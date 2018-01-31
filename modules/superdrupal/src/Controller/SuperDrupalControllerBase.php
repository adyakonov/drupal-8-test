<?php

namespace Drupal\superdrupal\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class SuperDrupalControllerBase.
 *
 * @package Drupal\superdrupal\Controller
 */
abstract class SuperDrupalControllerBase extends ControllerBase {

  /**
   * Controller content action.
   *
   * @return array
   *   Return renderable page array.
   */
  public function content() {
    return $this->getThemeArray();
  }

  /**
   * Return theme array.
   *
   * @return array
   *   Return renderable page array.
   */
  public function getThemeArray() {
    return array(
      '#theme' => 'superdrupal_' . $this->getThemeName(),
      '#data' => $this->getTemplateData(),
    );
  }

  /**
   * Return theme name.
   *
   * @return string
   *   Theme name.
   */
  public function getThemeName() {
    return 'base';
  }

  /**
   * Return template theme data.
   *
   * @return mixed
   *   Can be single variable, array of collection.
   */
  public function getTemplateData() {
    return null;
  }

}
