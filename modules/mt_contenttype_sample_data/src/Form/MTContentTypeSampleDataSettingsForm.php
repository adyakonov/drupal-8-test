<?php
namespace Drupal\mt_contenttype_sample_data\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

/**
 * Class MTContentTypeSampleDataSettingsForm.
 *
 * @package Drupal\mt_contenttype_sample_data\Form
 *
 * @ingroup mt_contenttype_sample_data
 */
class MTContentTypeSampleDataSettingsForm extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'MTContentTypeSampleData_settings';
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

    $validators = [
      'file_validate_extensions' => ['json'],
    ];

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Choose JSON File for Import'),
      '#upload_validators' => $validators,
      '#upload_location' => 'public://MTSampleData/',
      '#description' => $this->t('upload file'),
      '#states' => [
        'visible' => [
          ':input[name="File_type"]' => ['value' => $this->t('Upload Your File')],
        ],
      ],
    ];

    $form['import_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#submit' => ['::sampleDataImportCallback'],
    ];

    $form['file_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File name for Export'),
      '#default_value' => 'mt_simple_data',
      '#size' => 30,
      '#maxlength' => 128,
      '#required' => FALSE,
    ];

    $form['actions']['export_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export'),
      '#submit' => ['::sampleDataExportCallback'],
    ];

    return $form;
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
  }

  /**
   * Export handler.
   */
  public function sampleDataExportCallback(array &$form, FormStateInterface $form_state) {
    $serializer = \Drupal::service('serializer');
    $nids = \Drupal::entityQuery('node')->condition('type', 'mt_simple_product')->execute();
    if ($nids) {
      $nodes = Node::loadMultiple($nids);
      $data = $serializer->serialize($nodes, 'json', ['plugin_id' => 'entity']);

      // Create a file.
      $filename = $form_state->getValue('file_name') . '.json';
      file_save_data($data, "public://MTSampleData/" . $filename, FILE_EXISTS_REPLACE);
      drupal_set_message($this->t('The data was successfully saved in the') . ' ' . $filename . ' ' . $this->t('file'), 'status');
    }
    else {
      drupal_set_message($this->t('There is not data for Export'), 'status');
    }
  }

  /**
   * Import handler.
   */
  public function sampleDataImportCallback(array &$form, FormStateInterface $form_state) {
    $file = \Drupal::entityTypeManager()->getStorage('file')->load(reset($form_state->getValue('file')));
    $data = file_get_contents($file->getFileUri());

    $json_sample_product = json_decode($data, TRUE);

    // Create node from json.
    foreach ($json_sample_product as $sample_product) {
      $node = Node::create([
        'type' => $sample_product['type'][0]['target_id'],
        'langcode' => $sample_product['langcode'],
        'uid' => $sample_product['uid'],
        'status' => $sample_product['status'],
        'title' => $sample_product['title'],
        'body' => $sample_product['body'],
        'field_simple_price' => $sample_product['field_simple_price'],
      ]);

      // Get file name.
      $file_name = basename($sample_product['field_simple_image'][0]['url']);

      $module_path = drupal_get_path('module', 'mt_contenttype_sample_data');
      $data = file_get_contents($module_path . "/img/" . $file_name);
      $file = file_save_data($data, "public://MTSampleData/" . $file_name, FILE_EXISTS_REPLACE);

      $node->set('field_simple_image', [
        'target_id' => $file->id(),
        'alt' => $sample_product['field_simple_image'][0]['alt'],
        'title' => $sample_product['field_simple_image'][0]['title'],
      ]);

      $node->enforceIsNew();
      $node->save();
    }
    drupal_set_message($this->t('The data was successfully imported'), 'status');

  }

}
