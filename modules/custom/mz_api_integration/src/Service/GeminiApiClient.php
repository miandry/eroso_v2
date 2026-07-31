<?php

namespace Drupal\mz_api_integration\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Client HTTP pour l'API Google Gemini (generateContent).
 */
class GeminiApiClient implements AiVisionClientInterface {

  private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

  public function __construct(
    protected ClientInterface $httpClient,
    protected AiProviderSettings $providerSettings,
    protected AiUsageTracker $usageTracker,
    protected LoggerInterface $logger,
  ) {}

  public function getProviderId(): string {
    return AiProviderSettings::PROVIDER_GEMINI;
  }

  public function sendMessages(array $messages, array $options = []): array {
    $api_key = $this->providerSettings->getProviderApiKey(AiProviderSettings::PROVIDER_GEMINI);
    if ($api_key === '') {
      throw new \RuntimeException('Clé API Gemini non configurée ($settings[mz_gemini_api_key] ou mz_api_integration.settings).');
    }

    $model = trim((string) ($options['model'] ?? $this->providerSettings->getProviderModel(AiProviderSettings::PROVIDER_GEMINI)));
    $max_tokens = $this->providerSettings->getMaxTokens(isset($options['max_tokens']) ? (int) $options['max_tokens'] : NULL);

    $payload = [
      'contents' => $this->convertMessages($messages),
      'generationConfig' => [
        'maxOutputTokens' => $max_tokens,
      ],
    ];

    if (!empty($options['system']) && is_string($options['system'])) {
      $payload['systemInstruction'] = ['parts' => [['text' => $options['system']]]];
    }
    elseif (empty($options['skip_french_system'])) {
      $payload['systemInstruction'] = ['parts' => [['text' => AiFrenchSystemPrompt::TEXT]]];
    }

    $url = self::API_BASE . '/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($api_key);

    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => ['Content-Type' => 'application/json'],
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
      throw new \RuntimeException((string) ($body['error']['message'] ?? 'Erreur Gemini'));
    }

    $this->usageTracker->recordFromResponse(AiProviderSettings::PROVIDER_GEMINI, $model, $body);

    return $body;
  }

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
   * @param array<int, array<string, mixed>> $messages
   *
   * @return array<int, array<string, mixed>>
   */
  protected function convertMessages(array $messages): array {
    $contents = [];
    foreach ($messages as $message) {
      if (!is_array($message)) {
        continue;
      }
      $role = ($message['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
      $parts = $this->convertContentBlocks($message['content'] ?? '');
      if (!empty($parts)) {
        $contents[] = ['role' => $role, 'parts' => $parts];
      }
    }
    return $contents;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  protected function convertContentBlocks(mixed $content): array {
    $parts = [];
    if (is_string($content)) {
      if (trim($content) !== '') {
        $parts[] = ['text' => $content];
      }
      return $parts;
    }
    if (!is_array($content)) {
      return $parts;
    }
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
    return $parts;
  }

}
