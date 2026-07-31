<?php

namespace Drupal\mz_api_integration\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Site\Settings;

/**
 * Lit le fournisseur IA actif et les clés / modèles par provider.
 */
class AiProviderSettings {

  public const PROVIDER_GEMINI = 'gemini';

  public const PROVIDER_CLAUDE = 'claude';

  public const PROVIDER_CHATGPT = 'chatgpt';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected AiUsageTracker $usageTracker,
  ) {}

  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('mz_api_integration.settings');
  }

  /**
   * Fournisseur actif : gemini, claude ou chatgpt.
   */
  public function getActiveProvider(): string {
    $from_settings = Settings::get('mz_ai_provider', '');
    if (is_string($from_settings) && $this->isValidProvider(trim($from_settings))) {
      return trim($from_settings);
    }
    $from_config = trim((string) $this->getConfig()->get('ai_provider'));
    if ($this->isValidProvider($from_config)) {
      return $from_config;
    }
    return self::PROVIDER_GEMINI;
  }

  public function getMaxTokens(?int $override = NULL): int {
    if ($override !== NULL && $override > 0) {
      return $override;
    }
    $max = (int) ($this->getConfig()->get('max_tokens') ?? 1024);
    return $max > 0 ? $max : 1024;
  }

  public function getProviderModel(string $provider): string {
    $defaults = [
      self::PROVIDER_GEMINI => 'gemini-3.6-flash',
      self::PROVIDER_CLAUDE => 'claude-opus-4-7',
      self::PROVIDER_CHATGPT => 'gpt-4o',
    ];
    $provider = $this->normalizeProvider($provider);
    $from_config = trim((string) ($this->getConfig()->get('providers.' . $provider . '.model') ?? ''));
    if ($from_config !== '') {
      return $from_config;
    }
    $legacy = trim((string) $this->getConfig()->get('model'));
    if ($legacy !== '' && $this->modelMatchesProvider($legacy, $provider)) {
      return $legacy;
    }
    return $defaults[$provider];
  }

  public function getProviderApiKey(string $provider): string {
    $provider = $this->normalizeProvider($provider);
    $settings_keys = [
      self::PROVIDER_GEMINI => ['mz_gemini_api_key', 'mz_claude_api_key'],
      self::PROVIDER_CLAUDE => ['mz_claude_api_key'],
      self::PROVIDER_CHATGPT => ['mz_chatgpt_api_key', 'mz_openai_api_key'],
    ];
    foreach ($settings_keys[$provider] as $key) {
      $value = Settings::get($key, '');
      if (is_string($value) && trim($value) !== '') {
        return trim($value);
      }
    }
    $from_config = trim((string) ($this->getConfig()->get('providers.' . $provider . '.api_key') ?? ''));
    if ($from_config !== '') {
      return $from_config;
    }
    if ($provider === self::PROVIDER_GEMINI) {
      return trim((string) $this->getConfig()->get('api_key'));
    }
    return '';
  }

  public function getAnthropicVersion(): string {
    return trim((string) ($this->getConfig()->get('providers.claude.anthropic_version')
      ?? $this->getConfig()->get('anthropic_version')
      ?? '2023-06-01'));
  }

  public function isValidProvider(string $provider): bool {
    return in_array($provider, [
      self::PROVIDER_GEMINI,
      self::PROVIDER_CLAUDE,
      self::PROVIDER_CHATGPT,
    ], TRUE);
  }

  protected function normalizeProvider(string $provider): string {
    $provider = strtolower(trim($provider));
    return $this->isValidProvider($provider) ? $provider : self::PROVIDER_GEMINI;
  }

  protected function modelMatchesProvider(string $model, string $provider): bool {
    $model = strtolower($model);
    return match ($provider) {
      self::PROVIDER_GEMINI => str_starts_with($model, 'gemini'),
      self::PROVIDER_CLAUDE => str_starts_with($model, 'claude-') || str_starts_with($model, 'anthropic/'),
      self::PROVIDER_CHATGPT => str_starts_with($model, 'gpt-') || str_starts_with($model, 'o1') || str_starts_with($model, 'o3') || str_starts_with($model, 'o4'),
      default => FALSE,
    };
  }

  /**
   * Le fournisseur est imposé par settings.php (prioritaire sur la config).
   */
  public function isProviderLockedBySettings(): bool {
    $from_settings = Settings::get('mz_ai_provider', '');
    return is_string($from_settings) && trim($from_settings) !== '' && $this->isValidProvider(trim($from_settings));
  }

  /**
   * Résumé pour l'API / la page Réglages (sans exposer les clés API).
   *
   * @return array<string, mixed>
   */
  public function getSettingsSummary(): array {
    $providers = [];
    foreach ([self::PROVIDER_GEMINI, self::PROVIDER_CLAUDE, self::PROVIDER_CHATGPT] as $id) {
      $providers[] = [
        'id' => $id,
        'label' => $this->getProviderLabel($id),
        'model' => $this->getProviderModel($id),
        'configured' => $this->getProviderApiKey($id) !== '',
        'usage' => $this->usageTracker->getProviderUsage($id),
      ];
    }
    return [
      'ai_provider' => $this->getActiveProvider(),
      'locked_by_settings' => $this->isProviderLockedBySettings(),
      'providers' => $providers,
      'usage_note' => 'Coûts estimés cumulés pour les appels IA passés par cette application (tarifs publics indicatifs). La facture réelle Anthropic/OpenAI/Google se consulte sur la console du fournisseur.',
    ];
  }

  /**
   * Enregistre le fournisseur actif (config Drupal).
   *
   * @throws \RuntimeException
   * @throws \InvalidArgumentException
   */
  public function saveActiveProvider(string $provider): void {
    if ($this->isProviderLockedBySettings()) {
      throw new \RuntimeException('Le fournisseur IA est verrouillé dans settings.php ($settings[mz_ai_provider]).');
    }
    $provider = strtolower(trim($provider));
    if (!$this->isValidProvider($provider)) {
      throw new \InvalidArgumentException('Fournisseur IA invalide.');
    }
    $this->configFactory->getEditable('mz_api_integration.settings')
      ->set('ai_provider', $provider)
      ->save();
  }

  protected function getProviderLabel(string $id): string {
    return match ($id) {
      self::PROVIDER_GEMINI => 'Google Gemini',
      self::PROVIDER_CLAUDE => 'Claude AI',
      self::PROVIDER_CHATGPT => 'ChatGPT',
      default => $id,
    };
  }

}
