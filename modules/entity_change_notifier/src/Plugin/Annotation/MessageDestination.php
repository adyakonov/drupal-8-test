<?php

namespace Drupal\entity_change_notifier\Plugin\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * MessageDestination plugin definition.
 *
 * A MessageDestination is a service that accepts notifications about entity
 * actions. Destinations could be queues, webhooks, or other Drupal APIs.
 *
 * @see plugin_api
 *
 * @see \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface
 *
 * @Annotation
 */
class MessageDestination extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
