<?php

namespace Drupal\mz_sms_notification\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mz_sms_notification\SmsGatewayInterface;
use Drupal\mz_sms_notification\SmsGatewayResult;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Selects an SMS gateway and dispatches messages.
 *
 * Two entry points:
 *  - dispatchNode(sms $node) : picks phone/content from the node, sends,
 *    and writes back delivery status fields.
 *  - sendRaw($to, $message)  : send any ad-hoc message through the active
 *    gateway. Returns the raw SmsGatewayResult.
 */
class SmsDispatcher {

  const CONFIG_NAME = 'mz_sms_notification.settings';

  /** @var \Drupal\mz_sms_notification\SmsGatewayInterface[] Keyed by gateway id. */
  protected array $gateways = [];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
    protected TimeInterface $time
  ) {}

  /**
   * Register a gateway at build time (called from services.yml).
   */
  public function addGateway(SmsGatewayInterface $gateway) : void {
    $this->gateways[$gateway->getId()] = $gateway;
  }

  /**
   * @return array<string,string> ['id' => 'Label'] for all registered gateways.
   */
  public function listGateways() : array {
    $out = [];
    foreach ($this->gateways as $id => $gw) {
      $out[$id] = $gw->getLabel();
    }
    return $out;
  }

  /**
   * @return \Drupal\mz_sms_notification\SmsGatewayInterface[]
   */
  public function getGateways() : array {
    return $this->gateways;
  }

  public function getActiveGateway() : ?SmsGatewayInterface {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    $id     = (string) ($config->get('default_gateway') ?: 'log');
    return $this->gateways[$id] ?? NULL;
  }

  public function isEnabled() : bool {
    return (bool) $this->configFactory->get(self::CONFIG_NAME)->get('enabled');
  }

  /**
   * Send a raw message. Does NOT write any node — the caller owns persistence.
   */
  public function sendRaw(string $to, string $message, array $options = []) : SmsGatewayResult {
    if (!$this->isEnabled()) {
      return SmsGatewayResult::fail('SMS delivery is globally disabled in config.');
    }
    $gw = $this->getActiveGateway();
    if (!$gw) {
      return SmsGatewayResult::fail('No active SMS gateway configured.');
    }
    $to = $this->normalizeE164($to);
    if ($to === '') {
      return SmsGatewayResult::fail('Invalid recipient phone number.');
    }
    if (trim($message) === '') {
      return SmsGatewayResult::fail('Empty message body.');
    }
    return $gw->send($to, $message, $options);
  }

  /**
   * Dispatch a single `sms` node through the active gateway.
   */
  public function dispatchNode(NodeInterface $sms) : SmsGatewayResult {
    if ($sms->bundle() !== 'sms') {
      return SmsGatewayResult::fail('Not an sms node.');
    }

    $to      = $this->readField($sms, 'field_numero_destinataire');
    $content = $this->readContent($sms);

    $result = $this->sendRaw($to, $content, [
      'sender_address'    => $this->readField($sms, 'field_numero_de_l_expediteur'),
      'client_correlator' => 'sms-node-' . $sms->id(),
    ]);

    $this->applyResultToNode($sms, $result);
    return $result;
  }

  /**
   * Write tracking fields on an sms node and save it.
   */
  public function applyResultToNode(NodeInterface $sms, SmsGatewayResult $result) : void {
    $status = $result->success ? 'sent' : 'failed';

    if ($sms->hasField('field_delivery_status')) {
      $sms->set('field_delivery_status', $status);
    }
    if ($sms->hasField('field_delivery_response')) {
      $summary = $result->message;
      if ($result->raw) {
        $summary .= "\n\n---RAW---\n" . mb_substr($result->raw, 0, 4000);
      }
      $sms->set('field_delivery_response', mb_substr($summary, 0, 8000));
    }
    if ($sms->hasField('field_delivery_at')) {
      $sms->set('field_delivery_at', gmdate('Y-m-d\TH:i:s', $this->time->getCurrentTime()));
    }
    if ($sms->hasField('field_provider_message_id') && $result->providerMessageId) {
      $sms->set('field_provider_message_id', mb_substr($result->providerMessageId, 0, 255));
    }

    try {
      $sms->save();
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to persist delivery status on sms @id: @msg', [
        '@id'  => $sms->id(),
        '@msg' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Normalize a number to E.164. Uses the configured default country prefix
   * when the input doesn't start with '+'.
   */
  public function normalizeE164(string $value) : string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }
    if (stripos($value, 'tel:') === 0) {
      $value = substr($value, 4);
    }

    $hasPlus = strpos($value, '+') === 0;
    $digits  = preg_replace('/\D+/', '', $value);
    if ($digits === '') {
      return '';
    }
    if ($hasPlus) {
      return '+' . $digits;
    }
    $prefix = (string) $this->configFactory->get(self::CONFIG_NAME)->get('force_country_code');
    if ($prefix !== '' && strpos($prefix, '+') === 0 && strpos($digits, ltrim($prefix, '+')) !== 0) {
      return $prefix . ltrim($digits, '0');
    }
    return '+' . $digits;
  }

  protected function readField(NodeInterface $node, string $name) : string {
    if (!$node->hasField($name) || $node->get($name)->isEmpty()) {
      return '';
    }
    $first = $node->get($name)->first();
    if (!$first) {
      return '';
    }
    $val = $first->getValue();
    if (isset($val['value'])) {
      return (string) $val['value'];
    }
    if (isset($val['target_id'])) {
      return (string) $val['target_id'];
    }
    return '';
  }

  /**
   * Prefer field_content, fall back to body.value, then title.
   */
  protected function readContent(NodeInterface $node) : string {
    $content = $this->readField($node, 'field_content');
    if ($content !== '') {
      return $content;
    }
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $body = $node->get('body')->first();
      if ($body) {
        $val = $body->getValue();
        if (!empty($val['value'])) {
          return strip_tags((string) $val['value']);
        }
      }
    }
    return (string) $node->label();
  }

}
