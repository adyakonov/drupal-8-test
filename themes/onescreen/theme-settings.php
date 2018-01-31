<?php

/**
 * @file
 * Theme setting callbacks for the Adminimal theme.
 */

/**
 * Implements hook_form_FORM_ID_alter().
 */
function onescreen_form_system_theme_settings_alter(&$form, &$form_state) {

  $form['onescreen_settings'] = [
    '#type' => 'fieldset',
    '#title' => t('onescreen Settings'),
    '#collapsible' => FALSE,
    '#collapsed' => FALSE,
  ];
  $form['onescreen_settings']['show_breadcrumbs'] = [
    '#type' => 'checkbox',
    '#title' => t('Show breadcrumbs in a page'),
    '#default_value' => theme_get_setting('show_breadcrumbs', 'onescreen'),
    '#description'   => t("Check this option to show breadcrumbs in page. Uncheck to hide."),
  ];
  $form['onescreen_settings']['slideshow'] = [
    '#type' => 'fieldset',
    '#title' => t('Front Page Slideshow'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  ];
  $form['onescreen_settings']['slideshow']['slideshow_display'] = [
    '#type' => 'checkbox',
    '#title' => t('Show slideshow'),
    '#default_value' => theme_get_setting('slideshow_display', 'onescreen'),
    '#description'   => t("Check this option to show Slideshow in front page. Uncheck to hide."),
  ];
  $form['onescreen_settings']['slideshow']['slide'] = [
    '#markup' => t('You can change the description and URL of each slide in the following Slide Setting fieldsets.'),
  ];
  $form['onescreen_settings']['slideshow']['slide1'] = [
    '#type' => 'fieldset',
    '#title' => t('Slide 1'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  ];
  $form['onescreen_settings']['slideshow']['slide1']['slide1_head'] = [
    '#type' => 'textfield',
    '#title' => t('Slide Headline'),
    '#default_value' => theme_get_setting('slide1_head', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slide1']['slide1_desc'] = [
    '#type' => 'textarea',
    '#title' => t('Slide Description'),
    '#default_value' => theme_get_setting('slide1_desc', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slide1']['slide1_url'] = [
    '#type' => 'textfield',
    '#title' => t('Slide URL'),
    '#default_value' => theme_get_setting('slide1_url', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slide1']['slide1_img'] = [
    '#type' => 'textfield',
    '#title' => t('Slide Img'),
    '#default_value' => theme_get_setting('slide1_img', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slide2'] = [
    '#type' => 'fieldset',
    '#title' => t('Slide 2'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  ];
  $form['onescreen_settings']['slideshow']['slide2']['slide2_head'] = [
    '#type' => 'textfield',
    '#title' => t('Slide Headline'),
    '#default_value' => theme_get_setting('slide2_head', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slide2']['slide2_desc'] = [
    '#type' => 'textarea',
    '#title' => t('Slide Description'),
    '#default_value' => theme_get_setting('slide2_desc', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slide2']['slide2_url'] = [
    '#type' => 'textfield',
    '#title' => t('Slide URL'),
    '#default_value' => theme_get_setting('slide2_url', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slide2']['slide2_img'] = [
    '#type' => 'textfield',
    '#title' => t('Slide Img'),
    '#default_value' => theme_get_setting('slide1_img', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slide3'] = [
    '#type' => 'fieldset',
    '#title' => t('Slide 3'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  ];
  $form['onescreen_settings']['slideshow']['slide3']['slide3_head'] = [
    '#type' => 'textfield',
    '#title' => t('Slide Headline'),
    '#default_value' => theme_get_setting('slide3_head', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slide3']['slide3_desc'] = [
    '#type' => 'textarea',
    '#title' => t('Slide Description'),
    '#default_value' => theme_get_setting('slide3_desc', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slide3']['slide3_url'] = [
    '#type' => 'textfield',
    '#title' => t('Slide URL'),
    '#default_value' => theme_get_setting('slide3_url', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slide3']['slide3_img'] = [
    '#type' => 'textfield',
    '#title' => t('Slide Img'),
    '#default_value' => theme_get_setting('slide1_img', 'onescreen'),
  ];
  $form['onescreen_settings']['slideshow']['slideimage'] = [
    '#markup' => t('To change the Slide Images, Replace the slide-image-1.jpg, slide-image-2.jpg and slide-image-3.jpg in the images folder of the theme folder.'),
  ];
}
