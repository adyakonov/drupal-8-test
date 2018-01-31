<?php

namespace Drupal\entity_change_notifier\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the Publisher entity.
 *
 * @ConfigEntityType(
 *   id = "ecn_publisher",
 *   label = @Translation("Publisher"),
 *   label_singular = @Translation("publisher"),
 *   label_plural = @Translation("publishers"),
 *   label_count = @PluralTranslation(
 *     singular = "@count publisher",
 *     plural = "@count publishers",
 *   ),
 *   label_collection = @Translation("Publishers"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\entity_change_notifier\PublisherListBuilder",
 *     "form" = {
 *       "add" = "Drupal\entity_change_notifier\Form\PublisherForm",
 *       "edit" = "Drupal\entity_change_notifier\Form\PublisherForm",
 *       "delete" = "Drupal\entity_change_notifier\Form\PublisherDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "ecn_publisher",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/services/entity-change-notifier/publisher/{ecn_publisher}",
 *     "add-form" = "/admin/config/services/entity-change-notifier/publisher/add",
 *     "edit-form" = "/admin/config/services/entity-change-notifier/publisher/{ecn_publisher}/edit",
 *     "delete-form" = "/admin/config/services/entity-change-notifier/publisher/{ecn_publisher}/delete",
 *     "collection" = "/admin/config/services/entity-change-notifier/publisher"
 *   }
 * )
 */
class Publisher extends ConfigEntityBase implements PublisherInterface {
  use StringTranslationTrait;

  /**
   * The Publisher ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Publisher label.
   *
   * @var string
   */
  protected $label;

  /**
   * The ID of the destination configuration entity.
   *
   * @var string
   */
  protected $destination;

  /**
   * The array of entity types to publish.
   *
   * Each array key is the entity type, containing a list of entity bundles.
   *
   * @var array
   */
  protected $publish_types = [];

  /**
   * {@inheritdoc}
   */
  public function getDestination() {
    return $this->destination;
  }

  /**
   * {@inheritdoc}
   */
  public function getPublishTypes() {
    return $this->publish_types;
  }

  /**
   * {@inheritdoc}
   */
  public function setPublishTypes(array $publishTypes) {
    $this->publish_types = $publishTypes;
  }

  /**
   * {@inheritdoc}
   */
  public function notify($action, EntityInterface $entity) {
    $types = $this->getPublishTypes();

    $entityTypeId = $entity->getEntityTypeId();
    if (isset($types[$entityTypeId]) && in_array($entity->bundle(), $types[$entityTypeId])) {
      /** @var \Drupal\entity_change_notifier\Entity\DestinationInterface $destinationConfigEntity */
      $destinationConfigEntity = \Drupal::service('entity_type.manager')->getStorage('ecn_destination')->load($this->destination);

      /** @var \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface $messageDestination */
      $messageDestinationSettings = $destinationConfigEntity->getMessageDestinationSettings();

      $messageDestination = \Drupal::service('entity_change_notifier.message_destination_manager')->createInstance($destinationConfigEntity->getMessageDestination(), $messageDestinationSettings);
      $messageDestination->setDestinationConfigurationEntity($destinationConfigEntity->id());

      $messageDestination->notify($action, $entity);
      $context = [
        '%action' => $action,
        '%label' => $destinationConfigEntity->label(),
        '%entity' => $entity->label(),
      ];
      if ($entity->hasLinkTemplate('canonical')) {
        $context['link'] = $this->t('<a href="@url">View</a>', [
          '@url' => $entity->toUrl()
            ->toString(),
        ]);
      }
      \Drupal::logger('entity_change_notifier')->info('%action notification sent to %label for %entity', $context);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Add a dependency on the destination.
    $destination = $this->entityTypeManager()->getStorage('ecn_destination')->load($this->getDestination());
    $this->addDependency('config', $destination->getConfigDependencyName());

    // Add a dependency on all configured entities and bundles.
    foreach ($this->getPublishTypes() as $entity => $bundles) {
      foreach ($bundles as $bundle) {
        $definition = $this->entityTypeManager()->getDefinition($entity);
        $dependency = $definition->getBundleConfigDependency($bundle);
        $this->addDependency($dependency['type'], $dependency['name']);
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchEntities() {
    $entities = [];

    foreach ($this->getPublishTypes() as $entity => $bundles) {
      $query = $this->entityTypeManager()->getStorage($entity)->getQuery();
      $query->condition('type', $bundles, 'IN');
      $results = $query->execute();
      foreach ($results as $entity_id) {
        $entities[] = [
          'entity_type' => $entity,
          'entity_id' => $entity_id,
        ];
      }
    }

    return $entities;
  }

}
