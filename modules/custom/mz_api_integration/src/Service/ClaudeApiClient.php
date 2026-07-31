<?php

namespace Drupal\mz_api_integration\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Client HTTP pour l'API Anthropic Claude (POST /v1/messages).
 */
class ClaudeApiClient implements AiVisionClientInterface {

  private const API_URL = 'https://api.anthropic.com/v1/messages';

  public function __construct(
    protected ClientInterface $httpClient,
    protected AiProviderSettings $providerSettings,
    protected AiUsageTracker $usageTracker,
    protected LoggerInterface $logger,
  ) {}

  public function getProviderId(): string {
    return AiProviderSettings::PROVIDER_CLAUDE;
  }

  public function sendMessages(array $messages, array $options = []): array {
    $api_key = $this->providerSettings->getProviderApiKey(AiProviderSettings::PROVIDER_CLAUDE);
    if ($api_key === '') {
      throw new \RuntimeException('Clé API Claude non configurée ($settings[mz_claude_api_key] ou mz_api_integration.settings).');
    }

    $model = trim((string) ($options['model'] ?? $this->providerSettings->getProviderModel(AiProviderSettings::PROVIDER_CLAUDE)));
    $max_tokens = $this->providerSettings->getMaxTokens(isset($options['max_tokens']) ? (int) $options['max_tokens'] : NULL);
    $version = trim((string) ($options['anthropic_version'] ?? $this->providerSettings->getAnthropicVersion()));

    $payload = [
      'model' => $model,
      'max_tokens' => $max_tokens,
      'messages' => $this->normalizeMessages($messages),
    ];
    if (!empty($options['system']) && is_string($options['system'])) {
      $payload['system'] = $options['system'];
    }
    elseif (empty($options['skip_french_system'])) {
      $payload['system'] = AiFrenchSystemPrompt::TEXT;
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
      throw new \RuntimeException($this->formatRequestError($e), 0, $e);
    }

    $body = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($body)) {
      throw new \RuntimeException('Réponse Claude invalide.');
    }
    if (!empty($body['error']) && is_array($body['error'])) {
      throw new \RuntimeException($this->formatApiErrorMessage((string) ($body['error']['message'] ?? 'Erreur Claude')));
    }

    $this->usageTracker->recordFromResponse(AiProviderSettings::PROVIDER_CLAUDE, $model, $body);

    return $body;
  }

  protected function formatRequestError(\Throwable $e): string {
    return $this->formatApiErrorMessage($e->getMessage());
  }

  protected function formatApiErrorMessage(string $message): string {
    if (stripos($message, 'credit balance is too low') !== FALSE) {
      return 'Crédits Anthropic Claude épuisés. Rechargez votre compte Anthropic ou sélectionnez Google Gemini dans Réglages IA.';
    }
    if (stripos($message, 'invalid x-api-key') !== FALSE || stripos($message, 'authentication') !== FALSE) {
      return 'Clé API Claude invalide. Vérifiez $settings[mz_claude_api_key] dans settings.php.';
    }
    return 'Erreur API Claude : ' . $message;
  }

  public function extractText(array $response): string {
    if (empty($response['content']) || !is_array($response['content'])) {
      return '';
    }
    foreach ($response['content'] as $block) {
      if (is_array($block) && ($block['type'] ?? '') === 'text' && isset($block['text'])) {
        $text = trim((string) $block['text']);
        if ($text !== '') {
          return $text;
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
  protected function normalizeMessages(array $messages): array {
    $out = [];
    foreach ($messages as $message) {
      if (!is_array($message)) {
        continue;
      }
      $role = (string) ($message['role'] ?? 'user');
      if (!in_array($role, ['user', 'assistant'], TRUE)) {
        $role = 'user';
      }
      $content = $message['content'] ?? '';
      if (is_string($content)) {
        $out[] = ['role' => $role, 'content' => $content];
        continue;
      }
      if (is_array($content)) {
        $out[] = ['role' => $role, 'content' => $content];
      }
    }
    return $out;
  }

}
