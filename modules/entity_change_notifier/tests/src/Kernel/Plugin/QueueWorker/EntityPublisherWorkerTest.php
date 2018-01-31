<?php

namespace Drupal\Tests\entity_change_notifier\Kernel\Plugin\QueueWorker;

use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface;
use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageGenerator;
use Drupal\entity_change_notifier\Plugin\QueueWorker\EntityPublisherWorker;
use Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\BufferingLogger;

/**
 * Tests the PublishWorker queue processor.
 *
 * @group entity_change_notifier
 *
 * @coversDefaultClass Drupal\entity_change_notifier\Plugin\QueueWorker\EntityPublisherWorker
 */
class EntityPublisherWorkerTest extends KernelTestBase {

  /**
   * Mock Failed Item.
   *
   * @var \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $failedItem;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'node',
    'file',
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
    $this->installConfig(['node', 'file', 'user']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installConfig(['entity_change_notifier_test']);
    $this->failedItem = $this->buildFailedItem();
  }

  /**
   * Test processing of requeued data.
   *
   * @covers ::processItem
   * @covers ::__construct
   * @covers \Drupal\entity_change_notifier\EntityPublisher::retryNotification
   */
  public function testProcessItem() {
    $logger = new BufferingLogger();
    $this->container->get('logger.factory')->addLogger($logger);

    /** @var \Drupal\entity_change_notifier\Plugin\QueueWorker\EntityPublisherWorker $queueRunner */
    $queueRunner = new EntityPublisherWorker(
      [],
      "test_plugin",
      "plugin_definition",
      $this->container->get('entity_change_notifier.entity_publisher'),
      $this->container->get('entity_type.manager'),
      $this->container->get('logger.channel.entity_change_notifier')
    );

    $queueRunner->processItem($this->failedItem);
    $logs = $logger->cleanLogs();
    $this->assertCount(1, $logs);
    $this->assertEquals(MessageDestinationInterface::ENTITY_INSERT, $logs[0][2]['%action']);
    $queue = $this->container->get('queue')->get('entity_change_notifier');
    $this->assertEquals(0, $queue->numberOfItems());
  }

  /**
   * Test that expired items are dropped from the queue if they fail again.
   */
  public function testItemExpired() {
    // We need to mock calls to log and respond differently depending on the
    // arguments. Simply calling method() multiple times doesn't work, as the
    // method expectations will conflict with each other. We can't use
    // willReturnOnConsecutiveCalls() either as one of our calls must throw an
    // exception.
    // https://stackoverflow.com/questions/5988616/phpunit-mock-method-multiple-calls-with-different-arguments
    // http://pietervogelaar.nl/phpunit-how-to-mock-multiple-calls-to-the-same-method
    /** @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
    $logger = $this->getMockBuilder(LoggerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $logger->expects($this->exactly(2))
      ->method('log')
      ->will($this->returnCallback(
        function ($level, $message, $context) {
          if ($message == 'Entity %action on %entity_type %bundle %entity_id.') {
            throw new \Exception('bad logger');
          }
        }
      ));
    $this->container->get('logger.factory')->addLogger($logger);

    /** @var \Drupal\entity_change_notifier\Plugin\QueueWorker\EntityPublisherWorker $queueRunner */
    $queueRunner = new EntityPublisherWorker(
      [],
      "test_plugin",
      "plugin_definition",
      $this->container->get('entity_change_notifier.entity_publisher'),
      $this->container->get('entity_type.manager'),
      $this->container->get('logger.channel.entity_change_notifier')
    );

    $failedItem = $this->buildFailedItem(-1);
    $queueRunner->processItem($failedItem);
    $queue = $this->container->get('queue')->get('entity_change_notifier');
    $this->assertEquals(0, $queue->numberOfItems());
  }

