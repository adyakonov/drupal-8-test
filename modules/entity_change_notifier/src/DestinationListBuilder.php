<?php

namespace Drupal\entity_change_notifier;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Destination entities.
 */
class DestinationListBuilder extends ConfigEntityListBuilder {

  /**
   * The plugin manager for message destinations.
   *
   * @var \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationPluginManager
   */
  private $pluginManager;

  /**
   * Constructs a new DestinationListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationPluginManager $pluginManager
   *   The plugin manager for message destinations.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, MessageDestinationPluginManager $pluginManager) {
    parent::__construct($entity_type, $storage);
    $this->pluginManager = $pluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Destination');
    $header['id'] = $this->t('Machine name');
    $header['message_destination'] = $this->t('Message destination');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\entity_change_notifier\Entity\DestinationInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();

    // @todo Add a way for plugins to alter this, to add in useful settings?
    $row['message_destination'] = $this->pluginManager->getDefinition($entity->getMessageDestination())['label'];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('No destinations available. <a href=":link">Add destination</a>.', [
      ':link' => Url::fromRoute('entity.ecn_destination.add_form')->toString(),
    ]);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('entity_change_notifier.message_destination_manager')
    );
  }

}
