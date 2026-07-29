<?php

namespace Drupal\mz_claude_api\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Client HTTP pour l'API Anthropic Claude (POST /v1/messages).
 */
class ClaudeApiClient {

  private const API_URL = 'https://api.anthropic.com/v1/messages';

  /** Consigne système : toute sortie textuelle en français. */
  private const SYSTEM_FRENCH = <<<'SYSTEM'
Tu es un assistant pour une boutique e-commerce à Madagascar.
Règle absolue : tout texte que tu génères doit être en français (titres, descriptions, mots-clés, catégories, couleurs, matériaux, motifs de correspondance, etc.).
N'utilise jamais l'anglais ni d'autre langue dans les champs texte, sauf noms de marque officiels ou codes SKU visibles sur le produit.
SYSTEM;

  public function __construct(
    protected ClientInterface $httpClient,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Envoie une requête messages à Claude.
   *
   * @param array<int, array<string, mixed>> $messages
   *   Messages au format Anthropic (role + content string ou blocks).
   * @param array<string, mixed> $options
   *   model, max_tokens, system, …
   *
   * @return array<string, mixed>
   *   Réponse JSON décodée.
   *
   * @throws \RuntimeException
   */
  public function sendMessages(array $messages, array $options = []): array {
    $api_key = $this->getApiKey();
    if ($api_key === '') {
      throw new \RuntimeException('Clé API Anthropic non configurée ($settings[mz_claude_api_key] ou mz_claude_api.settings:api_key).');
    }

    $config = $this->configFactory->get('mz_claude_api.settings');
    $model = trim((string) ($options['model'] ?? $config->get('model') ?? 'claude-opus-4-7'));
    $max_tokens = (int) ($options['max_tokens'] ?? $config->get('max_tokens') ?? 1024);
    if ($max_tokens < 1) {
      $max_tokens = 1024;
    }
    $version = trim((string) ($options['anthropic_version'] ?? $config->get('anthropic_version') ?? '2023-06-01'));

    $payload = [
      'model' => $model,
      'max_tokens' => $max_tokens,
      'messages' => $messages,
    ];
    if (!empty($options['system']) && is_string($options['system'])) {
      $payload['system'] = $options['system'];
    }
    elseif (empty($options['skip_french_system'])) {
      $payload['system'] = self::SYSTEM_FRENCH;
    }

    try {
      $response = $this->httpClient->request('POST', self::API_URL, [
        'headers' => [
          'Content-Type' => 'application/json',
          'x-api-key' => $api_key,
          'anthropic-version' => $version,
        ],
        'json' => $payload,
        'timeout' => (int) ($options['timeout'] ?? 90),
      ]);
    }
    catch (\Throwable $e) {
      $this->logger->error('Claude API request failed: @msg', ['@msg' => $e->getMessage()]);
      throw new \RuntimeException('Erreur API Claude : ' . $e->getMessage(), 0, $e);
    }

    $body = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($body)) {
      throw new \RuntimeException('Réponse Claude invalide.');
    }
    if (!empty($body['error']) && is_array($body['error'])) {
      $msg = (string) ($body['error']['message'] ?? 'Erreur Claude');
      throw new \RuntimeException($msg);
    }

    return $body;
  }

  /**
   * Extrait le texte du premier bloc content[].text.
   */
  public function extractText(array $response): string {
    if (empty($response['content']) || !is_array($response['content'])) {
      return '';
    }
    foreach ($response['content'] as $block) {
      if (is_array($block) && ($block['type'] ?? '') === 'text' && isset($block['text'])) {
        return trim((string) $block['text']);
      }
    }
    return '';
  }

  /**
   * Clé API : settings.php prioritaire.
   */
  public function getApiKey(): string {
    $from_settings = Settings::get('mz_claude_api_key', '');
    if (is_string($from_settings) && trim($from_settings) !== '') {
      return trim($from_settings);
    }
    return trim((string) $this->configFactory->get('mz_claude_api.settings')->get('api_key'));
  }

}
