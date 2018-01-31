<?php

namespace Drupal\entity_change_notifier\Plugin\MessageDestination;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A DrupalLogger destination.
 *
 * This class supports any logger backend, such as the default Drupal database
 * log or Monolog. The plugin allows the channel and priority to be configured
 * as well.
 *
 * @MessageDestination(
 *   id = "drupal_logger",
 *   label = @Translation("Logger"),
 * )
 */
class DrupalLogger extends PluginBase implements MessageDestinationInterface, ContainerFactoryPluginInterface, PluginFormInterface {

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The system logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The class used to generate the message body.
   *
   * @var \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageGenerator
   */
  protected $generator;

  /**
   * The destination config entity ID, if one was used for this instance.
   *
   * @var string
   */
  protected $destinationEntityId;

  /**
   * Constructs a DrupalQueue object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance
   *   containing:
   *     - queue_id: The unique name of the queue.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageGenerator $generator
   *   The class used to generate the message body.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory used to retrieve the configured logger channel from.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessageGenerator $generator, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->logger = $logger_factory->get($this->getConfiguration()['channel']);
    $this->generator = $generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      new MessageGenerator($container->get('jsonapi.link_manager')),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'channel' => 'entity_change_notifier',
      'level' => RfcLogLevel::DEBUG,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    // This plugin has no additional dependencies.
  }

  /**
   * {@inheritdoc}
   */
  public function notify($action, EntityInterface $entity) {
    // @todo We should create an interface, and then have a LogMessage generator.
    $data = $this->generator->createMessage($action, $entity);
    foreach ($data as $key => $value) {
      $data['%' . $key] = $value;
    }

    if ($action != MessageDestinationInterface::ENTITY_DELETE) {
      $data['link'] = $this->t('<a href="@link">View</a>', ['@link' => $data['%uri']]);
    }

    $this->notifyDirect($data);
  }

  /**
   * {@inheritdoc}
   */
  public function notifyDirect(array $data) {
    try {
      $this->logger->log($this->configuration['level'], 'Entity %action on %entity_type %bundle %entity_id.', $data);
    }
    catch (\Exception $e) {
      throw new NotifyException($data, $e, $this->destinationEntityId, 'Unable to log the notification.', $e->getCode());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['note'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('All publishing actions are logged by default. In general, sites will not need to use this plugin except for testing the Entity Change Notifier module itself.'),
      '#suffix' => '</p>',
    ];

    $form['channel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Logger channel'),
      '#description' => $this->t('The channel to log notifications to. Can be any string, but machine names like %module are recommended.', ['%module' => 'entity_change_notifier']),
      '#default_value' => $this->getConfiguration()['channel'],
    ];

    $form['level'] = [
      '#type' => 'select',
      '#title' => $this->t('Log level'),
      '#description' => $this->t('The level to log notifications as.'),
      '#options' => RfcLogLevel::getLevels(),
      '#default_value' => $this->getConfiguration()['level'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // No additional validation is required.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration = $form_state->getValues();
  }

  /**
   * {@inheritdoc}
   */
  public function setDestinationConfigurationEntity($entity_id) {
    $this->destinationEntityId = $entity_id;
  }

}
