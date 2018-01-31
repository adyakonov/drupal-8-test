<?php

namespace Drupal\entity_change_notifier;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\entity_change_notifier\Entity\DestinationInterface;
use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationPluginManager;
use Drupal\entity_change_notifier\Plugin\MessageDestination\NotifyException;
use Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem;
use Psr\Log\LoggerInterface;

/**
 * Send entity notifications to all publishers, handling failed messages.
 */
class EntityPublisher implements EntityPublisherInterface {

  /**
   * The service used to load Publisher config entities.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The queue used to retry failed publishing actions.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $retryQueue;

  /**
   * The logger used to notify admins of failed messages.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Destination plugin manager.
   *
   * @var \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationPluginManager
   */
  protected $destinationPluginManager;

  /**
   * The service used to get the request time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new EntityPublisher.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager used to load publisher config entities.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory used to retrieve the "retry" queue.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger used to notify admins of failed messages.
   * @param \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationPluginManager $destinationManager
   *   The destination plugin manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The service used to get the request time.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue_factory, LoggerInterface $logger, MessageDestinationPluginManager $destinationManager, TimeInterface $time) {
    $this->entityTypeManager = $entity_type_manager;
    $this->retryQueue = $queue_factory->get('entity_change_notifier_retry');
    $this->logger = $logger;
    $this->destinationPluginManager = $destinationManager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function notifyMultiple($action, EntityInterface $entity) {
    /** @var \Drupal\entity_change_notifier\Entity\PublisherInterface[] $publishers */
    $publishers = $this->entityTypeManager->getStorage('ecn_publisher')->loadMultiple();

    /** @var \Drupal\entity_change_notifier\Entity\PublisherInterface $publisher */
    foreach ($publishers as $publisher) {
      try {
        $publisher->notify($action, $entity);
      }
      catch (NotifyException $e) {
        /** @var \Drupal\entity_change_notifier\Entity\DestinationInterface $destination */
        $destination = $this->entityTypeManager->getStorage('ecn_destination')->load($publisher->getDestination());
        $failedItem = $this->logRetry($destination, $e, $entity);
        $this->retryQueue->createItem($failedItem);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function logRetry(DestinationInterface $destination, NotifyException $e, EntityInterface $entity = NULL) {
    $failedItem = FailedItem::fromNotifyException($e, $this->time->getRequestTime() + $destination->getMessageExpiry());

    // We want to log the underlying error and not just the generic fact that
    // a notification failed.
    $this->watchdogException($e->getPrevious(), '%title failed to send to %destination (%id) and will be retried later: %type: @message in %function (line %line of %file).', $this->logPlaceholders($destination, $entity), RfcLogLevel::WARNING);

    return $failedItem;
  }

  /**
   * {@inheritdoc}
   */
  public function logDropped(DestinationInterface $destination, NotifyException $e, EntityInterface $entity = NULL) {
    // We want to log the underlying error and not just the generic fact that
    // a notification failed.
    $this->watchdogException($e->getPrevious(), '%title failed to send to %destination (%id) and has been dropped: @message', $this->logPlaceholders($destination, $entity), RfcLogLevel::WARNING);
  }

  /**
   * {@inheritdoc}
   */
  public function retryNotification(DestinationInterface $destination, array $data) {
    /** @var \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface $messageDestination */
    $messageDestination = $this->destinationPluginManager->createInstance($destination->getMessageDestination(), $destination->getMessageDestinationSettings());
    $messageDestination->setDestinationConfigurationEntity($destination->id());
    $messageDestination->notifyDirect($data);
  }

  /**
   * Helper to log an exception to the watchdog.
   *
   * @param \Exception $exception
   *   The exception that is going to be logged.
   * @param string $message
   *   The message to store in the log. If empty, a text that contains all
   *   useful information about the passed-in exception is used.
   * @param array $variables
   *   Array of variables to replace in the message on display or
   *   NULL if message is already translated or not possible to
   *   translate.
   * @param int $severity
   *   The severity of the message, as per RFC 3164.
   * @param string $link
   *   A link to associate with the message.
   *
   * @see watchdog_exception
   */
  private function watchdogException(\Exception $exception, $message = NULL, array $variables = [], $severity = RfcLogLevel::ERROR, $link = NULL) {
    // Use a default value if $message is not set.
    if (empty($message)) {
      $message = '%type: @message in %function (line %line of %file).';
    }

    if ($link) {
      $variables['link'] = $link;
    }

    $variables += Error::decodeException($exception);

    $this->logger->log($severity, $message, $variables);
  }

  /**
   * Return common log placeholders.
   *
   * @param \Drupal\entity_change_notifier\Entity\DestinationInterface $destination
   *   The destination configuration entity associated with the log message.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   (optional) The entity that failed to notify.
   *
   * @return array
   *   An array of placeholders containing:
   *     - '%destination'
   *     - '%id'
   *     - '%title'
   *     - 'link', if one exists
   */
  private function logPlaceholders(DestinationInterface $destination, EntityInterface $entity = NULL) {
    return [
      '%destination' => $destination->label(),
      '%id' => $destination->id(),
    ] + $this->entityPlaceholders($entity);
  }

  /**
   * Generate placeholders for use in log messages.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   (optional) The entity that the notification failed to send for.
   *
   * @return array
   *   An array of placeholders containing:
   *     - '%title'
   *     - 'link', if one exists
   */
  private function entityPlaceholders(EntityInterface $entity = NULL) {
    $entityPlaceholders = [
      '%title' => isset($entity) ? $entity->label() : 'Unknown title',
    ];

    // NULL is the default parameter for watchdogException().
    $link = NULL;
    if ($entity && $entity->hasLinkTemplate('canonical')) {
      $link = new TranslatableMarkup('<a href="@url">View</a>', [
        '@url' => $entity->toUrl('canonical')->toString(),
      ]);
      $entityPlaceholders['link'] = $link;
    }
    return $entityPlaceholders;
  }

}
