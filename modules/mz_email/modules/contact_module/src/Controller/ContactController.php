<?php

namespace Drupal\contact_module\Controller;

use Drupal\Core\Controller\ControllerBase;

class ContactController extends ControllerBase {
  public function content() {
    return [
      '#markup' => $this->t('Hello from Contact Module!'),
    ];
  }
}
