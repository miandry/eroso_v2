<?php

namespace Drupal\mz_sms_notification\Gateway;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mz_sms_notification\SmsGatewayInterface;
use Drupal\mz_sms_notification\SmsGatewayResult;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Orange Madagascar — SMS API sample gateway.
 *
 * Uses the Orange Developer (Partner API) "SMS API":
 *   - OAuth2 client_credentials → POST {base_url}{token_path}
 *       Authorization: Basic base64(client_id:client_secret)
 *       Body: grant_type=client_credentials
 *   - Send SMS → POST {base_url}/smsmessaging/v1/outbound/{senderAddress}/requests
 *       Authorization: Bearer <access_token>
 *       Body:
 *         {
 *           "outboundSMSMessageRequest": {
 *             "address": ["tel:+261340000000"],
 *             "senderAddress": "tel:+261340000000",
 *             "outboundSMSTextMessage": {"message": "..."}
 *           }
 *         }
 *
 * References:
 *   - https://developer.orange.com/apis/sms-api
 *   - https://developer.orange.com/apis/authentication
 *
 * The token is cached in Drupal state until `expires_in` seconds before the
 * actual expiry, avoiding an OAuth round-trip for every message.
 */
class OrangeMadagascarGateway implements SmsGatewayInterface {

  const STATE_TOKEN      = 'mz_sms_notification.orange_madagascar.token';
  const STATE_TOKEN_EXPS = 'mz_sms_notification.orange_madagascar.token_expires_at';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ClientInterface $httpClient,
    protected StateInterface $state,
    protected LoggerInterface $logger,
    protected TimeInterface $time
  ) {}

  public function getId() : string {
    return 'orange_madagascar';
  }

  public function getLabel() : string {
    return 'Orange Madagascar';
  }

  public function isConfigured() : bool {
    $c = $this->settings();
    return !empty($c['base_url'])
      && !empty($c['client_id'])
      && !empty($c['client_secret'])
      && !empty($c['sender_address']);
  }

  public function send(string $to, string $message, array $options = []) : SmsGatewayResult {
    if (!$this->isConfigured()) {
      return SmsGatewayResult::fail('Orange Madagascar gateway is not fully configured.');
    }

    try {
      $token = $this->getAccessToken();
    }
    catch (\Throwable $e) {
      return SmsGatewayResult::fail('OAuth2 token error: ' . $e->getMessage());
    }

    $c             = $this->settings();
    $senderAddress = $options['sender_address'] ?? $c['sender_address'];
    $senderName    = $options['sender_name'] ?? ($c['sender_name'] ?? '');
    $to            = $this->normalizeAddress($to);
    $senderAddress = $this->normalizeAddress($senderAddress);

    if ($to === '') {
      return SmsGatewayResult::fail('Invalid recipient phone number.');
    }
    if ($senderAddress === '') {
      return SmsGatewayResult::fail('Invalid sender address.');
    }

    $smsPath = (string) $c['sms_path'];
    $smsPath = str_replace('{senderAddress}', rawurlencode($senderAddress), $smsPath);
    $url     = rtrim((string) $c['base_url'], '/') . $smsPath;

    $payload = [
      'outboundSMSMessageRequest' => [
        'address'                 => [$to],
        'senderAddress'           => $senderAddress,
        'outboundSMSTextMessage'  => [
          'message' => $message,
        ],
      ],
    ];
    if ($senderName !== '') {
      $payload['outboundSMSMessageRequest']['senderName'] = $senderName;
    }
    if (!empty($options['client_correlator'])) {
      $payload['outboundSMSMessageRequest']['clientCorrelator'] = (string) $options['client_correlator'];
    }

    $timeout = (int) ($c['request_timeout'] ?? 15);
    if ($timeout <= 0) {
      $timeout = 15;
    }

    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
          'Content-Type'  => 'application/json',
          'Accept'        => 'application/json',
        ],
        'json'            => $payload,
        'timeout'         => $timeout,
        'connect_timeout' => $timeout,
        'http_errors'     => FALSE,
      ]);
    }
    catch (GuzzleException $e) {
      return SmsGatewayResult::fail('HTTP error talking to Orange: ' . $e->getMessage());
    }

    $status = $response->getStatusCode();
    $body   = (string) $response->getBody();
    $raw    = mb_substr($body, 0, 4000);
    $json   = json_decode($body, TRUE);

    if ($status >= 200 && $status < 300) {
      $providerRef = NULL;
      if (is_array($json)) {
        $providerRef = $this->extractResourceUrl($json) ?? ($payload['outboundSMSMessageRequest']['clientCorrelator'] ?? NULL);
      }
      return SmsGatewayResult::ok(
        'Accepted by Orange (HTTP ' . $status . ').',
        $providerRef,
        $status,
        $raw,
      );
    }

    $errorMsg = 'Orange rejected the request (HTTP ' . $status . ').';
    if (is_array($json)) {
      $requestError = $json['requestError'] ?? NULL;
      if (is_array($requestError)) {
        $first = reset($requestError);
        if (is_array($first)) {
          $code = $first['messageId'] ?? '';
          $text = $first['text'] ?? '';
          if ($code || $text) {
            $errorMsg .= ' ' . trim($code . ' ' . $text);
          }
        }
      }
    }
    return SmsGatewayResult::fail($errorMsg, $status, $raw);
  }

  /**
   * Fetch a cached access token or request a new one.
   */
  protected function getAccessToken() : string {
    $now     = $this->time->getCurrentTime();
    $cached  = (string) $this->state->get(self::STATE_TOKEN, '');
    $expires = (int)    $this->state->get(self::STATE_TOKEN_EXPS, 0);

    if ($cached !== '' && $expires > $now + 30) {
      return $cached;
    }

    $c       = $this->settings();
    $url     = rtrim((string) $c['base_url'], '/') . ($c['token_path'] ?: '/oauth/v3/token');
    $basic   = base64_encode($c['client_id'] . ':' . $c['client_secret']);
    $timeout = (int) ($c['request_timeout'] ?? 15);
    if ($timeout <= 0) {
      $timeout = 15;
    }

    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => [
          'Authorization' => 'Basic ' . $basic,
          'Content-Type'  => 'application/x-www-form-urlencoded',
          'Accept'        => 'application/json',
        ],
        'form_params' => [
          'grant_type' => 'client_credentials',
        ],
        'timeout'         => $timeout,
        'connect_timeout' => $timeout,
        'http_errors'     => FALSE,
      ]);
    }
    catch (RequestException $e) {
      throw new \RuntimeException('HTTP error: ' . $e->getMessage(), 0, $e);
    }

    $status = $response->getStatusCode();
    $body   = (string) $response->getBody();
    if ($status < 200 || $status >= 300) {
      throw new \RuntimeException("Token endpoint returned HTTP $status: " . mb_substr($body, 0, 400));
    }

    $data = json_decode($body, TRUE);
    if (!is_array($data) || empty($data['access_token'])) {
      throw new \RuntimeException('Token endpoint returned no access_token: ' . mb_substr($body, 0, 400));
    }

    $token     = (string) $data['access_token'];
    $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;
    if ($expiresIn < 60) {
      $expiresIn = 60;
    }
    $this->state->set(self::STATE_TOKEN, $token);
    $this->state->set(self::STATE_TOKEN_EXPS, $now + $expiresIn);
    return $token;
  }

  /**
   * Current gateway settings.
   */
  protected function settings() : array {
    $raw = $this->configFactory->get('mz_sms_notification.settings')->get('orange_madagascar');
    return is_array($raw) ? $raw : [];
  }

  /**
   * Normalize to "tel:+<E164>". Accepts raw MSISDN (with or without +) and
   * already-prefixed "tel:" strings.
   */
  protected function normalizeAddress(string $value) : string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }
    $prefix = '';
    if (stripos($value, 'tel:') === 0) {
      $value = substr($value, 4);
    }
    if (strpos($value, '+') === 0) {
      $prefix = '+';
      $value  = substr($value, 1);
    }
    $digits = preg_replace('/\D+/', '', $value);
    if ($digits === '') {
      return '';
    }
    return 'tel:' . ($prefix !== '' ? '+' : '') . $digits;
  }

  /**
   * Pull the resourceURL / resourceReference from a successful response.
   */
  protected function extractResourceUrl(array $json) : ?string {
    $req = $json['outboundSMSMessageRequest'] ?? $json;
    if (isset($req['resourceURL'])) {
      return (string) $req['resourceURL'];
    }
    if (isset($json['resourceReference']['resourceURL'])) {
      return (string) $json['resourceReference']['resourceURL'];
    }
    return NULL;
  }

}
