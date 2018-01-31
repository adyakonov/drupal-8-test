<?php

namespace Drupal\entity_change_notifier\Form;

use Drupal\Core\Entity\EntityDeleteForm;

/**
 * Builds the form to delete Destination entities.
 */
class DestinationDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ecn_destination_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Deleting a destination will delete all publishers using the destination. This action cannot be undone.');
  }

}
