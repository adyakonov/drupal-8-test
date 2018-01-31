<?php

namespace Drupal\Tests\entity_change_notifier\Kernel\Plugin\MessageDestination;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;

/**
 * Base class to set up a suitable entity for testing notifications.
 */
abstract class MessageDestinationTestBase extends KernelTestBase {

  protected static $modules = [
    'system',
    'field',
    'node',
    'text',
    'user',
    'serialization',
    'jsonapi',
    'entity_change_notifier',
    'entity_change_notifier_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['node', 'entity_change_notifier_test']);
  }

  /**
   * Create and save a node.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node.
   */
  protected function createNode() {
    $node = Node::create([
      'type' => 'ecn_test',
      'title' => $this->randomString(),
    ]);
    $node->save();
    return $node;
  }

}
