<?php

namespace Drupal\entity_change_notifier\Plugin\MessageDestination;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Generate the message body to send to a destination.
 */
class MessageGenerator {
  use DependencySerializationTrait;

  /**
   * The link manager used to generate URIs.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $jsonApiLinkManager;

  /**
   * Construct a message generator object.
   *
   * @param \Drupal\jsonapi\LinkManager\LinkManager $jsonapi_link_manager
   *   The link manager used to generate the URI to the json-api representation
   *   of an entity.
   */
  public function __construct(LinkManager $jsonapi_link_manager) {
    $this->jsonApiLinkManager = $jsonapi_link_manager;
  }

  /**
   * Create a message to send to a destination.
   *
   * @param string $action
   *   The action associated with the entity, from one of the
   *   MessageDestinationInterface constants.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the message is for.
   *
   * @return array
   *   An array matching the
   *
   * @see \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface
   */
  public function createMessage($action, EntityInterface $entity) {
    $resourceType = new ResourceType(
      $entity->getEntityTypeId(),
      $entity->bundle(),
      get_class($entity)
    );

    $uri = $this->jsonApiLinkManager->getEntityLink($entity->uuid(), $resourceType, [], 'individual');

    $message = [
      'action' => $action,
      'uri' => $uri,
      'entity_id' => $entity->id(),
      'entity_uuid' => $entity->uuid(),
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
    ];

    return $message;
  }

}
