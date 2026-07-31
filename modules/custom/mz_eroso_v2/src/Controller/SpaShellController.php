<?php

namespace Drupal\mz_eroso_v2\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Serves the Vue SPA shell for client-side routes (history mode).
 */
class SpaShellController extends ControllerBase {

  /**
   * Renders an empty page; eroso_mobile page.html.twig provides #vue-app.
   */
  public function shell() {
    return [
      '#markup' => '',
    ];
  }

}
