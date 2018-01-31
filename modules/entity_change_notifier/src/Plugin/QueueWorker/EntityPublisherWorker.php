<?php

namespace Drupal\entity_change_notifier\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_change_notifier\EntityPublisherInterface;
use Drupal\entity_change_notifier\Plugin\MessageDestination\NotifyException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker implementation to handle retrying failed messages.
 *
 * @QueueWorker(
 *   id ="entity_change_notifier_retry",
 *   title = @Translation("Retries failed publisher actions"),
 *   cron = {15},
 * )
 */
class EntityPublisherWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Entity Publisher Service.
   *
   * @var \Drupal\entity_change_notifier\EntityPublisherInterface
   */
  protected $entityPublisher;

  /**
   * The service used to load Publisher config entities.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger used to notify about dropped messages.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a EntityPublisherWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_change_notifier\EntityPublisherInterface $entity_publisher
   *   The entity publisher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger used to log dropped notifications.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityPublisherInterface $entity_publisher, EntityTypeManagerInterface $entity_type_manager, LoggerChannelInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityPublisher = $entity_publisher;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_change_notifier.entity_publisher'),
      $container->get('entity_type.manager'),
      $container->get('logger.channel.entity_change_notifier')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem $data */
    /** @var \Drupal\entity_change_notifier\Entity\DestinationInterface $destinationConfigEntity */
    $destinationConfigEntity = $this->entityTypeManager->getStorage('ecn_destination')->load($data->getMessageDestinationEntityId());

    $entityId = $data->getData()['entity_id'];
    $entityType = $data->getData()['entity_type'];
    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);

    // Drop the queue item if the destination has been deleted.
    if (!$destinationConfigEntity) {
      // The entity could have been deleted too.
      $entityPlaceholders = [
        '%title' => isset($entity) ? $entity->label() : new TranslatableMarkup('Unknown title'),
        '%entity_id' => $entityId,
        '%type' => $entityType,
      ];
      if ($entity && $entity->hasLinkTemplate('canonical')) {
        $entityPlaceholders['link'] = new TranslatableMarkup('<a href="@url">View</a>', [
          '@url' => $entity->toUrl('canonical')->toString(),
        ]);
      }

      $this->logger->error("Could not load destination %id. %title (%type %entity_id) will not be retried.",
        [
          '%id' => $data->getMessageDestinationEntityId(),
        ] + $entityPlaceholders);

      return;
    }

    try {
      $this->entityPublisher->retryNotification($destinationConfigEntity, $data->getData());
    }
    catch (NotifyException $e) {
      if ($data->getExpires() < time()) {
        $this->entityPublisher->logDropped($destinationConfigEntity, $e, $entity);
      }
      else {
        // Cron's queue processor always logs exceptions, and there's no way to
        // note an item has failed but doesn't need to be logged. In this case,
        // we are in a different logger channel, so while there is some overlap
        // in the messages it's not complete.
        $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
        $this->entityPublisher->logRetry($destinationConfigEntity, $e, $entity);
        throw $e;
      }
    }
  }

}
