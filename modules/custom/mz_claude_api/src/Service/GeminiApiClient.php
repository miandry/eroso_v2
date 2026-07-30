<?php

namespace Drupal\mz_claude_api\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Client HTTP pour l'API Google Gemini (generateContent).
 */
class GeminiApiClient {

  private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

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
   * Envoie une requête multimodale à Gemini.
   *
   * @param array<int, array<string, mixed>> $messages
   *   Messages au format Anthropic (role + content string ou blocks) convertis
   *   en interne vers le format Gemini.
   * @param array<string, mixed> $options
   *   model, max_tokens, system, skip_french_system, timeout, …
   *
   * @return array<string, mixed>
   *   Réponse JSON Gemini décodée.
   *
   * @throws \RuntimeException
   */
  public function sendMessages(array $messages, array $options = []): array {
    $api_key = $this->getApiKey();
    if ($api_key === '') {
      throw new \RuntimeException('Clé API Gemini non configurée ($settings[mz_gemini_api_key] ou mz_claude_api.settings:api_key).');
    }

    $config = $this->configFactory->get('mz_claude_api.settings');
    $model = $this->resolveModel(trim((string) ($options['model'] ?? $config->get('model') ?? 'gemini-3.6-flash')));
    $max_tokens = (int) ($options['max_tokens'] ?? $config->get('max_tokens') ?? 1024);
    if ($max_tokens < 1) {
      $max_tokens = 1024;
    }

    $payload = [
      'contents' => $this->convertMessagesToGemini($messages),
      'generationConfig' => [
        'maxOutputTokens' => $max_tokens,
      ],
    ];

    if (!empty($options['system']) && is_string($options['system'])) {
      $payload['systemInstruction'] = [
        'parts' => [
          ['text' => $options['system']],
        ],
      ];
    }
    elseif (empty($options['skip_french_system'])) {
      $payload['systemInstruction'] = [
        'parts' => [
          ['text' => self::SYSTEM_FRENCH],
        ],
      ];
    }

    $url = self::API_BASE . '/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($api_key);

    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json' => $payload,
        'timeout' => (int) ($options['timeout'] ?? 90),
      ]);
    }
    catch (\Throwable $e) {
      $this->logger->error('Gemini API request failed: @msg', ['@msg' => $e->getMessage()]);
      throw new \RuntimeException('Erreur API Gemini : ' . $e->getMessage(), 0, $e);
    }

    $body = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($body)) {
      throw new \RuntimeException('Réponse Gemini invalide.');
    }
    if (!empty($body['error']) && is_array($body['error'])) {
      $msg = (string) ($body['error']['message'] ?? 'Erreur Gemini');
      throw new \RuntimeException($msg);
    }

    return $body;
  }

  /**
   * Extrait le texte de la réponse Gemini.
   */
  public function extractText(array $response): string {
    if (empty($response['candidates']) || !is_array($response['candidates'])) {
      return '';
    }
    foreach ($response['candidates'] as $candidate) {
      if (!is_array($candidate) || empty($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
        continue;
      }
      foreach ($candidate['content']['parts'] as $part) {
        if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
          $text = trim($part['text']);
          if ($text !== '') {
            return $text;
          }
        }
      }
    }
    return '';
  }

  /**
   * Clé API : settings.php prioritaire.
   */
  public function getApiKey(): string {
    $from_settings = Settings::get('mz_gemini_api_key', '');
    if (is_string($from_settings) && trim($from_settings) !== '') {
      return trim($from_settings);
    }
    $legacy = Settings::get('mz_claude_api_key', '');
    if (is_string($legacy) && trim($legacy) !== '') {
      return trim($legacy);
    }
    return trim((string) $this->configFactory->get('mz_claude_api.settings')->get('api_key'));
  }

  /**
   * Normalise le modèle : remplace les ids Claude legacy par Gemini.
   */
  protected function resolveModel(string $model): string {
    if ($model === '') {
      return 'gemini-3.6-flash';
    }
    if (str_starts_with($model, 'claude-') || str_starts_with($model, 'anthropic/')) {
      return 'gemini-3.6-flash';
    }
    return $model;
  }

  /**
   * Convertit le format messages Anthropic vers contents Gemini.
   *
   * @param array<int, array<string, mixed>> $messages
   *
   * @return array<int, array<string, mixed>>
   */
  protected function convertMessagesToGemini(array $messages): array {
    $contents = [];
    foreach ($messages as $message) {
      if (!is_array($message)) {
        continue;
      }
      $role = ($message['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
      $parts = [];
      $content = $message['content'] ?? '';
      if (is_string($content)) {
        if (trim($content) !== '') {
          $parts[] = ['text' => $content];
        }
      }
      elseif (is_array($content)) {
        foreach ($content as $block) {
          if (!is_array($block)) {
            continue;
          }
          $type = (string) ($block['type'] ?? '');
          if ($type === 'text') {
            $text = trim((string) ($block['text'] ?? ''));
            if ($text !== '') {
              $parts[] = ['text' => $text];
            }
          }
          elseif ($type === 'image') {
            $source = is_array($block['source'] ?? NULL) ? $block['source'] : [];
            $data = (string) ($source['data'] ?? '');
            if ($data !== '') {
              $parts[] = [
                'inline_data' => [
                  'mime_type' => (string) ($source['media_type'] ?? 'image/jpeg'),
                  'data' => $data,
                ],
              ];
            }
          }
        }
      }
      if (!empty($parts)) {
        $contents[] = [
          'role' => $role,
          'parts' => $parts,
        ];
      }
    }
    return $contents;
  }

}
