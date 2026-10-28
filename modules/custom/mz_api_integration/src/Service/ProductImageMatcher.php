<?php

namespace Drupal\mz_api_integration\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Recherche produits par field_search_image et comparaison visuelle catalogue.
 */
class ProductImageMatcher {

  public function __construct(
    protected AiVisionClientInterface $aiClient,
    protected ProductImageAnalyzer $imageAnalyzer,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Génère field_search_image depuis l'image principale d'un produit.
   */
  public function buildSearchImageTextForNode(NodeInterface $node): ?string {
    $data = $this->buildSearchImageDataForNode($node);
    return $data['field_search_image'] ?? NULL;
  }

  /**
   * @return array{field_search_image: string, analysis: array}|null
   */
  public function buildSearchImageDataForNode(NodeInterface $node): ?array {
    if (!$node->hasField('field_search_image')) {
      return NULL;
    }
    $image = $this->loadPrimaryImageFromNode($node);
    if ($image === NULL) {
      return NULL;
    }
    try {
      $analysis = $this->imageAnalyzer->analyze($image['binary'], $image['mime']);
    }
    catch (\RuntimeException $e) {
      \Drupal::logger('mz_api_integration')->warning('buildSearchImageText nid @nid: @msg', [
        '@nid' => $node->id(),
        '@msg' => $e->getMessage(),
      ]);
      return NULL;
    }
    return [
      'field_search_image' => $this->imageAnalyzer->formatSearchImageText($analysis),
      'analysis' => $analysis,
    ];
  }

  /**
   * Recherche produits via field_search_image (texte IA généré à l'insert).
   *
   * @param array{
   *   keywords?: string[],
   *   title_guess?: string,
   *   category_guess?: string,
   *   description_short?: string,
   *   colors?: string[],
   *   materials?: string[]
   * } $analysis
   *
   * @return array{
   *   matches: array<int, array{score: int, reason: string}>,
   *   scanned: int
   * }
   */
  /**
   * @param array{
   *   public_only?: bool,
   *   category_tid?: int
   * } $options
   */
  public function searchByFieldSearchImage(array $analysis, int $resultLimit = 20, string $bundle = 'product', array $options = []): array {
    $config = $this->configFactory->get('mz_api_integration.settings');
    $min_score = (int) ($config->get('min_text_match_score') ?? $config->get('min_match_score') ?? 24);
    if ($min_score < 1) {
      $min_score = 24;
    }

    if (!in_array($bundle, ['product', 'product_commande'], TRUE)) {
      $bundle = 'product';
    }

    $sku = trim((string) ($analysis['sku_guess'] ?? ''));
    if ($sku !== '' && mb_strlen($sku) >= 3) {
      $sku_result = $this->searchBySkuGuess($sku, $resultLimit, $bundle, $options, $analysis);
      if (!empty($sku_result['matches'])) {
        return $sku_result;
      }
    }

    $needles = $this->extractNeedlesFromAnalysis($analysis);
    if (empty($needles)) {
      return ['matches' => [], 'scanned' => 0];
    }

    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->exists('field_search_image');

    $this->applySearchQueryOptions($query, $options);

    $or = $query->orConditionGroup();
    foreach (array_slice($needles, 0, 15) as $needle) {
      if (mb_strlen($needle) >= 2) {
        $or->condition('field_search_image.value', $needle, 'CONTAINS');
      }
    }
    if (count($or->conditions()) > 0) {
      $query->condition($or);
    }

    $query->sort('changed', 'DESC');
    $scan_limit = !empty($options['public_only']) ? 180 : 300;
    $query->range(0, $scan_limit);
    $nids = $query->execute();
    if (empty($nids)) {
      return ['matches' => [], 'scanned' => 0];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);
    $matches = [];
    foreach ($nodes as $node) {
      if (!$node->hasField('field_search_image') || $node->get('field_search_image')->isEmpty()) {
        continue;
      }
      $search_text = (string) $node->get('field_search_image')->value;
      $detail = $this->scoreTextMatchDetailed($analysis, $search_text);
      if ($detail['score'] < $min_score) {
        continue;
      }
      $matches[(int) $node->id()] = [
        'score' => $detail['score'],
        'reason' => $this->formatTextMatchReason($detail['matched']),
      ];
    }

    uasort($matches, static function ($a, $b) {
      return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });

    if ($resultLimit > 0) {
      $matches = array_slice($matches, 0, $resultLimit, TRUE);
    }

    return [
      'matches' => $matches,
      'scanned' => count($nids),
    ];
  }

  public function matchCatalog(string $uploadBinary, string $uploadMime, int $resultLimit = 20, ?array $analysis = NULL): array {
    $config = $this->configFactory->get('mz_api_integration.settings');
    $max_scan = (int) ($config->get('max_products_scan') ?? 48);
    $batch_size = (int) ($config->get('compare_batch_size') ?? 6);
    $min_score = (int) ($config->get('min_match_score') ?? 35);
    if ($max_scan < 1) {
      $max_scan = 48;
    }
    if ($batch_size < 1) {
      $batch_size = 6;
    }
    if ($batch_size > 10) {
      $batch_size = 10;
    }

    $candidates = $this->loadProductCandidates($max_scan);
    if (empty($candidates)) {
      return ['matches' => [], 'scanned' => 0, 'batches' => 0];
    }

    if (is_array($analysis) && !empty($analysis)) {
      foreach ($candidates as &$candidate) {
        $candidate['text_score'] = $this->scoreTextMatch(
          $analysis,
          (string) ($candidate['search_text'] ?? '')
        );
      }
      unset($candidate);
      usort($candidates, static function ($a, $b) {
        return ($b['text_score'] ?? 0) <=> ($a['text_score'] ?? 0);
      });
    }

    $uploadMime = $this->normalizeMime($uploadMime);
    $all_matches = [];
    $batches = 0;
    $chunks = array_chunk($candidates, $batch_size);

    foreach ($chunks as $chunk) {
      $batch_matches = $this->compareBatch($uploadBinary, $uploadMime, $chunk);
      $batches++;
      foreach ($batch_matches as $nid => $info) {
        $score = (int) ($info['score'] ?? 0);
        if ($score < $min_score) {
          continue;
        }
        $text_boost = 0;
        foreach ($candidates as $candidate) {
          if ((int) ($candidate['nid'] ?? 0) === $nid) {
            $text_boost = (int) ($candidate['text_score'] ?? 0);
            break;
          }
        }
        if ($text_boost > 0) {
          $score = min(100, $score + (int) round($text_boost * 0.15));
          $info['score'] = $score;
        }
        if (!isset($all_matches[$nid]) || $all_matches[$nid]['score'] < $score) {
          $all_matches[$nid] = $info;
        }
      }
    }

    uasort($all_matches, static function ($a, $b) {
      return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });

    if ($resultLimit > 0) {
      $all_matches = array_slice($all_matches, 0, $resultLimit, TRUE);
    }

    return [
      'matches' => $all_matches,
      'scanned' => count($candidates),
      'batches' => $batches,
    ];
  }

  /**
   * @param array<int, array{nid: int, title: string, binary: string, mime: string}> $candidates
   *
   * @return array<int, array{score: int, reason: string}>
   */
  protected function compareBatch(string $uploadBinary, string $uploadMime, array $candidates): array {
    $content = [];
    $content[] = [
      'type' => 'text',
      'text' => 'Image A = photo de référence (celle de l\'utilisateur). Les images suivantes B1, B2… sont les photos catalogue (field_media_image) de produits e-commerce.',
    ];
    $content[] = [
      'type' => 'image',
      'source' => [
        'type' => 'base64',
        'media_type' => $uploadMime,
        'data' => base64_encode($uploadBinary),
      ],
    ];

    $index_map = [];
    $i = 1;
    foreach ($candidates as $candidate) {
      $label = 'B' . $i;
      $index_map[$label] = (int) $candidate['nid'];
      $content[] = [
        'type' => 'text',
        'text' => $label . ' — produit nid ' . $candidate['nid'] . ', titre : « ' . $candidate['title'] . ' »',
      ];
      $content[] = [
        'type' => 'image',
        'source' => [
          'type' => 'base64',
          'media_type' => $candidate['mime'],
          'data' => base64_encode($candidate['binary']),
        ],
      ];
      $i++;
    }

    $nid_list = implode(', ', array_map('strval', array_values($index_map)));
    $content[] = [
      'type' => 'text',
      'text' => <<<PROMPT
Compare visuellement l'image A à chaque image catalogue (B1…).
Même produit, même modèle, couleur proche ou photo très similaire = score élevé.
Réponds UNIQUEMENT en JSON valide (sans markdown) :
{
  "matches": [
    {"nid": 123, "score": 85, "reason": "court motif de correspondance"}
  ]
}
Le champ "reason" doit être une phrase courte en français uniquement.
Inclus uniquement les nids parmi : {$nid_list}
score entier 0-100. N'inclus que score >= 40.
PROMPT,
    ];

    try {
      $response = $this->aiClient->sendMessages([
        [
          'role' => 'user',
          'content' => $content,
        ],
      ], ['max_tokens' => 1500]);
    }
    catch (\RuntimeException $e) {
      \Drupal::logger('mz_api_integration')->error('compare batch: @msg', ['@msg' => $e->getMessage()]);
      return [];
    }

    $text = $this->aiClient->extractText($response);
    if ($text === '') {
      return [];
    }

    return $this->parseMatchResponse($text, array_values($index_map));
  }

  /**
   * @param int[] $allowed_nids
   *
   * @return array<int, array{score: int, reason: string}>
   */
  protected function parseMatchResponse(string $text, array $allowed_nids): array {
    $text = trim($text);
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
      $text = trim($matches[1]);
    }
    $parsed = json_decode($text, TRUE);
    if (!is_array($parsed)) {
      return [];
    }

    $items = $parsed['matches'] ?? $parsed;
    if (!is_array($items)) {
      return [];
    }

    $allowed = array_flip($allowed_nids);
    $out = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $nid = (int) ($item['nid'] ?? 0);
      if ($nid < 1 || !isset($allowed[$nid])) {
        continue;
      }
      $score = (int) round((float) ($item['score'] ?? 0));
      $score = max(0, min(100, $score));
      $out[$nid] = [
        'score' => $score,
        'reason' => trim((string) ($item['reason'] ?? $item['match_reason'] ?? '')),
      ];
    }
    return $out;
  }

  /**
   * Score texte field_search_image vs analyse upload (0-100).
   *
   * @param array{keywords?: string[], title_guess?: string, category_guess?: string} $analysis
   */
  protected function scoreTextMatch(array $analysis, string $search_text): int {
    return $this->scoreTextMatchDetailed($analysis, $search_text)['score'];
  }

  /**
   * @param array{keywords?: string[], title_guess?: string, category_guess?: string} $analysis
   *
   * @return array{score: int, matched: string[]}
   */
  protected function scoreTextMatchDetailed(array $analysis, string $search_text): array {
    $search_text = trim($search_text);
    if ($search_text === '') {
      return ['score' => 0, 'matched' => []];
    }
    $haystack = mb_strtolower($search_text);
    $needles = $this->extractNeedlesFromAnalysis($analysis);
    if (empty($needles)) {
      return ['score' => 0, 'matched' => []];
    }
    $score = 0;
    $matched = [];

    $sku = trim((string) ($analysis['sku_guess'] ?? ''));
    if ($sku !== '' && mb_strlen($sku) >= 3) {
      $sku_lower = mb_strtolower($sku);
      if (mb_strpos($haystack, $sku_lower) !== FALSE) {
        $score += 45;
        $matched[] = 'SKU ' . $sku;
      }
    }

    $title = trim((string) ($analysis['title_guess'] ?? ''));
    if ($title !== '' && mb_strlen($title) >= 3) {
      $title_lower = mb_strtolower($title);
      if (mb_strpos($haystack, $title_lower) !== FALSE) {
        $score += 18;
        $matched[] = $title;
      }
    }

    $category = trim((string) ($analysis['category_guess'] ?? ''));
    if ($category !== '' && mb_strlen($category) >= 3) {
      $category_lower = mb_strtolower($category);
      if (mb_strpos($haystack, $category_lower) !== FALSE) {
        $score += 14;
        $matched[] = $category;
      }
    }

    foreach ($needles as $needle) {
      if ($needle === $title || $needle === $category || $needle === $sku) {
        continue;
      }
      if (mb_strpos($haystack, mb_strtolower($needle)) !== FALSE) {
        $score += 10;
        $matched[] = $needle;
      }
    }
    return [
      'score' => min(100, $score),
      'matched' => array_values(array_unique($matched)),
    ];
  }

  /**
   * @param array{
   *   keywords?: string[],
   *   title_guess?: string,
   *   category_guess?: string,
   *   colors?: string[],
   *   materials?: string[]
   * } $analysis
   *
   * @return string[]
   */
  /**
   * @param array{public_only?: bool, category_tid?: int} $options
   */
  protected function applySearchQueryOptions($query, array $options): void {
    if (!empty($options['public_only'])) {
      $query->condition('field_quantite_disponible', 0, '>');
      $status_group = $query->orConditionGroup();
      $status_group->condition('field_status', ['dispo', 'disponible'], 'IN');
      $status_group->notExists('field_status');
      $query->condition($status_group);
    }

    $category_tid = (int) ($options['category_tid'] ?? 0);
    if ($category_tid > 0) {
      $query->condition('field_category.target_id', $category_tid);
    }
  }

  /**
   * Correspondance rapide par SKU détecté sur la photo.
   *
   * @param array{public_only?: bool, category_tid?: int} $options
   */
  protected function searchBySkuGuess(string $sku, int $resultLimit, string $bundle, array $options, array $analysis): array {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->condition('field_sku', '%' . $sku . '%', 'LIKE');

    $this->applySearchQueryOptions($query, $options);
    $query->sort('changed', 'DESC');
    $query->range(0, max(5, $resultLimit));

    $nids = $query->execute();
    if (empty($nids)) {
      return ['matches' => [], 'scanned' => 0];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);
    $matches = [];
    $sku_lower = mb_strtolower($sku);

    foreach ($nodes as $node) {
      $node_sku = mb_strtolower(trim((string) ($node->get('field_sku')->value ?? '')));
      if ($node_sku === '') {
        continue;
      }
      $score = 70;
      $reason = 'SKU détecté : ' . $sku;
      if ($node_sku === $sku_lower) {
        $score = 98;
        $reason = 'SKU exact : ' . $sku;
      }
      elseif (mb_strpos($node_sku, $sku_lower) !== FALSE || mb_strpos($sku_lower, $node_sku) !== FALSE) {
        $score = 88;
      }

      if ($node->hasField('field_search_image') && !$node->get('field_search_image')->isEmpty()) {
        $text_detail = $this->scoreTextMatchDetailed($analysis, (string) $node->get('field_search_image')->value);
        if ($text_detail['score'] > 0) {
          $score = min(100, $score + (int) round($text_detail['score'] * 0.2));
        }
      }

      $matches[(int) $node->id()] = [
        'score' => $score,
        'reason' => $reason,
      ];
    }

    uasort($matches, static function ($a, $b) {
      return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });

    if ($resultLimit > 0) {
      $matches = array_slice($matches, 0, $resultLimit, TRUE);
    }

    return [
      'matches' => $matches,
      'scanned' => count($nids),
    ];
  }

  protected function extractNeedlesFromAnalysis(array $analysis): array {
    $needles = [];
    $sku = trim((string) ($analysis['sku_guess'] ?? ''));
    if ($sku !== '' && mb_strlen($sku) >= 3) {
      $needles[] = $sku;
    }
    foreach (['title_guess', 'category_guess', 'description_short'] as $key) {
      $value = trim((string) ($analysis[$key] ?? ''));
      if ($value !== '' && mb_strlen($value) >= 2) {
        $needles[] = $value;
      }
    }
    foreach (['keywords', 'colors', 'materials'] as $list_key) {
      if (empty($analysis[$list_key]) || !is_array($analysis[$list_key])) {
        continue;
      }
      foreach ($analysis[$list_key] as $item) {
        $item = trim((string) $item);
        if ($item !== '' && mb_strlen($item) >= 2) {
          $needles[] = $item;
        }
      }
    }
    return array_values(array_unique($needles));
  }

  /**
   * @param string[] $matched
   */
  protected function formatTextMatchReason(array $matched): string {
    if (empty($matched)) {
      return 'Correspondance dans field_search_image';
    }
    $sample = array_slice($matched, 0, 5);
    return 'Correspondance : ' . implode(', ', $sample);
  }

  /**
   * @return array<int, array{nid: int, title: string, binary: string, mime: string, search_text: string}>
   */
  protected function loadProductCandidates(int $max): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'product')
      ->condition('status', 1)
      ->exists('field_media_image')
      ->sort('changed', 'DESC')
      ->range(0, $max * 2)
      ->execute();

    if (empty($nids)) {
      return [];
    }

    $candidates = [];
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);
    foreach ($nodes as $node) {
      if (count($candidates) >= $max) {
        break;
      }
      $image = $this->loadPrimaryImageFromNode($node);
      if ($image === NULL) {
        continue;
      }
      $search_text = '';
      if ($node->hasField('field_search_image') && !$node->get('field_search_image')->isEmpty()) {
        $search_text = (string) $node->get('field_search_image')->value;
      }
      $candidates[] = [
        'nid' => (int) $node->id(),
        'title' => $node->getTitle(),
        'binary' => $image['binary'],
        'mime' => $image['mime'],
        'search_text' => $search_text,
      ];
    }

    return $candidates;
  }

  /**
   * field_media_image (media → file), sinon field_images[0].
   *
   * @return array{binary: string, mime: string}|null
   */
  protected function loadPrimaryImageFromNode(NodeInterface $node): ?array {
    if ($node->hasField('field_media_image') && !$node->get('field_media_image')->isEmpty()) {
      $media = $node->get('field_media_image')->entity;
      $from_media = $this->loadImageFromMedia($media);
      if ($from_media !== NULL) {
        return $from_media;
      }
    }

    if ($node->hasField('field_images') && !$node->get('field_images')->isEmpty()) {
      foreach ($node->get('field_images') as $item) {
        $media = $item->entity;
        $from_media = $this->loadImageFromMedia($media);
        if ($from_media !== NULL) {
          return $from_media;
        }
      }
    }

    return NULL;
  }

  /**
   * @param \Drupal\media\MediaInterface|\Drupal\Core\Entity\EntityInterface|null $media
   *
   * @return array{binary: string, mime: string}|null
   */
  protected function loadImageFromMedia($media): ?array {
    if (!$media instanceof MediaInterface) {
      return NULL;
    }
    if (!$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return NULL;
    }
    $file = $media->get('field_media_image')->entity;
    return $this->loadImageFromFile($file);
  }

  /**
   * @return array{binary: string, mime: string}|null
   */
  protected function loadImageFromFile($file): ?array {
    if (!$file instanceof FileInterface) {
      return NULL;
    }
    $uri = $file->getFileUri();
    $path = $this->fileSystem->realpath($uri);
    if (!$path || !is_readable($path)) {
      return NULL;
    }
    $binary = @file_get_contents($path);
    if ($binary === FALSE || $binary === '') {
      return NULL;
    }
    if (strlen($binary) > 4 * 1024 * 1024) {
      return NULL;
    }
    $mime = $file->getMimeType() ?: 'image/jpeg';
    return [
      'binary' => $binary,
      'mime' => $this->normalizeMime($mime),
    ];
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
