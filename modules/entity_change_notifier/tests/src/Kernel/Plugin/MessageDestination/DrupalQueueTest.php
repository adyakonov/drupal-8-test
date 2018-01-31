<?php

namespace Drupal\Tests\entity_change_notifier\Kernel\Plugin\MessageDestination;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface;
use Drupal\entity_change_notifier\Plugin\MessageDestination\DrupalQueue;
use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageGenerator;
use Drupal\entity_change_notifier\Plugin\MessageDestination\NotifyException;
use Drupal\node\NodeInterface;

/**
 * Test sending notifications to the Drupal queue.
 *
 * This test is a kernel test as generating an entity URI requires some painful
 * mocking.
 *
 * @group entity_change_notifier
 *
 * @coversDefaultClass Drupal\entity_change_notifier\Plugin\MessageDestination\DrupalQueue
 */
class DrupalQueueTest extends MessageDestinationTestBase {

  /**
   * Test notifying to a Drupal queue.
   *
   * This test creates an entity, and then validates that the appropriate Queue
   * API calls are made to send the notification. It also validates the
   * structure of the notification body.
   *
   * @covers ::__construct
   * @covers ::notify
   * @covers ::notifyDirect
   */
  public function testNotify() {
    $node = $this->createNode();

    // Generate the data we expect to be inserted into the queue.
    $data = $this->notificationData($node);

    // Assume a success case by returning a queue ID.
    /** @var \Drupal\Core\Queue\QueueInterface|\PHPUnit_Framework_MockObject_MockObject $queue */
    $queue = $this->getMockBuilder(QueueInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $expectedQueueItemId = rand();
    $queue->expects($this->once())->method('createItem')
      ->with($data)
      ->willReturn($expectedQueueItemId);

    /** @var \Drupal\Core\Queue\QueueFactory|\PHPUnit_Framework_MockObject_MockObject $factory */
    $factory = $this->getMockBuilder(QueueFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $factory->expects($this->once())
      ->method('get')
      ->willReturn($queue);

    $generator = new MessageGenerator($this->container->get('jsonapi.link_manager'));
    $destination = new DrupalQueue(['queue_name' => 'example_queue'], 'drupal_queue', [], $generator, $factory);

    // The once() calls above assert the appropriate methods are called.
    $queueItemId = $destination->notify(MessageDestinationInterface::ENTITY_INSERT, $node);
    $this->assertEquals($expectedQueueItemId, $queueItemId);
  }

  /**
   * Test that an exception is thrown if the queue fails.
   *
   * @covers ::notify
   * @covers ::notifyDirect
   */
  public function testNotifyFailure() {
    $node = $this->createNode();

    // Generate the data we expect to be inserted into the queue.
    $data = $this->notificationData($node);

    // Assume a failure when the create call returns FALSE.
    /** @var \Drupal\Core\Queue\QueueInterface|\PHPUnit_Framework_MockObject_MockObject $queue */
    $queue = $this->getMockBuilder(QueueInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $queue->expects($this->once())->method('createItem')
      ->with($data)
      ->willReturn(FALSE);

    /** @var \Drupal\Core\Queue\QueueFactory|\PHPUnit_Framework_MockObject_MockObject $factory */
    $factory = $this->getMockBuilder(QueueFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $factory->expects($this->once())
      ->method('get')
      ->willReturn($queue);

    $generator = new MessageGenerator($this->container->get('jsonapi.link_manager'));
    $destination = new DrupalQueue(['queue_name' => 'example_queue'], 'drupal_queue', [], $generator, $factory);

    // The once() calls above assert the appropriate methods are called.
    $this->setExpectedException(NotifyException::class, 'Unable to create the queue item.');
    $destination->notify(MessageDestinationInterface::ENTITY_INSERT, $node);
  }

  /**
   * Test when queue creation throws an exception.
   *
   * @covers ::notify
   * @covers ::notifyDirect
   */
  public function testNotifyCreateQueueException() {
    $node = $this->createNode();

    // Generate the data we expect to be inserted into the queue.
    $data = $this->notificationData($node);

    /** @var \Drupal\Core\Queue\QueueInterface|\PHPUnit_Framework_MockObject_MockObject $queue */
    $queue = $this->getMockBuilder(QueueInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $queue->expects($this->once())->method('createItem')
      ->with($data)
      ->willThrowException(new \Exception('This is a bad queue backend not properly returning FALSE'));

    /** @var \Drupal\Core\Queue\QueueFactory|\PHPUnit_Framework_MockObject_MockObject $factory */
    $factory = $this->getMockBuilder(QueueFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $factory->expects($this->once())
      ->method('get')
      ->willReturn($queue);

    $generator = new MessageGenerator($this->container->get('jsonapi.link_manager'));
    $destination = new DrupalQueue(['queue_name' => 'example_queue'], 'drupal_queue', [], $generator, $factory);

    // The once() calls above assert the appropriate methods are called.
    $this->setExpectedException(NotifyException::class, 'An unexpected exception occurred creating the queue item.');
    $destination->notify(MessageDestinationInterface::ENTITY_INSERT, $node);
  }

  /**
   * Test when a misbehaving queue throws an exception creating an item.
   *
   * @covers ::notify
   * @covers ::notifyDirect
   */
  public function testNotifyCreateItemException() {
    $node = $this->createNode();

    /** @var \Drupal\Core\Queue\QueueInterface|\PHPUnit_Framework_MockObject_MockObject $queue */
    $queue = $this->getMockBuilder(QueueInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $queue->expects($this->once())->method('createQueue')
      ->willThrowException(new \Exception('Unable to create the queue'));

    /** @var \Drupal\Core\Queue\QueueFactory|\PHPUnit_Framework_MockObject_MockObject $factory */
    $factory = $this->getMockBuilder(QueueFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $factory->expects($this->once())
      ->method('get')
      ->willReturn($queue);

    $generator = new MessageGenerator($this->container->get('jsonapi.link_manager'));
    $destination = new DrupalQueue(['queue_name' => 'example_queue'], 'drupal_queue', [], $generator, $factory);

    // The once() calls above assert the appropriate methods are called.
    $this->setExpectedException(NotifyException::class, 'An unexpected exception occurred creating the queue.');
    $destination->notify(MessageDestinationInterface::ENTITY_INSERT, $node);
  }

  /**
   * Generate notification data for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to generate a notification for.
   *
   * @return array
   *   The notification data.
   */
  private function notificationData(NodeInterface $node) {
    $data = [
      'action' => MessageDestinationInterface::ENTITY_INSERT,
      'uri' => 'http://localhost/jsonapi/node/ecn_test/' . $node->uuid(),
      'entity_id' => $node->id(),
      'entity_uuid' => $node->uuid(),
      'entity_type' => $node->getEntityTypeId(),
      'bundle' => $node->bundle(),
    ];
    return $data;
  }

}
