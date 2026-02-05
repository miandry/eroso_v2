<?php

namespace Drupal\mz_force_maintenance\Controller;

use Drupal\Core\Controller\ControllerBase;

class MaintenanceController extends ControllerBase {
  public function content() {
    return [
      '#theme' => 'maintenance_page',
    ];
  }
}
