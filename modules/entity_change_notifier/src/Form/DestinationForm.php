<?php

namespace Drupal\entity_change_notifier\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Url;
use Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DestinationForm.
 */
class DestinationForm extends EntityForm {

  /**
   * The plugin manager used to discover available message destinations.
   *
   * @var \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationPluginManager
   */
  protected $destinationPluginManager;

  /**
   * The formatter used to render dates and times.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a DestinationForm object.
   *
   * @param \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationPluginManager $destination_plugin_manager
   *   The plugin manager used to discover available message destinations.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The formatter used to render dates and times.
   */
  public function __construct(MessageDestinationPluginManager $destination_plugin_manager, DateFormatterInterface $date_formatter) {
    $this->destinationPluginManager = $destination_plugin_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_change_notifier.message_destination_manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_change_notifier\Entity\DestinationInterface $destination */
    $destination = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $destination->label(),
      '#description' => $this->t("Label for the Destination."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $destination->id(),
      '#machine_name' => [
        'exists' => '\Drupal\entity_change_notifier\Entity\Destination::load',
      ],
      '#disabled' => !$destination->isNew(),
    ];

    $period_keys = [
      60,
      180,
      300,
      600,
      900,
      1800,
      2700,
      3600,
      10800,
      21600,
      32400,
      43200,
      86400,
    ];

    $period = array_map([$this->dateFormatter, 'formatInterval'], array_combine($period_keys, $period_keys));
    $form['message_expiry'] = [
      '#type' => 'select',
      '#title' => $this->t('Message expiration time'),
      '#default_value' => ($destination->getMessageExpiry() ? $destination->getMessageExpiry() : end($period_keys)),
      '#options' => $period,
      '#description' => $this->t('The maximum time a failed message will be retried for. After this time, the message will be logged and discarded.'),
    ];

    // We don't bother checking for no definitions as we ship with two
    // destinations out of the box.
    $options = [];
    $destinations = $this->destinationPluginManager->getDefinitions();
    foreach ($destinations as $destination_id => $definition) {
      $options[$destination_id] = $definition['label'];
    }

    // On a fresh form load, we are loading the plugin from the saved config
    // entity. For an #ajax callback, it comes from the form state.
    $message_destination_id = ($form_state->getValue('message_destination') ? $form_state->getValue('message_destination') : $destination->getMessageDestination());

    // When creating a new destination, default to the first found message
    // destination.
    if (!$message_destination_id) {
      $keys = array_keys($options);
      $message_destination_id = reset($keys);
    }

    $form['message_destination'] = [
      '#type' => 'select',
      '#title' => $this->t('Message destination'),
      '#description' => $this->t('The system to send notifications to.'),
      '#default_value' => $message_destination_id,
      '#options' => $options,
      '#ajax' => [
        'event' => 'change',
        'callback' => [$this, 'updateSelectedMessageDestination'],
      ],
    ];

    // The wrapper div for #ajax callbacks.
    $form['message_destination_settings'] = [
      '#prefix' => '<div id="entity-change-notifier-message-destination">',
      '#suffix' => '</div>',
    ];

    /** @var \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface $messageDestination */
    $messageDestination = $this->destinationPluginManager->createInstance($message_destination_id);

    // Only add message destination settings if they define a form.
    if ($messageDestination instanceof PluginFormInterface) {
      $messageDestination->setConfiguration($destination->getMessageDestinationSettings());
      $sub_form_state = SubformState::createForSubform($form['message_destination_settings'], $form, $form_state);
      $form['message_destination_settings'] = $messageDestination->buildConfigurationForm($form['message_destination_settings'], $sub_form_state);
      $form['message_destination_settings']['#tree'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $message_destination = $this->destinationPluginManager->createInstance($form_state->getValue('message_destination'));
    if ($message_destination instanceof PluginFormInterface) {
      $sub_form_state = SubformState::createForSubform($form['message_destination_settings'], $form, $form_state);
      $message_destination->validateConfigurationForm($form['message_destination_settings'], $sub_form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $form_state->cleanValues();

    /** @var \Drupal\entity_change_notifier\Entity\DestinationInterface $destination */
    $destination = $this->entity;

    /** @var \Drupal\entity_change_notifier\Plugin\MessageDestination\MessageDestinationInterface $current_destination */
    $current_destination = $destination->getPluginCollections()['message_destination_settings']->get($destination->getMessageDestination());
    $plugin_collection = $destination->getPluginCollections()['message_destination_settings'];
    if ($current_destination instanceof PluginFormInterface) {
      $sub_form_state = SubformState::createForSubform($form['message_destination_settings'], $form, $form_state);
      $current_destination->submitConfigurationForm($form['message_destination_settings'], $sub_form_state);
      $plugin_collection->setConfiguration($current_destination->getConfiguration());
    }
    else {
      // Make sure we clear out any stale settings.
      $plugin_collection->setConfiguration([]);
    }

    $status = $destination->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Destination. <a href="@publishers">Configure a publisher</a> to send entities to the destination.', [
          '%label' => $destination->label(),
          '@publishers' => Url::fromRoute('entity.ecn_publisher.collection')->toString(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Destination.', [
          '%label' => $destination->label(),
        ]));
    }
    $form_state->setRedirectUrl($destination->toUrl('collection'));
  }

  /**
   * Ajax callback to replace the message destination settings.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An #ajax command to alter the form.
   */
  public function updateSelectedMessageDestination(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $response->addCommand(new ReplaceCommand(
      '#entity-change-notifier-message-destination',
      $form['message_destination_settings']
    ));

    return $response;
  }

}
