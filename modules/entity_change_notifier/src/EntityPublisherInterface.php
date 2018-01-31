<?php

namespace Drupal\entity_change_notifier;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_change_notifier\Entity\DestinationInterface;
use Drupal\entity_change_notifier\Plugin\MessageDestination\NotifyException;

/**
 * EntityPublisherInterface.
 */
interface EntityPublisherInterface {

  /**
   * Notify all publishers that an entity has been created or modified.
   *
   * @param string $action
   *   The action associated with the change, from the
   *   \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface
   *   constants.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being created or modified.
   */
  public function notifyMultiple($action, EntityInterface $entity);

  /**
   * Retry sending a notification to a destination.
   *
   * @param \Drupal\entity_change_notifier\Entity\DestinationInterface $destination
   *   The the destination config entity.
   * @param array $data
   *   The message to retry.
   */
  public function retryNotification(DestinationInterface $destination, array $data);

  /**
   * Logs a failed notification that will be retried.
   *
   * @param \Drupal\entity_change_notifier\Entity\DestinationInterface $destination
   *   The destination config entity.
   * @param \Drupal\entity_change_notifier\Plugin\MessageDestination\NotifyException $e
   *   The NotifyException that causes retry to be called.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   (optional) The entity to requeue. If the entity has been deleted, then
   *   this parameter may be omitted.
   *
   * @return \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem
   *   A failed item that can be added to the retry queue.
   */
  public function logRetry(DestinationInterface $destination, NotifyException $e, EntityInterface $entity = NULL);

  /**
   * Logs a failed notification that will be dropped.
   *
   * @param \Drupal\entity_change_notifier\Entity\DestinationInterface $destination
   *   The destination config entity.
   * @param \Drupal\entity_change_notifier\Plugin\MessageDestination\NotifyException $e
   *   The NotifyException that causes retry to be called.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   (optional) The entity to requeue. If the entity has been deleted, then
   *   this parameter may be omitted.
   */
  public function logDropped(DestinationInterface $destination, NotifyException $e, EntityInterface $entity = NULL);

}
