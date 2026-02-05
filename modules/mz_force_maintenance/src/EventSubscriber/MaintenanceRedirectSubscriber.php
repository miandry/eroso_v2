<?php

namespace Drupal\mz_force_maintenance\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Routing\AdminContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

class MaintenanceRedirectSubscriber implements EventSubscriberInterface {

  protected $state;
  protected $adminContext;
  protected $routeMatch;
  protected $urlGenerator;

  public function __construct(StateInterface $state, AdminContext $adminContext, $route_match, $url_generator) {
    $this->state = $state;
    $this->adminContext = $adminContext;
    $this->routeMatch = $route_match;
    $this->urlGenerator = $url_generator;
  }

  public function onKernelRequest(RequestEvent $event) {
    if ($this->state->get('system.maintenance_mode')) {
      $route_name = $this->routeMatch->getRouteName();

      if (!in_array($route_name, ['user.login', 'mz_force_maintenance.custom_maintenance'])) {
        if (!$this->adminContext->isAdminRoute()) {
          $url = Url::fromRoute('mz_force_maintenance.custom_maintenance')->toString();
          $event->setResponse(new RedirectResponse($url));
        }
      }
    }
  }

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 31],
    ];
  }
}
