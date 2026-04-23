<?php

namespace Drupal\mz_sms_notification;

/**
 * Immutable value object returned by every gateway.
 */
final class SmsGatewayResult {

  /**
   * Whether the provider accepted the message for delivery.
   */
  public bool $success;

  /**
   * Short, human-readable message (English). Stored on the sms node.
   */
  public string $message;

  /**
   * Provider-side reference for the accepted message (when available).
   */
  public ?string $providerMessageId;

  /**
   * HTTP status returned by the provider (when applicable).
   */
  public ?int $httpStatus;

  /**
   * Raw response body, truncated to a safe length for persistence.
   */
  public ?string $raw;

  public function __construct(
    bool $success,
    string $message,
    ?string $providerMessageId = NULL,
    ?int $httpStatus = NULL,
    ?string $raw = NULL
  ) {
    $this->success = $success;
    $this->message = $message;
    $this->providerMessageId = $providerMessageId;
    $this->httpStatus = $httpStatus;
    $this->raw = $raw;
  }

  public static function ok(string $message, ?string $providerMessageId = NULL, ?int $httpStatus = NULL, ?string $raw = NULL) : self {
    return new self(TRUE, $message, $providerMessageId, $httpStatus, $raw);
  }

  public static function fail(string $message, ?int $httpStatus = NULL, ?string $raw = NULL) : self {
    return new self(FALSE, $message, NULL, $httpStatus, $raw);
  }

}
