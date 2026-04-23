<?php

namespace Drupal\mz_sms_notification\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\mz_sms_notification\Service\SmsDispatcher;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Re-dispatch a previously-saved sms node through the active gateway.
 */
class ResendController extends ControllerBase {

  public function __construct(protected SmsDispatcher $dispatcher) {}

  public static function create(ContainerInterface $container) {
    return new static($container->get('mz_sms_notification.dispatcher'));
  }

  public function resend(int $nid) : RedirectResponse {
    $node = Node::load($nid);
    if (!$node || $node->bundle() !== 'sms') {
      $this->messenger()->addError($this->t('SMS node @id not found.', ['@id' => $nid]));
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    $result = $this->dispatcher->dispatchNode($node);
    if ($result->success) {
      $this->messenger()->addStatus($this->t('Resent sms @id: @m', [
        '@id' => $nid,
        '@m'  => $result->message,
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Resend failed for sms @id: @m', [
        '@id' => $nid,
        '@m'  => $result->message,
      ]));
    }

    return new RedirectResponse(Url::fromRoute('mz_sms_notification.settings')->toString());
  }

}
