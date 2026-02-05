<?php

namespace Drupal\mailersend_mailer\Service;

use GuzzleHttp\Client;

class MailerSendService {

  protected $apiKey;
  protected $client;

  public function __construct() {
    $this->apiKey = 'YOUR_MAILERSEND_API_KEY';
    $this->client = new Client([
      'base_uri' => 'https://api.mailersend.com/v1/',
      'headers' => [
        'Authorization' => 'Bearer ' . $this->apiKey,
        'Content-Type' => 'application/json',
      ],
    ]);
  }

  public function send($to, $subject, $message, $from = 'no-reply@yourdomain.com') {
    $payload = [
      'from' => ['email' => $from],
      'to' => [['email' => $to]],
      'subject' => $subject,
      'text' => $message,
    ];

    try {
      $response = $this->client->post('email', ['json' => $payload]);
      return $response->getStatusCode() === 202;
    } catch (\Exception $e) {
      \Drupal::logger('mailersend_mailer')->error($e->getMessage());
      return false;
    }
  }
}
