<?php

/**
 * @file
 * Drush commands and callbacks for the Entity Change Notifier module.
 */

use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface;

/**
 * Implements hook_drush_command().
 */
function entity_change_notifier_drush_command() {
  $commands['entity-change-notifier-send'] = [
    'aliases' => ['ecns'],
    'description' => dt('Manually send notifications for a given publisher.'),
    'arguments' => [
      'publisher id' => dt('The unique ID of the publisher.'),
    ],
    'options' => [
      'action' => dt("The action to send with the notifications. One of 'insert', 'update'. Defaults to 'update'."),
    ],
    'examples' => [
      'drush ecns article_api --action insert' => dt('Sends all entities matched by the article_api publisher as if they have been newly created.'),
    ],
  ];

  return $commands;
}

/**
 * Implements hook_drush_help().
 */
function entity_change_notifier_drush_help($section) {
  if ($section != 'drush:entity-change-notifier') {
    return NULL;
  }
  return dt('Debugging tools for Entity Change Notifier.');
}

/**
 * Command callback to send notifications.
 *
 * @param string $publisher_id
 *   The entity ID of the publisher to send notifications for.
 *
 * @return bool
 *   True if the callback succeeded, FALSE otherwise.
 */
function drush_entity_change_notifier_send($publisher_id) {
  $action = drush_get_option('action', MessageDestinationInterface::ENTITY_UPDATE);
  $actions = [
    MessageDestinationInterface::ENTITY_INSERT,
    MessageDestinationInterface::ENTITY_UPDATE,
  ];
  if (!in_array($action, $actions, TRUE)) {
    return drush_set_error(dt("--action must be one of 'insert', 'update'"));
  }

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
  $entity_type_manager = \Drupal::service('entity_type.manager');

  /** @var \Drupal\entity_change_notifier\Entity\PublisherInterface $publisher */
  $publisher = $entity_type_manager->getStorage('ecn_publisher')->load($publisher_id);

  $entity_list = $publisher->fetchEntities();
  foreach ($entity_list as &$item) {
    $item['action'] = $action;
  }

  $chunks = array_chunk($entity_list, 100);
  $total = count($entity_list);
  $progress = 0;
  $operations = [];
  foreach ($chunks as $chunk) {
    $progress += count($chunk);
    $operation = [
      $chunk,
      dt('@percent% (Processing @progress of @total)', [
        '@percent' => round(100 * $progress / $total),
        '@progress' => $progress,
        '@total' => $total,
      ]),
    ];

    $operations[] = ['_drush_entity_change_notifier_send_callback', $operation];
  }

  $batch = [
    'operations' => $operations,
    'title' => dt('Entity change notifier send'),
    'finished' => '_drush_entity_change_notifier_send_finished_callback',
    'progress_message' => dt('@current entities of @total were processed.'),
  ];

  batch_set($batch);
  drush_backend_batch_process();

  return TRUE;
}

/**
 * Batch callback to process a subset of notifications.
 *
 * @param array[] $chunk
 *   The array of entity_type and entity_id pairs.
 * @param string $details
 *   A feedback message to be sent to the user.
 * @param \DrushBatchContext $context
 *   This param is added automatically by drush. It is used to interact with the
 *   process executing the batches.
 */
function _drush_entity_change_notifier_send_callback(array $chunk, $details, \DrushBatchContext &$context) {
  $context['message'] = $details;
  // Make sure to only initialize the results the first time.
  if (!isset($context['results']['success'])) {
    $context['results']['success'] = $context['results']['error'] = 0;
  }

  $entity_publisher = \Drupal::service('entity_change_notifier.entity_publisher');
  foreach ($chunk as $item) {
    $entity = \Drupal::service('entity_type.manager')->getStorage($item['entity_type'])->load($item['entity_id']);
    try {
      $entity_publisher->notifyMultiple($item['action'], $entity);
      $context['results']['success']++;
    }
    catch (\Exception $e) {
      $context['results']['error']++;
    }
  }
}

/**
 * This callback is called when the batch process finishes.
 *
 * @param bool $success
 *   TRUE if the batch finished correctly, FALSE otherwise.
 * @param array $results
 *   The array of results from the batch.
 * @param array $operations
 *   The array of operations that were executed.
 */
function _drush_entity_change_notifier_send_finished_callback($success, array $results, array $operations) {
  if ($success) {
    // Let the user know we have finished.
    drush_log(dt('@succeeded entities were published correctly. @errored entities failed.', [
      '@succeeded' => empty($results['success']) ? 0 : $results['success'],
      '@errored' => empty($results['error']) ? 0 : $results['error'],
    ]), 'ok');
  }
}