  /**
   * Test discarding messages when the destination is deleted.
   *
   * @covers ::processItem
   */
  public function testDeletedDestination() {
    $node = Node::create([
      'type' => 'ecn_test',
      'title' => $this->randomString(),
    ]);
    $node->save();

    $generator = new MessageGenerator($this->container->get('jsonapi.link_manager'));
    $message = $generator->createMessage(MessageDestinationInterface::ENTITY_INSERT, $node);
    $failedItem = new FailedItem('does_not_exist', $message, time() + 86400);

    /** @var \Drupal\entity_change_notifier\Plugin\QueueWorker\EntityPublisherWorker $queueRunner */
    $queueRunner = new EntityPublisherWorker(
      [],
      "test_plugin",
      "plugin_definition",
      $this->container->get('entity_change_notifier.entity_publisher'),
      $this->container->get('entity_type.manager'),
      $this->container->get('logger.channel.entity_change_notifier')
    );

    $logger = new BufferingLogger();
    $this->container->get('logger.factory')->addLogger($logger);

    $queueRunner->processItem($failedItem);
    $logs = $logger->cleanLogs();
    $this->assertEquals("Could not load destination %id. %title (%type %entity_id) will not be retried.", $logs[0][1]);
    $this->assertNotEmpty($logs[0][2]['link']);
  }

  /**
   * Test discarding messages when the content entity has no URL.
   *
   * @covers ::processItem
   */
  public function testDeletedDestinationNoUrl() {
    $file = File::create([
      'type' => 'file',
      'title' => $this->randomString(),
      'uri' => 'core/misc/druplicon.png',
    ]);
    $file->save();

    $generator = new MessageGenerator($this->container->get('jsonapi.link_manager'));
    $message = $generator->createMessage(MessageDestinationInterface::ENTITY_INSERT, $file);
    $failedItem = new FailedItem('does_not_exist', $message, time() + 86400);

    /** @var \Drupal\entity_change_notifier\Plugin\QueueWorker\EntityPublisherWorker $queueRunner */
    $queueRunner = new EntityPublisherWorker(
      [],
      "test_plugin",
      "plugin_definition",
      $this->container->get('entity_change_notifier.entity_publisher'),
      $this->container->get('entity_type.manager'),
      $this->container->get('logger.channel.entity_change_notifier')
    );

    $logger = new BufferingLogger();
    $this->container->get('logger.factory')->addLogger($logger);

    $queueRunner->processItem($failedItem);
    $logs = $logger->cleanLogs();
    $this->assertEquals("Could not load destination %id. %title (%type %entity_id) will not be retried.", $logs[0][1]);
    $this->assertEmpty($logs[0][2]['link']);
  }

  /**
   * Builds a Mock FailedItem.
   *
   * @param int $expires
   *   (optional) The length of time this item expires. Defaults to 1 day.
   *
   * @return \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem|\PHPUnit_Framework_MockObject_MockObject
   *   The mock failed item, without a creation time set.
   */
  private function buildFailedItem($expires = 86400) {
    $message = [
      'action' => MessageDestinationInterface::ENTITY_INSERT,
      'uri' => "http://example.com/node/1",
      'entity_id' => 100,
      'entity_uuid' => "44bc0505-0bc8-4cc4-b9ba-a423c0a8a82z",
      'entity_type' => "node",
      'bundle' => "test_bundle",
      '%action' => MessageDestinationInterface::ENTITY_INSERT,
      '%uri' => "http://example.com/node/1",
      '%entity_id' => 100,
      '%entity_uuid' => "44bc0505-0bc8-4cc4-b9ba-a423c0a8a82z",
      '%entity_type' => "node",
      '%bundle' => "test_bundle",
      'link' => '<a href="http://example.com/node/1">View</a>',
    ];

    $failedItem = $this->getMockBuilder(FailedItem::class)
      ->disableOriginalConstructor()
      ->getMock();
    $failedItem->method('getMessageDestinationEntityId')->willReturn('test_destination');
    $failedItem->method('getData')->willReturn($message);
    $failedItem->method('getExpires')->willReturn(time() + $expires);

    return $failedItem;
  }

}
