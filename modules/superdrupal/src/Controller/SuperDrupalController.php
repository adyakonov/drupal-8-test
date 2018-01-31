<?php

namespace Drupal\superdrupal\Controller;

/**
 * Class SuperDrupalController.
 *
 * @package Drupal\superdrupal\Controller
 */
class SuperDrupalController extends SuperDrupalControllerBase {

  /**
   * {@inheritdoc}
   */
  public function getTemplateData() {
    return $this->t('So Long, and Thanks for All the Fish');
  }

}
