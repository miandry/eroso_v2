<?php

namespace Drupal\mz_api_integration\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Client HTTP pour l'API OpenAI ChatGPT (POST /v1/chat/completions).
 */
class ChatGptApiClient implements AiVisionClientInterface {

  private const API_URL = 'https://api.openai.com/v1/chat/completions';

  public function __construct(
    protected ClientInterface $httpClient,
    protected AiProviderSettings $providerSettings,
    protected AiUsageTracker $usageTracker,
    protected LoggerInterface $logger,
  ) {}

  public function getProviderId(): string {
    return AiProviderSettings::PROVIDER_CHATGPT;
  }

  public function sendMessages(array $messages, array $options = []): array {
    $api_key = $this->providerSettings->getProviderApiKey(AiProviderSettings::PROVIDER_CHATGPT);
    if ($api_key === '') {
      throw new \RuntimeException('Clé API ChatGPT non configurée ($settings[mz_chatgpt_api_key] ou mz_api_integration.settings).');
    }

    $model = trim((string) ($options['model'] ?? $this->providerSettings->getProviderModel(AiProviderSettings::PROVIDER_CHATGPT)));
    $max_tokens = $this->providerSettings->getMaxTokens(isset($options['max_tokens']) ? (int) $options['max_tokens'] : NULL);

    $payload = [
      'model' => $model,
      'max_tokens' => $max_tokens,
      'messages' => $this->convertMessages($messages, $options),
    ];

    try {
      $response = $this->httpClient->request('POST', self::API_URL, [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . $api_key,
        ],
        'json' => $payload,
        'timeout' => (int) ($options['timeout'] ?? 90),
      ]);
    }
    catch (\Throwable $e) {
      $this->logger->error('ChatGPT API request failed: @msg', ['@msg' => $e->getMessage()]);
      throw new \RuntimeException('Erreur API ChatGPT : ' . $e->getMessage(), 0, $e);
    }

    $body = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($body)) {
      throw new \RuntimeException('Réponse ChatGPT invalide.');
    }
    if (!empty($body['error']) && is_array($body['error'])) {
      throw new \RuntimeException((string) ($body['error']['message'] ?? 'Erreur ChatGPT'));
    }

    $this->usageTracker->recordFromResponse(AiProviderSettings::PROVIDER_CHATGPT, $model, $body);

    return $body;
  }

  public function extractText(array $response): string {
    if (empty($response['choices']) || !is_array($response['choices'])) {
      return '';
    }
    foreach ($response['choices'] as $choice) {
      if (!is_array($choice)) {
        continue;
      }
      $content = $choice['message']['content'] ?? '';
      if (is_string($content)) {
        $text = trim($content);
        if ($text !== '') {
          return $text;
        }
      }
      if (is_array($content)) {
        foreach ($content as $part) {
          if (is_array($part) && ($part['type'] ?? '') === 'text' && isset($part['text'])) {
            $text = trim((string) $part['text']);
            if ($text !== '') {
              return $text;
            }
          }
        }
      }
    }
    return '';
  }

  /**
   * @param array<int, array<string, mixed>> $messages
   * @param array<string, mixed> $options
   *
   * @return array<int, array<string, mixed>>
   */
  protected function convertMessages(array $messages, array $options): array {
    $out = [];
    $system = NULL;
    if (!empty($options['system']) && is_string($options['system'])) {
      $system = $options['system'];
    }
    elseif (empty($options['skip_french_system'])) {
      $system = AiFrenchSystemPrompt::TEXT;
    }
    if ($system !== NULL) {
      $out[] = ['role' => 'system', 'content' => $system];
    }

    foreach ($messages as $message) {
      if (!is_array($message)) {
        continue;
      }
      $role = (string) ($message['role'] ?? 'user');
      if ($role === 'assistant') {
        $role = 'assistant';
      }
      else {
        $role = 'user';
      }
      $content = $message['content'] ?? '';
      if (is_string($content)) {
        $out[] = ['role' => $role, 'content' => $content];
        continue;
      }
      if (is_array($content)) {
        $out[] = ['role' => $role, 'content' => $this->convertContentBlocks($content)];
      }
    }
    return $out;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  protected function convertContentBlocks(array $blocks): array {
    $parts = [];
    foreach ($blocks as $block) {
      if (!is_array($block)) {
        continue;
      }
      $type = (string) ($block['type'] ?? '');
      if ($type === 'text') {
        $text = trim((string) ($block['text'] ?? ''));
        if ($text !== '') {
          $parts[] = ['type' => 'text', 'text' => $text];
        }
      }
      elseif ($type === 'image') {
        $source = is_array($block['source'] ?? NULL) ? $block['source'] : [];
        $data = (string) ($source['data'] ?? '');
        if ($data !== '') {
          $mime = (string) ($source['media_type'] ?? 'image/jpeg');
          $parts[] = [
            'type' => 'image_url',
            'image_url' => [
              'url' => 'data:' . $mime . ';base64,' . $data,
            ],
          ];
        }
      }
    }
    return $parts;
  }

}
