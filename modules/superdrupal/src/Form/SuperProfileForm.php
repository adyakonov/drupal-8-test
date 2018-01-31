<?php

namespace Drupal\superdrupal\Form;

use Drupal;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\superdrupal\Entity\SuperProfile;

/**
 * Form controller for Super profile edit forms.
 *
 * @ingroup superdrupal
 */
class SuperProfileForm extends ContentEntityForm {
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->entity = SuperProfile::getByUid(Drupal::currentUser()->id(), $this->entity);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Super profile.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Super profile.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('superdrupal.profile');
  }

}
