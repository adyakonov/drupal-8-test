<?php

namespace Drupal\Tests\entity_change_notifier\Kernel;

use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface;
use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageGenerator;
use Drupal\entity_change_notifier\Plugin\MessageDestination\NotifyException;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\BufferingLogger;

/**
 * Tests publishing entities to destinations.
 *
 * The EntityPublisher class is currently very simple, and just wraps calling
 * individual publishers in a loop. Since unit testing entities is very painful
 * (they don't support injection), we mark multiple classes as being covered
 * here. If EntityPublisher becomes more complex, we should split out it's tests
 * from those that cover Publisher itself.
 *
 * @group entity_change_notifier
 *
 * @coversDefaultClass Drupal\entity_change_notifier\EntityPublisher
 */
class EntityPublisherTest extends KernelTestBase {

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
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installConfig(['node', 'file']);
    $this->installConfig(['entity_change_notifier_test']);
  }

  /**
   * Test firing of inserts on hook_entity_insert.
   *
   * @covers ::notifyMultiple
   * @covers \Drupal\entity_change_notifier\Entity\Publisher::notify
   */
  public function testNotifyInsert() {
    $logger = new BufferingLogger();
    $this->container->get('logger.factory')->addLogger($logger);

    $node = Node::create([
      'type' => 'ecn_test',
      'title' => $this->randomString(),
    ]);
    $node->save();

    $logs = $logger->cleanLogs();
    $this->assertCount(2, $logs);
    $this->assertEquals(MessageDestinationInterface::ENTITY_INSERT, $logs[0][2]['%action']);
  }

  /**
   * Test firing of updates on hook_entity_update.
   *
   * @covers ::notifyMultiple
   * @covers \Drupal\entity_change_notifier\Entity\Publisher::notify
   */
  public function testNotifyUpdate() {
    $node = Node::create([
      'type' => 'ecn_test',
      'title' => $this->randomString(),
    ]);
    $node->save();

    $logger = new BufferingLogger();
    $this->container->get('logger.factory')->addLogger($logger);
    $node->save();

    $logs = $logger->cleanLogs();
    $this->assertCount(2, $logs);
    $this->assertEquals(MessageDestinationInterface::ENTITY_UPDATE, $logs[0][2]['%action']);
  }

  /**
   * Test notifying for URI-less entities.
   *
   * @covers \Drupal\entity_change_notifier\Entity\Publisher::notify
   */
  public function testNotifyNoUri() {
    // Turn on notifications for files.
    /** @var \Drupal\entity_change_notifier\Entity\PublisherInterface $publisher */
    $publisher = $this->container->get('entity_type.manager')->getStorage('ecn_publisher')->load('test_publisher');
    $publisher->setPublishTypes(['file' => ['file']]);
    $publisher->save();

    $file = File::create([
      'type' => 'file',
      'title' => $this->randomString(),
      'uri' => 'core/misc/druplicon.png',
    ]);
    $file->save();

    $logger = new BufferingLogger();
    $this->container->get('logger.factory')->addLogger($logger);
    $file->save();

    $logs = $logger->cleanLogs();
    $this->assertCount(2, $logs);
    $this->assertEquals(MessageDestinationInterface::ENTITY_UPDATE, $logs[0][2]['%action']);
    $this->assertEmpty($logs[1][2]['link']);
  }

  /**
   * Test firing of updates on hook_entity_delete.
   *
   * @covers ::notifyMultiple
   * @covers \Drupal\entity_change_notifier\Entity\Publisher::notify
   */
  public function testNotifyDelete() {
    $node = Node::create([
      'type' => 'ecn_test',
      'title' => $this->randomString(),
    ]);
    $node->save();

    $logger = new BufferingLogger();
    $this->container->get('logger.factory')->addLogger($logger);
    $node->delete();

    $logs = $logger->cleanLogs();
    $this->assertCount(2, $logs);
    $this->assertEquals(MessageDestinationInterface::ENTITY_DELETE, $logs[0][2]['%action']);
  }

  /**
   * Test queuing retry on failure.
   *
   * @covers ::notifyMultiple
   * @covers ::logRetry
   * @covers ::watchdogException
   * @covers \Drupal\entity_change_notifier\Entity\Publisher::notify
   * @covers \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem::__construct
   * @covers \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem::fromNotifyException
   * @covers \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem::fromNotifyException
   * @covers \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem::getExpires
   * @covers \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem::getData
   * @covers \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem::getMessageDestinationEntityId
   */
  public function testQueueRetry() {
    /** @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
    $logger = $this->getMockBuilder(LoggerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $logger->expects($this->any())->method('error')->willReturn(NULL);
    $logger->expects($this->any())->method('warning')->willReturn(NULL);
    $logger->expects($this->at(0))->method('log')->willThrowException(new \Exception('logging error'));
    $logger->expects($this->at(1))->method('log')->willReturn(TRUE);
    $this->container->get('logger.factory')->addLogger($logger);

    $node = Node::create([
      'type' => 'ecn_test',
      'title' => $this->randomString(),
    ]);
    $node->save();
    $queue = \Drupal::queue('entity_change_notifier_retry');
    $size = (int) $queue->numberOfItems();
    $this->assertSame(1, $size);

    $item = $queue->claimItem();
    /** @var \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem $failedItem */
    $failedItem = $item->data;
    $this->assertEquals($this->container->get('datetime.time')->getRequestTime() + 86400, $failedItem->getExpires());
    $this->assertEquals('test_destination', $failedItem->getMessageDestinationEntityId());
    $expected = [
      '%action' => 'insert',
      '%uri' => 'http://localhost/jsonapi/node/ecn_test/' . $node->uuid(),
      '%entity_id' => $node->id(),
      '%entity_uuid' => $node->uuid(),
      '%entity_type' => 'node',
      '%bundle' => 'ecn_test',
      'link' => '<a href="http://localhost/jsonapi/node/ecn_test/' . $node->uuid() . '">View</a>',
      'action' => 'insert',
      'uri' => 'http://localhost/jsonapi/node/ecn_test/' . $node->uuid(),
      'entity_id' => $node->id(),
      'entity_uuid' => $node->uuid(),
      'entity_type' => 'node',
      'bundle' => 'ecn_test',
    ];
    $data = $failedItem->getData();
    $data['link'] = (string) $data['link'];
    $this->assertEquals($expected, $data);
  }

  /**
   * Test queuing retry for URL-less entities.
   *
   * @covers ::logRetry
   */
  public function testQueueRetryNoCanonical() {
    $logger = new BufferingLogger();
    $this->container->get('logger.factory')->addLogger($logger);

    $file = File::create([
      'type' => 'file',
      'title' => $this->randomString(),
      'uri' => 'core/misc/druplicon.png',
    ]);
    $file->save();

    $publisher = $this->container->get('entity_change_notifier.entity_publisher');
    /** @var \Drupal\entity_change_notifier\Entity\DestinationInterface $destination */
    $generator = new MessageGenerator($this->container->get('jsonapi.link_manager'));
    $message = $generator->createMessage(MessageDestinationInterface::ENTITY_INSERT, $file);
    $exception = new NotifyException($message, new \Exception('Such failure'), 'test_destination');
    $destination = $this->container->get('entity_type.manager')->getStorage('ecn_destination')->load('test_destination');
    $publisher->logRetry($destination, $exception, $file);

    $logs = $logger->cleanLogs();
    $this->assertEmpty($logs[0][2]['link']);
  }

  /**
   * Test setting a custom retry expiry time.
   *
   * @covers ::notifyMultiple
   * @covers ::logRetry
   * @covers \Drupal\entity_change_notifier\Entity\Publisher::notify
   * @covers \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem::__construct
   * @covers \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem::fromNotifyException
   * @covers \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem::fromNotifyException
   * @covers \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem::getExpires
   */
  public function testSetQueueRetry() {
    /** @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
    $logger = $this->getMockBuilder(LoggerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $logger->expects($this->any())->method('error')->willReturn(NULL);
    $logger->expects($this->any())->method('warning')->willReturn(NULL);
    $logger->expects($this->at(0))->method('log')->willThrowException(new \Exception('logging error'));
    $logger->expects($this->at(1))->method('log')->willReturn(TRUE);

    $this->container->get('logger.factory')->addLogger($logger);

    /** @var \Drupal\entity_change_notifier\Entity\DestinationInterface $destination */
    $destination = $this->container->get('entity_type.manager')->getStorage('ecn_destination')->load('test_destination');

    // Override the expiry to one second.
    $destination->setMessageExpiry(1);
    $destination->save();

    $node = Node::create([
      'type' => 'ecn_test',
      'title' => $this->randomString(),
    ]);
    $node->save();
    $queue = \Drupal::queue('entity_change_notifier_retry');
    $size = (int) $queue->numberOfItems();
    $this->assertSame(1, $size);

    $item = $queue->claimItem();
    /** @var \Drupal\entity_change_notifier\Plugin\QueueWorker\FailedItem $failedItem */
    $failedItem = $item->data;
    $this->assertEquals($this->container->get('datetime.time')->getRequestTime() + 1, $failedItem->getExpires());
  }

  /**
   * Test that we can retry a failed notification.
   *
   * @covers ::retryNotification
   */
  public function testRetryNotification() {
    $node = Node::create([
      'type' => 'ecn_test',
      'title' => $this->randomString(),
    ]);
    $node->save();

    $message = [
      'action' => MessageDestinationInterface::ENTITY_INSERT,
      'uri' => $node->toUrl()->toString(),
      'entity_id' => $node->id(),
      'entity_uuid' => $node->uuid(),
      'entity_type' => 'node',
      'bundle' => 'ecn_test',
    ];

    /** @var \Drupal\entity_change_notifier\Entity\DestinationInterface $destination */
    $destination = $this->container->get('entity_type.manager')->getStorage('ecn_destination')->load('test_destination');

    $logger = new BufferingLogger();
    $this->container->get('logger.factory')->addLogger($logger);

    $publisher = $this->container->get('entity_change_notifier.entity_publisher');
    $publisher->retryNotification($destination, $message);

    $logs = $logger->cleanLogs();
    $this->assertCount(1, $logs);
  }

  /**
   * Test that we can retry a failed notification.
   *
   * @covers ::retryNotification
   */
  public function testRetryNotificationFails() {
    $node = Node::create([
      'type' => 'ecn_test',
      'title' => $this->randomString(),
    ]);
    $node->save();

    $message = [
      'action' => MessageDestinationInterface::ENTITY_INSERT,
      'uri' => $node->toUrl()->toString(),
      'entity_id' => $node->id(),
      'entity_uuid' => $node->uuid(),
      'entity_type' => 'node',
      'bundle' => 'ecn_test',
    ];

    /** @var \Drupal\entity_change_notifier\Entity\DestinationInterface $destination */
    $destination = $this->container->get('entity_type.manager')->getStorage('ecn_destination')->load('test_destination');

    /** @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
    $logger = $this->getMockBuilder(LoggerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $logger->expects($this->at(0))->method('log')->willThrowException(new \Exception('logging error'));

    $this->container->get('logger.factory')->addLogger($logger);

    $publisher = $this->container->get('entity_change_notifier.entity_publisher');

    $this->setExpectedException(NotifyException::class, 'Unable to log the notification.');
    $publisher->retryNotification($destination, $message);
  }

}
