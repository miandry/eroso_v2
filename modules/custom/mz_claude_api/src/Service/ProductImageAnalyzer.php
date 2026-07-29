<?php

namespace Drupal\mz_claude_api\Service;

/**
 * Analyse d'images produit via Claude Vision.
 */
class ProductImageAnalyzer {

  public function __construct(
    protected ClaudeApiClient $claudeClient,
  ) {}

  /**
   * Extrait des termes de recherche catalogue depuis une photo produit.
   *
   * @return array{
   *   keywords: string[],
   *   title_guess: string,
   *   sku_guess: string,
   *   category_guess: string,
   *   description_short: string,
   *   colors: string[],
   *   materials: string[]
   * }
   */
  public function analyze(string $binary, string $mime): array {
    $mime = $this->normalizeMime($mime);
    $prompt = <<<'PROMPT'
Analyse cette photo comme pour un catalogue e-commerce (boutique à Madagascar).
Réponds UNIQUEMENT en JSON valide (sans markdown, sans commentaire) avec cette structure exacte :
{
  "keywords": ["mot-clé1", "mot-clé2"],
  "title_guess": "nom probable du produit",
  "sku_guess": "référence/SKU visible sur l'image ou chaîne vide",
  "category_guess": "catégorie probable",
  "description_short": "description courte",
  "colors": ["couleur principale"],
  "materials": ["matière si identifiable"]
}
IMPORTANT — langue : tous les champs texte (keywords, title_guess, category_guess, description_short, colors, materials) doivent être rédigés en français uniquement.
Les keywords doivent être utiles pour retrouver le produit (type, marque, couleur, usage).
PROMPT;

    $response = $this->claudeClient->sendMessages([
      [
        'role' => 'user',
        'content' => [
          [
            'type' => 'image',
            'source' => [
              'type' => 'base64',
              'media_type' => $mime,
              'data' => base64_encode($binary),
            ],
          ],
          [
            'type' => 'text',
            'text' => $prompt,
          ],
        ],
      ],
    ]);

    $text = $this->claudeClient->extractText($response);
    if ($text === '') {
      throw new \RuntimeException('Réponse Claude vide.');
    }

    $parsed = $this->parseJsonFromText($text);
    return $this->normalizeAnalysis($parsed);
  }

  /**
   * @return array<string, mixed>
   */
  protected function parseJsonFromText(string $text): array {
    $text = trim($text);
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
      $text = trim($matches[1]);
    }
    $parsed = json_decode($text, TRUE);
    if (!is_array($parsed)) {
      throw new \RuntimeException('Réponse Claude : JSON invalide.');
    }
    return $parsed;
  }

  /**
   * @param array<string, mixed> $parsed
   */
  protected function normalizeAnalysis(array $parsed): array {
    $keywords = [];
    if (!empty($parsed['keywords']) && is_array($parsed['keywords'])) {
      foreach ($parsed['keywords'] as $kw) {
        $kw = trim((string) $kw);
        if ($kw !== '') {
          $keywords[] = $kw;
        }
      }
    }

    $title = trim((string) ($parsed['title_guess'] ?? ''));
    if ($title !== '') {
      $keywords[] = $title;
    }

    $category = trim((string) ($parsed['category_guess'] ?? ''));
    if ($category !== '') {
      $keywords[] = $category;
    }

    foreach (['colors', 'materials'] as $list_key) {
      if (!empty($parsed[$list_key]) && is_array($parsed[$list_key])) {
        foreach ($parsed[$list_key] as $item) {
          $item = trim((string) $item);
          if ($item !== '') {
            $keywords[] = $item;
          }
        }
      }
    }

    return [
      'keywords' => array_values(array_unique($keywords)),
      'title_guess' => $title,
      'sku_guess' => trim((string) ($parsed['sku_guess'] ?? '')),
      'category_guess' => $category,
      'description_short' => trim((string) ($parsed['description_short'] ?? '')),
      'colors' => array_values(array_filter(array_map('strval', $parsed['colors'] ?? []))),
      'materials' => array_values(array_filter(array_map('strval', $parsed['materials'] ?? []))),
    ];
  }

  /**
   * Texte stocké dans field_search_image pour la recherche par image.
   *
   * @param array{
   *   keywords?: string[],
   *   title_guess?: string,
   *   sku_guess?: string,
   *   category_guess?: string,
   *   description_short?: string,
   *   colors?: string[],
   *   materials?: string[]
   * } $analysis
   */
  public function formatSearchImageText(array $analysis): string {
    $lines = [];

    $title = trim((string) ($analysis['title_guess'] ?? ''));
    if ($title !== '') {
      $lines[] = $title;
    }

    $category = trim((string) ($analysis['category_guess'] ?? ''));
    if ($category !== '') {
      $lines[] = 'Catégorie : ' . $category;
    }

    $description = trim((string) ($analysis['description_short'] ?? ''));
    if ($description !== '') {
      if (mb_strlen($description) > 320) {
        $description = mb_substr($description, 0, 317) . '…';
      }
      $lines[] = $description;
    }

    $context_parts = array_filter([$title, $category, $description]);
    $context = mb_strtolower(implode(' ', $context_parts));

    $keywords = [];
    if (!empty($analysis['keywords']) && is_array($analysis['keywords'])) {
      foreach ($analysis['keywords'] as $kw) {
        $kw = trim((string) $kw);
        if ($kw === '' || mb_strlen($kw) < 2) {
          continue;
        }
        $kw_lower = mb_strtolower($kw);
        if ($context !== '' && (mb_strpos($context, $kw_lower) !== FALSE || mb_strpos($kw_lower, $context) !== FALSE)) {
          continue;
        }
        $keywords[] = $kw;
      }
    }
    $keywords = array_values(array_unique($keywords));
    if (count($keywords) > 18) {
      $keywords = array_slice($keywords, 0, 18);
    }
    if (!empty($keywords)) {
      $lines[] = 'Mots-clés : ' . implode(', ', $keywords);
    }

    $colors = array_values(array_filter(array_map('strval', $analysis['colors'] ?? [])));
    if (!empty($colors)) {
      $lines[] = 'Couleurs : ' . implode(', ', array_slice($colors, 0, 6));
    }

    $materials = array_values(array_filter(array_map('strval', $analysis['materials'] ?? [])));
    if (!empty($materials)) {
      $lines[] = 'Matériaux : ' . implode(', ', array_slice($materials, 0, 6));
    }

    $sku = trim((string) ($analysis['sku_guess'] ?? ''));
    if ($sku !== '') {
      $lines[] = 'SKU : ' . $sku;
    }

    $text = implode("\n", $lines);
    if (mb_strlen($text) > 2000) {
      $text = mb_substr($text, 0, 1997) . '…';
    }
    return $text;
  }

  protected function normalizeMime(string $mime): string {
    $mime = strtolower(trim($mime));
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (in_array($mime, $allowed, TRUE)) {
      return $mime;
    }
    return 'image/jpeg';
  }

}
