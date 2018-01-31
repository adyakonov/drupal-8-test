<?php

namespace Drupal\superdrupal\Controller;

use Drupal\superdrupal\Entity\SuperProfile;
use Drupal\user\Entity\User;
use Drupal;

/**
 * Class ProfileController.
 *
 * @package Drupal\superdrupal\Controller
 */
class ProfileController extends SuperDrupalControllerBase
{
  /**
   * Return theme array.
   *
   * @return array
   *   Return renderable page array.
   */
  public function getThemeArray() {
    /** @var User $user */
    $profile = SuperProfile::getByUid(Drupal::currentUser()->id());
    if (!$profile) {
      $profile = SuperProfile::create([
        'user_id' => Drupal::currentUser()->id(),
      ]);
    }
    $render = Drupal::service('entity_type.manager')->getViewBuilder($profile->getEntityTypeId())->view($profile);

    $render['#theme'] = 'superdrupal_profile';
    $render['#cache']['max-age'] = 0;

    return $render;
  }

}

