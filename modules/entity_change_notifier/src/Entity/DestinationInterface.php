<?php

namespace Drupal\entity_change_notifier\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Provides an interface for defining Destination entities.
 */
interface DestinationInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Return the message destination plugin ID.
   *
   * @return string
   *   The message destination plugin ID.
   */
  public function getMessageDestination();

  /**
   * Return the array of message destination plugin settings.
   *
   * @return array
   *   An array of plugin settings.
   */
  public function getMessageDestinationSettings();

  /**
   * Set the message destination plugin settings.
   *
   * @param array $message_destination_settings
   *   The array of plugin settings. This must match the schema provided by the
   *   plugin's containing module.
   */
  public function setMessageDestinationSettings(array $message_destination_settings);

  /**
   * Return the label.
   *
   * @return string
   *   The label.
   */
  public function getLabel();

  /**
   * Set how long messages should be retried in the case of a delivery failure.
   *
   * @param int $seconds
   *   The number of seconds a new message should be valid for.
   */
  public function setMessageExpiry($seconds);

  /**
   * Set how long messages should be retried in the case of a delivery failure.
   *
   * @return int
   *   The number of seconds a new message should be valid for.
   */
  public function getMessageExpiry();

}
