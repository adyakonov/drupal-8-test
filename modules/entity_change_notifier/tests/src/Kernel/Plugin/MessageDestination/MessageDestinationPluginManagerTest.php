<?php

namespace Drupal\Tests\entity_change_notifier\Kernel\Plugin\MessageDestination;

use Drupal\KernelTests\KernelTestBase;

/**
 * Test the MessageDestination plugin manager.
 *
 * @group entity_change_notifier
 */
class MessageDestinationPluginManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_change_notifier'];

  /**
   * Test that our plugin manager is registered and discovering correctly.
   */
  public function testPluginDiscovery() {
    $manager = $this->container->get('entity_change_notifier.message_destination_manager');
    $implementations = $manager->getDefinitions();

    // This should match the number of implementations we ship in the core
    // module.
    $this->assertCount(2, $implementations);
  }

}
