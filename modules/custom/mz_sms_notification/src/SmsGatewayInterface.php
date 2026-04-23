<?php

namespace Drupal\mz_sms_notification;

/**
 * Contract every SMS gateway must implement.
 *
 * Gateways are tagged with `mz_sms_notification.gateway` and registered on
 * the dispatcher via the `addGateway` service call.
 */
interface SmsGatewayInterface {

  /**
   * Short, stable identifier used in config (e.g. "orange_madagascar").
   */
  public function getId() : string;

  /**
   * Human-readable label for the admin UI.
   */
  public function getLabel() : string;

  /**
   * True when the gateway has the credentials it needs to operate.
   */
  public function isConfigured() : bool;

  /**
   * Attempt to deliver a message to a single E.164 MSISDN.
   *
   * @param string $to
   *   Recipient phone number in E.164 form (e.g. +261340000000).
   * @param string $message
   *   UTF-8 message body.
   * @param array $options
   *   Optional: 'sender_address', 'sender_name', 'client_correlator'.
   */
  public function send(string $to, string $message, array $options = []) : SmsGatewayResult;

}
