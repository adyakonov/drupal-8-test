<?php

namespace Drupal\entity_change_notifier\Plugin\MessageDestination;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a common interface for all notification destinations.
 *
 * @package Drupal\entity_change_notifier\Plugin\MessageDestination
 */
interface MessageDestinationInterface extends ConfigurablePluginInterface {

  /**
   * The string associated with inserting an entity.
   */
  const ENTITY_INSERT = 'insert';

  /**
   * The string associated with updating an entity.
   */
  const ENTITY_UPDATE = 'update';

  /**
   * The string associated with deleting an entity.
   */
  const ENTITY_DELETE = 'delete';

  /**
   * Notify the destination of a given action on an entity.
   *
   * @param string $action
   *   The action to notify on, from one of the ENTITY_ constants.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity affected by the action.
   *
   * @return mixed
   *   The result of the notification, of applicable.
   *
   * @throws \Drupal\entity_change_notifier\Plugin\MessageDestination\NotifyException
   *   Thrown when the notification failed to send. In general, implementations
   *   should wrap a try around their "send" call, and re-throw any exceptions
   *   as a NotifyException.
   */
  public function notify($action, EntityInterface $entity);

  /**
   * Notify the destination with an existing array of data.
   *
   * This is primarily used by retries of failed notifications, to ensure the
   * notification content doesn't change. Implementations should consider
   * calling this method from their notify() method.
   *
   * @param array $data
   *   The data to send to the message destination.
   *
   * @return mixed
   *   The response from the destination, if applicable.
   *
   * @todo Not sure about the return type here. Could also be void, or a whole new class.
   */
  public function notifyDirect(array $data);

  /**
   * Set the destination configuration entity used to instantiate this plugin.
   *
   * We add in the ID of this entity so the message destination plugin can
   * throw reasonable errors with the ID of the specific config entity that
   * threw the error. Otherwise, if more than one of the same destination
   * type are configured, we can't know which instance actually threw the
   * error.
   *
   * @param string $entity_id
   *   The ID of the configuration entity.
   */
  public function setDestinationConfigurationEntity($entity_id);

}
