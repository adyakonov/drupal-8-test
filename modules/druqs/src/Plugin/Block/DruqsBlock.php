<?php

namespace Drupal\druqs\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides the druqs search field as a block
 *
 * @Block(
 *   id = "druqs",
 *   admin_label = @Translation("Druqs"),
 *   category = @Translation("Administration"),
 * )
 */
class DruqsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $user = \Drupal::currentUser();
    if ($user->hasPermission('access druqs')) {
      $build = [
        'tab' => [
          '#type' => 'search',
          '#attributes' => [
            'id' => 'druqs-input',
            'placeholder' => t('Quick search'),
          ],
          '#suffix' => '<div id="druqs-results"></div>',
        ],
        '#wrapper_attributes' => [
          'class' => ['druqs-tab'],
        ],
        '#attached' => [
          'library' => ['druqs/drupal.druqs'],
        ],
      ];
    }

    return $build;
  }

}
