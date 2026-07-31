<?php

namespace Drupal\mz_api_integration\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Délègue aux clients Gemini, Claude ou ChatGPT selon la configuration.
 */
class SwitchableAiVisionClient implements AiVisionClientInterface {

  public function __construct(
    protected AiProviderSettings $providerSettings,
    protected GeminiApiClient $geminiClient,
    protected ClaudeApiClient $claudeClient,
    protected ChatGptApiClient $chatGptClient,
  ) {}

  public function getProviderId(): string {
    return $this->providerSettings->getActiveProvider();
  }

  public function sendMessages(array $messages, array $options = []): array {
    return $this->getActiveClient()->sendMessages($messages, $options);
  }

  public function extractText(array $response): string {
    return $this->getActiveClient()->extractText($response);
  }

  protected function getActiveClient(): AiVisionClientInterface {
    return match ($this->getProviderId()) {
      AiProviderSettings::PROVIDER_CLAUDE => $this->claudeClient,
      AiProviderSettings::PROVIDER_CHATGPT => $this->chatGptClient,
      default => $this->geminiClient,
    };
  }

}
