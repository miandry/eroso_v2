<?php

namespace Drupal\mz_api_integration\Service;

use Drupal\Core\State\StateInterface;

/**
 * Suivi cumulé des tokens et coût estimé par fournisseur IA (app Eroso).
 */
class AiUsageTracker {

  private const STATE_KEY = 'mz_api_integration.ai_usage';

  /**
   * Tarifs indicatifs USD / 1M tokens (input, output).
   *
   * @var array<string, array{0: float, 1: float}>
   */
  private const MODEL_RATES = [
    'claude-opus-4' => [15.0, 75.0],
    'claude-sonnet-4' => [3.0, 15.0],
    'claude-haiku' => [0.8, 4.0],
    'gemini-3.6-flash' => [0.15, 0.60],
    'gemini-2.5-flash' => [0.15, 0.60],
    'gemini-2.0-flash' => [0.10, 0.40],
    'gpt-4o' => [2.5, 10.0],
    'gpt-4o-mini' => [0.15, 0.60],
    'o1' => [15.0, 60.0],
  ];

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Enregistre l'usage depuis une réponse API brute.
   */
  public function recordFromResponse(string $provider, string $model, array $body): void {
    [$input, $output] = $this->extractTokens($provider, $body);
    if ($input > 0 || $output > 0) {
      $this->record($provider, $model, $input, $output);
    }
  }

  public function record(string $provider, string $model, int $inputTokens, int $outputTokens): void {
    $provider = strtolower(trim($provider));
    if ($provider === '') {
      return;
    }
    $data = $this->state->get(self::STATE_KEY, []);
    if (!is_array($data)) {
      $data = [];
    }
    if (!isset($data[$provider]) || !is_array($data[$provider])) {
      $data[$provider] = $this->emptyProviderStats();
    }
    $cost = $this->estimateCostUsd($model, $inputTokens, $outputTokens);
    $data[$provider]['requests'] = (int) ($data[$provider]['requests'] ?? 0) + 1;
    $data[$provider]['input_tokens'] = (int) ($data[$provider]['input_tokens'] ?? 0) + max(0, $inputTokens);
    $data[$provider]['output_tokens'] = (int) ($data[$provider]['output_tokens'] ?? 0) + max(0, $outputTokens);
    $data[$provider]['cost_usd'] = round((float) ($data[$provider]['cost_usd'] ?? 0) + $cost, 6);
    $data[$provider]['last_request'] = time();
    $this->state->set(self::STATE_KEY, $data);
  }

  /**
   * @return array{
   *   requests: int,
   *   input_tokens: int,
   *   output_tokens: int,
   *   cost_usd: float,
   *   cost_usd_formatted: string,
   *   last_request: int|null,
   *   last_request_label: string|null
   * }
   */
  public function getProviderUsage(string $provider): array {
    $provider = strtolower(trim($provider));
    $data = $this->state->get(self::STATE_KEY, []);
    $stats = (is_array($data) && isset($data[$provider]) && is_array($data[$provider]))
      ? $data[$provider]
      : $this->emptyProviderStats();
    $cost = (float) ($stats['cost_usd'] ?? 0);
    $last = (int) ($stats['last_request'] ?? 0);
    return [
      'requests' => (int) ($stats['requests'] ?? 0),
      'input_tokens' => (int) ($stats['input_tokens'] ?? 0),
      'output_tokens' => (int) ($stats['output_tokens'] ?? 0),
      'cost_usd' => $cost,
      'cost_usd_formatted' => $this->formatUsd($cost),
      'last_request' => $last > 0 ? $last : NULL,
      'last_request_label' => $last > 0 ? date('d/m/Y H:i', $last) : NULL,
    ];
  }

  /**
   * @return array<int, int>
   */
  protected function extractTokens(string $provider, array $body): array {
    return match ($provider) {
      AiProviderSettings::PROVIDER_CLAUDE => [
        (int) ($body['usage']['input_tokens'] ?? 0),
        (int) ($body['usage']['output_tokens'] ?? 0),
      ],
      AiProviderSettings::PROVIDER_GEMINI => [
        (int) ($body['usageMetadata']['promptTokenCount'] ?? 0),
        (int) (($body['usageMetadata']['candidatesTokenCount'] ?? 0) + ($body['usageMetadata']['thoughtsTokenCount'] ?? 0)),
      ],
      AiProviderSettings::PROVIDER_CHATGPT => [
        (int) ($body['usage']['prompt_tokens'] ?? 0),
        (int) ($body['usage']['completion_tokens'] ?? 0),
      ],
      default => [0, 0],
    };
  }

  public function estimateCostUsd(string $model, int $inputTokens, int $outputTokens): float {
    [$in_rate, $out_rate] = $this->resolveRates($model);
    return ($inputTokens / 1_000_000 * $in_rate) + ($outputTokens / 1_000_000 * $out_rate);
  }

  /**
   * @return array{0: float, 1: float}
   */
  protected function resolveRates(string $model): array {
    $model = strtolower(trim($model));
    foreach (self::MODEL_RATES as $prefix => $rates) {
      if (str_starts_with($model, strtolower($prefix))) {
        return $rates;
      }
    }
    if (str_starts_with($model, 'claude-')) {
      return [3.0, 15.0];
    }
    if (str_starts_with($model, 'gemini-')) {
      return [0.15, 0.60];
    }
    if (str_starts_with($model, 'gpt-') || str_starts_with($model, 'o')) {
      return [2.5, 10.0];
    }
    return [1.0, 3.0];
  }

  protected function formatUsd(float $amount): string {
    if ($amount < 0.01 && $amount > 0) {
      return '$' . number_format($amount, 4, '.', '');
    }
    return '$' . number_format($amount, 2, '.', ' ');
  }

  /**
   * @return array<string, int|float>
   */
  protected function emptyProviderStats(): array {
    return [
      'requests' => 0,
      'input_tokens' => 0,
      'output_tokens' => 0,
      'cost_usd' => 0.0,
      'last_request' => 0,
    ];
  }

}
