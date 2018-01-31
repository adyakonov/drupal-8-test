<?php

namespace Drupal\entity_change_notifier\Plugin\MessageDestination;

/**
 * Class thrown when a notification to a destination fails.
 *
 * This class intentionally does not store the current configuration of a
 * message destination plugin. Instead, whatever is current will be used by
 * retries. That way, a bug or typo in a configuration (like a host name)
 * can be fixed and the same message can be retried.
 */
class NotifyException extends \RuntimeException {

  /**
   * The data that failed to send to the destination.
   *
   * In general, this is the array that is serialized to JSON, but individual
   * MessageDestination implementations may use another format. This data will
   * be sent on retries.
   *
   * @var array
   */
  protected $data;

  /**
   * The ID of the destination entity that threw the error.
   *
   * @var string
   */
  protected $destinationEntityId;

  /**
   * Construct a new NotifyException.
   *
   * @param array $data
   *   The data that failed to send to the message destination.
   * @param \Exception $previous
   *   The previous throwable used for the exception chaining.
   * @param string $destination_entity_id
   *   (optional) The ID of the destination entity that threw the error.
   * @param string $message
   *   (optional) The Exception message to throw.
   * @param int $code
   *   (optional) The Exception code.
   */
  public function __construct(array $data, \Exception $previous, $destination_entity_id = NULL, $message = "", $code = 0) {
    parent::__construct($message, $code, $previous);
    $this->data = $data;
    $this->destinationEntityId = $destination_entity_id;
  }

  /**
   * Return the data that failed to send to the destination.
   *
   * @return array
   *   The data that failed to send.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Return the ID of the destination entity that threw the error.
   *
   * @return string
   *   The ID of the destination entity that threw the error.
   */
  public function getDestinationEntityId() {
    return $this->destinationEntityId;
  }

}
