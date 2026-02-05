<?php

namespace Drupal\mz_checkout_stripe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
/**
 * Class DefaultController.
 */
class DefaultController extends ControllerBase {

  /**
   * Checkout.
   * /mz/api/checkout?id=1&price=50&product=shoot&currency=eur
   * @return string
   */
  
  public function checkout(Request $request) {
    $id = $request->query->get('id');
    $price = $request->query->get('price');
    $product = $request->query->get('product');
    $currency = $request->query->get('currency');
    $service = \Drupal::service('mz_checkout_stripe.default');
    $result = $service->checkoutExecute($id, $price,  $product,$currency);

    $path = '/stripe/error'.'/'.$id;
    $response = new RedirectResponse($path, 302);
    $response->send();
    return;

  }

}
