<?php

namespace Drupal\entity_change_notifier\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Defines the Destination entity.
 *
 * @ConfigEntityType(
 *   id = "ecn_destination",
 *   label = @Translation("Destination"),
 *   label_singular = @Translation("destination"),
 *   label_plural = @Translation("destinations"),
 *   label_count = @PluralTranslation(
 *     singular = "@count destination",
 *     plural = "@count destinations",
 *   ),
 *   label_collection = @Translation("Destinations"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\entity_change_notifier\DestinationListBuilder",
 *     "form" = {
 *       "add" = "Drupal\entity_change_notifier\Form\DestinationForm",
 *       "edit" = "Drupal\entity_change_notifier\Form\DestinationForm",
 *       "delete" = "Drupal\entity_change_notifier\Form\DestinationDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "ecn_destination",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/services/entity-change-notifier/destination/{ecn_destination}",
 *     "add-form" = "/admin/config/services/entity-change-notifier/destination/add",
 *     "edit-form" = "/admin/config/services/entity-change-notifier/destination/{ecn_destination}/edit",
 *     "delete-form" = "/admin/config/services/entity-change-notifier/destination/{ecn_destination}/delete",
 *     "collection" = "/admin/config/services/entity-change-notifier/destination"
 *   }
 * )
 */
class Destination extends ConfigEntityBase implements DestinationInterface {

  /**
   * The Destination ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Destination label.
   *
   * @var string
   */
  protected $label;

  /**
   * The unique plugin identifier of the message destination.
   *
   * @var string
   */
  protected $message_destination;

  /**
   * An array of settings for the message destination.
   *
   * @var array
   */
  protected $message_destination_settings = [];

  /**
   * The number of seconds a new message should be valid for.
   *
   * @var int
   */
  protected $message_expiry;

  /**
   * The plugins this config entity is dependent on.
   *
   * This is used to allow plugins to declare config dependencies.
   *
   * @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   */
  private $messageDestinationPluginCollection;

  /**
   * {@inheritdoc}
   */
  public function getMessageDestination() {
    return $this->message_destination;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessageDestinationSettings() {
    return $this->message_destination_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessageDestinationSettings(array $message_destination_settings) {
    $this->message_destination_settings = $message_destination_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    // When adding a new destination this property may not be set yet.
    if (!$this->message_destination) {
      return [
        'message_destination_settings' => [],
      ];
    }

    if (!isset($this->messageDestinationPluginCollection)) {
      $this->messageDestinationPluginCollection = new DefaultSingleLazyPluginCollection(\Drupal::service('entity_change_notifier.message_destination_manager'), $this->getMessageDestination(), $this->getMessageDestinationSettings());
    }
    return [
      'message_destination_settings' => $this->messageDestinationPluginCollection,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setMessageExpiry($seconds) {
    $this->message_expiry = $seconds;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessageExpiry() {
    return $this->message_expiry;
  }

}
