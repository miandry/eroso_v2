<?php

namespace Drupal\mz_checkout_stripe;


use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class DefaultService.
 */
class DefaultService {

  private $stripe ;
  private $currency ;
  private $accountId ;

  /**
   * Constructs a new DefaultService object.
   */
  public function __construct() {
    $config = \Drupal::config('stripe.settings');
    $this->accountId = $config->get('account_id');

  }
   
  public function checkoutExecute($id,$price,$product,$currency = 'usd'){
    $config = \Drupal::config('stripe.settings');
    $apikeySecret = $config->get('apikey.' . $config->get('environment') . '.secret');
    \Stripe\Stripe::setApiKey($apikeySecret);

    try {
      $host = \Drupal::request()->getSchemeAndHttpHost();
      $url_success =  $host. $config->get('success_url');
      $url_cancel =   $host. $config->get('cancel_url');
      // Create a new Stripe Checkout Session
      $checkout_session = \Stripe\Checkout\Session::create([
          'payment_method_types' => ['card'], // Accept credit cards
          'line_items' => [[
              'price_data' => [
                  'currency' => $currency, // Specify currency
                  'product_data' => [
                      'name' =>   $product, // Product name
                  ],
                  'unit_amount' => $price, // Amount in cents ($20.00)
              ],
              'quantity' => 1, // Product quantity
          ]],
          'automatic_tax' => ['enabled' => false],
          'mode' => 'payment', // Payment mode
          'success_url' =>   $url_success .'?id='. $id .'&session_id={CHECKOUT_SESSION_ID}',
          'cancel_url' => $url_cancel.'?id='. $id .'&session_id={CHECKOUT_SESSION_ID}',  
          'payment_intent_data' => [
            //'capture_method' => 'manual'
            // 'transfer_data' => [
            //   'destination' => $this->accountId ,
            // ]
          ],
      ]);
  
      // Return the checkout session ID as JSON
      $url  = $checkout_session->url ;
 
      $response = new RedirectResponse($url, 302);
      $response->send();
      return;
  
  } catch (Exception $e) {
      http_response_code(500);
      echo json_encode(['error' => $e->getMessage()]);
      die();
  }

  }

}
