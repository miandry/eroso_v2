<?php

namespace Drupal\mz_sms_notification\Gateway;

use Drupal\mz_sms_notification\SmsGatewayInterface;
use Drupal\mz_sms_notification\SmsGatewayResult;
use Psr\Log\LoggerInterface;

/**
 * Pretends to send a message — actually just writes it to the log channel.
 *
 * Perfect for development and for environments where you want to verify the
 * pipeline without spending real SMS credits.
 */
class LogOnlyGateway implements SmsGatewayInterface {

  public function __construct(protected LoggerInterface $logger) {}

  public function getId() : string {
    return 'log';
  }

  public function getLabel() : string {
    return 'Log only (no delivery)';
  }

  public function isConfigured() : bool {
    return TRUE;
  }

  public function send(string $to, string $message, array $options = []) : SmsGatewayResult {
    $this->logger->info('[SIMULATED SMS] to=@to length=@len body=@body', [
      '@to'   => $to,
      '@len'  => mb_strlen($message),
      '@body' => $message,
    ]);
    return SmsGatewayResult::ok(
      'Simulated delivery (log gateway).',
      'log-' . uniqid('', TRUE),
      200,
      NULL,
    );
  }

}
