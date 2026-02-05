<?php

namespace Drupal\mailersend_mailer_simple\Service;

class MailerSendService {

  protected $apiKey;

  public function __construct() {
    $this->apiKey = 'mlsn.6f7721d34c355bed5e93c51d7fea31cdb4319a67daf8cff5d9db9de7f26d3537';
  }

  public function send($to, $subject, $message, $from = 'no-reply@yourdomain.com') {
    $url = 'https://api.mailersend.com/v1/email';

    $payload = json_encode([
      'from' => ['email' => $from],
      'to' => [['email' => $to]],
      'subject' => $subject,
      'text' => $message,
    ]);

    $headers = [
      'Authorization: Bearer ' . $this->apiKey,
      'Content-Type: application/json',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
      \Drupal::logger('mailersend_mailer_simple')->error(curl_error($ch));
      curl_close($ch);
      return false;
    }

    curl_close($ch);
    return $httpCode === 202;
  }
}
