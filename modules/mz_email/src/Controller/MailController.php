<?php

namespace Drupal\mz_email\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
/**
 * Class ApiController.
 */
class MailController extends ControllerBase {

  public function _get_parameter($param = null)
  {
      $method = \Drupal::request()->getMethod();
      if ($param == null) {
          if ($method == "GET") {
              return \Drupal::request()->query->all();
          } elseif ($method == "POST") {
              return \Drupal::request()->request->all();
          } else {
              return null;
          }
      } else {
          if ($method == "GET") {
              return \Drupal::request()->query->get($param);
          } elseif ($method == "POST") {
              return \Drupal::request()->request->get($param);
          } else {
              return null;
          }
      }
  }
  /**
   * Paragraph_delete.
   *
   * @return string
   *   Return Hello string.
   */
  public function sendEmail(){
 
    
  $json = [];
  $method = \Drupal::request()->getMethod();
  $id = null;
  $params = $this->_get_parameter();
  $redirect = "/";
  if(!empty($params)){
            $mail_site = \Drupal::config('system.site')->get('mail');
            $email = isset($content["mail"])? $params["mail"]:$$mail_site  ;
            $service = \Drupal::service("mz_email.default");
            $subject = $params["subject"];
            $body =   $params['body'] ;
            $status = $service->sendMail($email, $subject , $body);
            if ( $status !== true) {
               \Drupal::messenger()->addMessage(t('There was a problem sending the welcome email to @email.', ['@email' => $to]), 'error');
            }
            if (isset($params['destination'])) {
              $redirect = $params['destination'];
            }
  } else {
    \Drupal::messenger()->addMessage('No POST request', 'error');       
  }
    return new RedirectResponse($redirect);
  }
}
