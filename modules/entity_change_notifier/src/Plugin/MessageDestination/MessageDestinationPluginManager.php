<?php

namespace Drupal\entity_change_notifier\Plugin\MessageDestination;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_change_notifier\Plugin\Annotation\MessageDestination;

/**
 * Plugin Manager for MessageDestinations.
 */
class MessageDestinationPluginManager extends DefaultPluginManager {

  /**
   * Constructor for MessageDestinationPluginManager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   *
   * @codeCoverageIgnore
   *   We ignore code coverage on this for now as there is no simple way for us
   *   to test these calls without mocking / replacing them. If this becomes
   *   more complex it should be tested.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/MessageDestination', $namespaces, $module_handler, MessageDestinationInterface::class, MessageDestination::class);
    $this->setCacheBackend($cache_backend, 'entity_change_notifier_destination_plugins');

  }

}
