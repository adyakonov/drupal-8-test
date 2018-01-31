<?php

namespace Drupal\druqs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;

/**
 * Provides route responses for druqs.module.
 */
class DruqsController extends ControllerBase {

  /**
   * Callback returning search results as JSON.
   */
  public function search() {

    $q = \Drupal::request()->request->get('query');

    // Get the config.
    $config = \Drupal::config('druqs.configuration');

    // Build args for the hook invocation.
    $args = [
      // Strip unnecessary whitespace from the search string.
      'input' => trim($q),
      // Keep track of how many.
      'results_current' => 0,
      // Add maximum amount of results per invocation.
      'results_per_source' => $config->get('results_per_source'),
      // Add maximum amount of results per invocation.
      'results_max' => $config->get('results_max'),
    ];

    // Invoke hook_druqs_search to allow modules to add their results.
    $output = array();
    if ($results = $this->moduleHandler()->invokeAll('druqs_search', array(&$args))) {
      foreach ($results as $result) {
        // Format and escape the actions.
        $actions = array();
        foreach ($result['actions'] as $title => $uri) {
          $actions[Html::escape($title)] = UrlHelper::stripDangerousProtocols($uri);
        }
        // Add formatted and escaped output.
        $output[] = array(
          'type' => Html::escape($result['type']),
          'title' => Html::escape($result['title']),
          'actions' => $actions
        );
      }
    }

    // Return these results.
    return new JsonResponse($output);
  }

}
