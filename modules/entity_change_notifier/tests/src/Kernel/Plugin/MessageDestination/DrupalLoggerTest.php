<?php

namespace Drupal\Tests\entity_change_notifier\Kernel\Plugin\MessageDestination;

use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\entity_change_notifier\Plugin\MessageDestination\DrupalLogger;
use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface;
use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageGenerator;
use Drupal\entity_change_notifier\Plugin\MessageDestination\NotifyException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\BufferingLogger;

/**
 * Test sending notifications to the Drupal log.
 *
 * This test is a kernel test as generating an entity URI requires some painful
 * mocking.
 *
 * @group entity_change_notifier
 *
 * @coversDefaultClass Drupal\entity_change_notifier\Plugin\MessageDestination\DrupalLogger
 */
class DrupalLoggerTest extends MessageDestinationTestBase {

  /**
   * Test notifying to a Drupal queue.
   *
   * This test creates an entity, and then validates that the appropriate Queue
   * API calls are made to send the notification. It also validates the
   * structure of the notification body.
   *
   * @covers ::__construct
   * @covers ::notify
   */
  public function testNotify() {
    $node = $this->createNode();

    $logger = new BufferingLogger();
    $configuration = [
      'channel' => 'entity_change_notifier',
      'level' => RfcLogLevel::INFO,
    ];
    $generator = new MessageGenerator($this->container->get('jsonapi.link_manager'));
    $channel = new LoggerChannelFactory();
    $channel->addLogger($logger);
    $destination = new DrupalLogger($configuration, 'drupal_logger', [], $generator, $channel);

    // The once() calls above assert the appropriate methods are called.
    $destination->notify(MessageDestinationInterface::ENTITY_INSERT, $node);
    $logs = $logger->cleanLogs();
    $this->assertCount(1, $logs);
    $subset = [
      [
        RfcLogLevel::INFO,
        'Entity %action on %entity_type %bundle %entity_id.',
        [
          '%action' => MessageDestinationInterface::ENTITY_INSERT,
          '%uri' => 'http://localhost/jsonapi/node/ecn_test/' . $node->uuid(),
          '%entity_id' => $node->id(),
          '%entity_type' => $node->getEntityTypeId(),
          '%bundle' => $node->bundle(),
        ],
      ],
    ];
    $this->assertArraySubset($subset, $logs);
    $this->assertEquals('<a href="http://localhost/jsonapi/node/ecn_test/' . $node->uuid() . '">View</a>', (string) $logs[0][2]['link']);
  }

  /**
   * Test that an exception is thrown if the log call fails.
   *
   * @covers ::notify
   */
  public function testNotifyFailure() {
    $node = $this->createNode();

    /** @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
    $logger = $this->getMockBuilder(LoggerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $logger->expects($this->once())->method('log')
      ->willThrowException(new \Exception('We failed hard.'));
    $channel = new LoggerChannelFactory();
    $channel->addLogger($logger);

    $generator = new MessageGenerator($this->container->get('jsonapi.link_manager'));
    $destination = new DrupalLogger(['level' => RfcLogLevel::INFO], 'drupal_logger', [], $generator, $channel);

    // The once() calls above assert the appropriate methods are called.
    $this->setExpectedException(NotifyException::class, 'Unable to log the notification.');
    $destination->notify(MessageDestinationInterface::ENTITY_INSERT, $node);
  }

  /**
   * Test getting the logger configuration.
   *
   * @covers ::create
   * @covers ::setConfiguration
   * @covers ::getConfiguration
   */
  public function testGetConfiguration() {
    $configuration = [
      'level' => RfcLogLevel::INFO,
      'channel' => 'example_channel',
    ];

    $destination = DrupalLogger::create($this->container, $configuration, 'drupal_logger', []);
    $this->assertEquals($configuration, $destination->getConfiguration());
  }

  /**
   * Test that the default configuration is merged correctly.
   *
   * @covers ::create
   * @covers ::setConfiguration
   * @covers ::getConfiguration
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfiguration() {
    $configuration = [
      'channel' => 'entity_change_notifier',
      'level' => RfcLogLevel::DEBUG,
    ];
    $destination = DrupalLogger::create($this->container, $configuration, 'drupal_logger', []);
    $destination->setConfiguration([]);
    $this->assertEquals($configuration, $destination->defaultConfiguration());
    $this->assertEquals($configuration, $destination->getConfiguration());
  }

  /**
   * Test that there is no View link when an entity is deleted.
   *
   * @covers ::notify
   */
  public function testNotifyDeleteLink() {
    $node = $this->createNode();

    $logger = new BufferingLogger();
    $configuration = [
      'channel' => 'entity_change_notifier',
      'level' => RfcLogLevel::INFO,
    ];
    $generator = new MessageGenerator($this->container->get('jsonapi.link_manager'));
    $channel = new LoggerChannelFactory();
    $channel->addLogger($logger);
    $destination = new DrupalLogger($configuration, 'drupal_logger', [], $generator, $channel);

    // The once() calls above assert the appropriate methods are called.
    $destination->notify(MessageDestinationInterface::ENTITY_DELETE, $node);
    $logs = $logger->cleanLogs();
    $this->assertCount(1, $logs);
    $subset = [
      [
        RfcLogLevel::INFO,
        'Entity %action on %entity_type %bundle %entity_id.',
        [
          '%action' => MessageDestinationInterface::ENTITY_DELETE,
          '%uri' => 'http://localhost/jsonapi/node/ecn_test/' . $node->uuid(),
          '%entity_id' => $node->id(),
          '%entity_type' => $node->getEntityTypeId(),
          '%bundle' => $node->bundle(),
        ],
      ],
    ];
    $this->assertArraySubset($subset, $logs);
    $this->assertEmpty($logs[0][2]['link']);
  }

}
