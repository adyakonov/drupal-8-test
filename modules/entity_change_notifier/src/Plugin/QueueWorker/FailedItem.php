<?php

namespace Drupal\entity_change_notifier\Plugin\QueueWorker;

use Drupal\entity_change_notifier\Plugin\MessageDestination\NotifyException;

/**
 * A failed notification that must be retried later.
 *
 * This class must remain serialization-safe, across module upgrades. As well,
 * since it needs to be able to handle failed notifications for deleted
 * entities, it must not rely on Entity objects since they may no longer exist.
 *
 * We use this class instead of NotifyException so we don't serialize
 * backtraces and other large debugging information to the queue.
 */
class FailedItem {

  /**
   * The time at which no more retries should be made, as a unix timestamp.
   *
   * @var int
   */
  protected $expires;

  /**
   * The msg data that failed and needs to be resent.
   *
   * @var array
   */
  protected $data;

  /**
   * The entity id of the Destination Config which failed.
   *
   * @var string
   */
  protected $messageDestinationEntityId;

  /**
   * FailedItem constructor.
   *
   * @param string $destination_entity_id
   *   The id of the entity for which notification is being sent.
   * @param array $data
   *   The message data.
   * @param int $expires
   *   The time at which no more retries should be made, as a unix timestamp.
   */
  public function __construct($destination_entity_id, array $data, $expires) {
    $this->messageDestinationEntityId = $destination_entity_id;
    $this->data = $data;
    $this->expires = $expires;
  }

  /**
   * Create a FailedItem from a NotifyException.
   *
   * @param \Drupal\entity_change_notifier\Plugin\MessageDestination\NotifyException $exception
   *   The NotifyException to use to create FailedItem.
   * @param int $expires
   *   When retry queue item will expire.
   *
   * @return \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem
   *   A FailedItem created from the NotifyException
   */
  public static function fromNotifyException(NotifyException $exception, $expires) {
    return new static(
      $exception->getDestinationEntityId(),
      $exception->getData(),
      $expires
    );
  }

  /**
   * Get the expires attribute.
   *
   * @return int
   *   Expires attribute.
   */
  public function getExpires() {
    return $this->expires;
  }

  /**
   * Get Message Data.
   *
   * @return array
   *   Array of msg data.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Get the Destination Config Entity Id.
   *
   * @return string
   *   The id of the Destination config entity.
   */
  public function getMessageDestinationEntityId() {
    return $this->messageDestinationEntityId;
  }

}
