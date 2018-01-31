<?php

namespace Drupal\entity_change_notifier\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for defining Publisher entities.
 */
interface PublisherInterface extends ConfigEntityInterface {

  /**
   * Return the message destination configuration entity ID.
   *
   * @return string
   *   The message destination configuration entity ID.
   */
  public function getDestination();

  /**
   * Sets the Publish Types that this publisher will publish.
   *
   * @param array $publishTypes
   *   Array of publish types.
   */
  public function setPublishTypes(array $publishTypes);

  /**
   * Return the Publish Types that this publisher will publish.
   *
   * @return array
   *   array of publish types.
   */
  public function getPublishTypes();

  /**
   * Notify this publisher about an action on an entity.
   *
   * It is up to the publisher to decide if the action should be forwarded on to
   * a destination or not.
   *
   * @param string $action
   *   The action to notify on, from one of the MessageDestinationInterface
   *   constants.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity affected by the action.
   */
  public function notify($action, EntityInterface $entity);

  /**
   * Fetch entities that match this publisher.
   *
   * @return array
   *   An array of matched entities, with each array item containing:
   *     - entity_type: The type of matched entity, such as 'node'.
   *     - entity_id: The ID of the matched entity.
   */
  public function fetchEntities();

}
