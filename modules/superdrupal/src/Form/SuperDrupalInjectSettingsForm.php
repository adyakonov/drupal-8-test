<?php

namespace Drupal\superdrupal\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SuperDrupalInjectSettingsForm.
 *
 * @package Drupal\superdrupal\Form
 *
 * @ingroup superdrupal
 */
class SuperDrupalInjectSettingsForm extends ConfigFormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'SuperDrupalInject_settings';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('superdrupalinject.settings');

	  $form['superdrupalinject_css'] = [
		  '#type' => 'textfield',
		  '#title' => $this->t('CSS URL'),
		  '#description' => $this->t('Enter the CSS URL'),
		  '#default_value' => $config->get('superdrupalinject_css', 1),
	  ];

	  $form['superdrupalinject_js'] = [
		  '#type' => 'textfield',
		  '#title' => $this->t('JS URL'),
		  '#description' => $this->t('Enter the JS URL'),
		  '#default_value' => $config->get('superdrupalinject_js', 1),
	  ];

	  $form['superdrupalinject_description'] = [
		  '#type' => 'textarea',
		  '#title' => $this->t('Description'),
		  '#description' => $this->t('Enter the description here'),
		  '#default_value' => $config->get('superdrupalinject_description', 1),
	  ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('superdrupalinject.settings')
      ->set('superdrupalinject_css', $form_state->getValue('superdrupalinject_css'))
      ->set('superdrupalinject_js', $form_state->getValue('superdrupalinject_js'))
      ->set('superdrupalinject_description', $form_state->getValue('superdrupalinject_description'))
      ->save();

    parent::submitForm($form, $form_state);

  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'superdrupalinject.settings',
    ];
  }
}
