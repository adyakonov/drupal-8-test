<?php

namespace Drupal\entity_change_notifier\Plugin\MessageDestination;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drupal Queue API destination.
 *
 * The class supports any queue backend, such as the database queue, RabbitMQ,
 * and more.
 *
 * @MessageDestination(
 *   id = "drupal_queue",
 *   label = @Translation("Drupal Queue"),
 * )
 */
class DrupalQueue extends PluginBase implements MessageDestinationInterface, ContainerFactoryPluginInterface, PluginFormInterface {

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The queue factory to send notifications to.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

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
   *     - queue_name: The unique name of the queue.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageGenerator $generator
   *   The class used to generate the message body.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory to find the queue to create notifications in.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessageGenerator $generator, QueueFactory $queueFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->generator = $generator;
    $this->queueFactory = $queueFactory;
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
      $container->get('queue')
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
      'queue_name' => 'entity_change_notifier',
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
    $data = $this->generator->createMessage($action, $entity);
    $itemId = $this->notifyDirect($data);
    return $itemId;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['queue_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Queue name'),
      '#description' => $this->t('The name of the queue to send notifications to. If it does not exist, it will be created when the first message is sent. It is your responsibility to ensure that the queue is consumed by some other system. To use an alternate queue backend such as RabbitMQ see instructions in the README file'),
      '#default_value' => $this->getConfiguration()['queue_name'],
      '#machine_name' => [
        'exists' => [$this, 'machineNameAlwaysExists'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // No additional validation required.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration = $form_state->getValues();
  }

  /**
   * Disable 'exists' check for queue machine names.
   *
   * This can't be a closure as forms are serialized during rebuilds.
   *
   * @param mixed $value
   *   The value being validated.
   * @param array $element
   *   The form element containing the value.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   *
   * @return bool
   *   Always returns FALSE.
   */
  public function machineNameAlwaysExists($value, array $element, FormStateInterface $formState) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function notifyDirect(array $data) {
    $queue = $this->queueFactory->get($this->getConfiguration()['queue_name']);
    try {
      $queue->createQueue();
    }
    catch (\Exception $e) {
      throw new NotifyException($data, $e, $this->destinationEntityId, 'An unexpected exception occurred creating the queue.', $e->getCode());
    }

    try {
      $itemId = $queue->createItem($data);
    }
    catch (\Exception $e) {
      // The Queue API requires createItem() never throw an exception. If that
      // happens, likely due to a bug in a queue implementation, we re-throw the
      // exception so callers can have the action and entity context for
      // debugging.
      throw new NotifyException($data, $e, $this->destinationEntityId, 'An unexpected exception occurred creating the queue item.', $e->getCode());
    }

    if (!$itemId) {
      throw new NotifyException($data, new \RuntimeException('The queue backend returned a FALSE item id.'), $this->destinationEntityId, 'Unable to create the queue item.');
    }
    return $itemId;
  }

  /**
   * {@inheritdoc}
   */
  public function setDestinationConfigurationEntity($entity_id) {
    $this->destinationEntityId = $entity_id;
  }

}
