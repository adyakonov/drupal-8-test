<?php

namespace Drupal\superdrupal\Entity;

use Drupal;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Super profile entity.
 *
 * @ingroup superdrupal
 *
 * @ContentEntityType(
 *   id = "super_profile",
 *   label = @Translation("Super profile"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\superdrupal\SuperProfileHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "default" = "Drupal\superdrupal\Form\SuperProfileForm",
 *     }
 *   },
 *   base_table = "super_profile",
 *   admin_permission = "administer",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *   },
 *   field_ui_base_route = "super_profile.settings"
 * )
 */
class SuperProfile extends ContentEntityBase {
  use EntityChangedTrait;
  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += array(
      'user_id' => \Drupal::currentUser()->id(),
    );
  }

  /**
   * Get SuperProfile entity by user ID.
   *
   * @param $uid
   *   User ID.
   *
   * @param null $default
   *   If entity is not found - return this value.
   *
   * @return SuperProfile Return SuperProfile entity object.
   * Return SuperProfile entity object.
   */
  public static function getByUid($uid, $default = null) {
    $entity_ids = Drupal::entityQuery('super_profile')
      ->condition('user_id', $uid)
      ->execute();

    if (!empty($entity_ids)) {
      $entity_id = reset($entity_ids);
      $default = self::load($entity_id);
    }

    return $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Super profile entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Super profile entity.'))
      ->setReadOnly(TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Super profile entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\node\Entity\Node::getCurrentUserId')
      ->setTranslatable(TRUE);

    $fields['full_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Full name'))
      ->setDescription(t('The profile full name.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => 1,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => 1,
      ));

    $fields['role'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Role'))
      ->setDescription(t('The user role.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => 2,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => 2,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['birth_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Date of Birth'))
      ->setDescription(t('The user date of birth.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', array(
        'weight' => 4,
      ))
      ->setDisplayOptions('view', array(
        'weight' => 4,
      ));;

    $fields['sex'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Sex'))
      ->setDescription(t('The gender of the user.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setSettings(array(
        'allowed_values' => array(
          'female' => t('Female'),
          'male' => t('Male'),
        ),
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => 3,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'options_select',
        'weight' => 3,
      ));

    $fields['notes'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Notes'))
      ->setDescription(t('The profile notes.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', array(
        'type' => 'textarea',
        'weight' => 5,
        'settings' => array(
          'rows' => 4,
        ),
      ))->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => 5,
      ));

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code for the Super profile entity.'))
      ->setDisplayOptions('form', array(
        'type' => 'language_select',
        'weight' => 10,
      ))
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}

