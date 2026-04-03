<?php

namespace Drupal\eroso_store\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Psr\Log\LoggerInterface;

/**
 * Drush commands for eroso_store migrations.
 */
class ErosoStoreCommands extends DrushCommands {

  /**
   * Constructs command service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $channelLogger,
    protected FileSystemInterface $fileSystem,
    protected FileRepositoryInterface $fileRepository,
  ) {
    parent::__construct();
  }

  /**
   * Import products API into product_store nodes.
   *
   * @command eroso-store:import-products
   * @aliases esip
   * @option url Source JSON endpoint URL.
   * @option limit Max items to process (0 = all).
   * @option offset Skip first N items.
   * @option update-existing Update existing nodes found by SKU.
   * @option dry-run Validate and log only, without saving.
   * @option input-file Local JSON file path as source (fallback when API is down).
   * @option batch-size Number of items per batch insert/update.
   * @option api-page-limit Remote API page size (limit query param).
   * @usage drush eroso-store:import-products
   * @usage drush eroso-store:import-products --limit=100 --offset=200
   * @usage drush eroso-store:import-products --dry-run=1
   * @usage drush eroso-store:import-products --input-file=/tmp/products.json
   * @usage drush eroso-store:import-products --batch-size=100
   * @usage drush eroso-store:import-products --api-page-limit=50
   */
  public function importProducts(array $options = [
    'url' => 'https://www.eroso.mg/api/products',
    'limit' => 0,
    'offset' => 0,
    'update-existing' => 1,
    'dry-run' => 0,
    'retries' => 4,
    'input-file' => '',
    'batch-size' => 100,
    'api-page-limit' => 50,
  ]) : int {
    $url = (string) $options['url'];
    $input_file = trim((string) ($options['input-file'] ?? ''));
    $limit = (int) $options['limit'];
    $offset = max(0, (int) $options['offset']);
    $update_existing = ((int) $options['update-existing']) === 1;
    $dry_run = ((int) $options['dry-run']) === 1;
    $retries_raw = $options['retries'] ?? NULL;
    $retries = ($retries_raw === NULL || $retries_raw === '') ? 4 : max(0, (int) $retries_raw);
    $batch_size = max(1, (int) ($options['batch-size'] ?? 100));
    $api_page_limit = max(1, (int) ($options['api-page-limit'] ?? 50));

    $run_id = date('Ymd-His') . '-' . substr(sha1(uniqid('', TRUE)), 0, 8);
    $log_file = $this->initHistoryLogFile();
    $this->appendHistory($log_file, [
      'run_id' => $run_id,
      'event' => 'start',
      'url' => $url,
      'limit' => $limit,
      'offset' => $offset,
      'update_existing' => $update_existing,
      'dry_run' => $dry_run,
      'batch_size' => $batch_size,
      'api_page_limit' => $api_page_limit,
      'timestamp' => date(DATE_ATOM),
    ]);

    if ($input_file !== '') {
      $this->io()->writeln("Reading products from file: $input_file");
      $payload = $this->loadJsonFromFile($input_file);
      $items = $this->extractItems($payload);
    }
    else {
      $this->io()->writeln("Fetching products from paginated API: $url");
      $items = $this->loadAllDetailsFromPaginatedList($url, $retries, $api_page_limit, $offset, $limit);
      // Already applied offset/limit at fetch phase.
      $offset = 0;
      $limit = 0;
    }

    if ($offset > 0) {
      $items = array_slice($items, $offset);
    }
    if ($limit > 0) {
      $items = array_slice($items, 0, $limit);
    }

    $total = count($items);
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $failed = 0;

    $batch_count = (int) ceil($total / $batch_size);
    $this->io()->writeln("Processing $total items in $batch_count batches (size=$batch_size)...");
    $processed = 0;
    foreach (array_chunk($items, $batch_size, TRUE) as $batch_idx => $batch_items) {
      $batch_no = $batch_idx + 1;
      $batch_created = 0;
      $batch_updated = 0;
      $batch_skipped = 0;
      $batch_failed = 0;

      $this->io()->writeln("Batch $batch_no/$batch_count: " . count($batch_items) . ' items');
      foreach ($batch_items as $index => $item) {
        try {
          $result = $this->importSingleItem($item, $update_existing, $dry_run);
          if ($result['status'] === 'created') {
            $created++;
            $batch_created++;
          }
          elseif ($result['status'] === 'updated') {
            $updated++;
            $batch_updated++;
          }
          else {
            $skipped++;
            $batch_skipped++;
          }

          $this->appendHistory($log_file, [
            'run_id' => $run_id,
            'event' => 'item',
            'batch' => $batch_no,
            'index' => $index,
            'status' => $result['status'],
            'nid' => $result['nid'] ?? NULL,
            'title' => $result['title'] ?? NULL,
            'sku' => $result['sku'] ?? NULL,
            'message' => $result['message'] ?? '',
            'timestamp' => date(DATE_ATOM),
          ]);
        }
        catch (\Throwable $e) {
          $failed++;
          $batch_failed++;
          $this->appendHistory($log_file, [
            'run_id' => $run_id,
            'event' => 'item_error',
            'batch' => $batch_no,
            'index' => $index,
            'message' => $e->getMessage(),
            'timestamp' => date(DATE_ATOM),
          ]);
        }
        $processed++;
      }

      $this->appendHistory($log_file, [
        'run_id' => $run_id,
        'event' => 'batch_end',
        'batch' => $batch_no,
        'batch_total' => count($batch_items),
        'batch_created' => $batch_created,
        'batch_updated' => $batch_updated,
        'batch_skipped' => $batch_skipped,
        'batch_failed' => $batch_failed,
        'processed' => $processed,
        'remaining' => max(0, $total - $processed),
        'timestamp' => date(DATE_ATOM),
      ]);

      // Keep memory stable for large imports.
      $this->entityTypeManager->getStorage('node')->resetCache();
      gc_collect_cycles();
    }

    $summary = [
      'run_id' => $run_id,
      'event' => 'end',
      'total' => $total,
      'created' => $created,
      'updated' => $updated,
      'skipped' => $skipped,
      'failed' => $failed,
      'timestamp' => date(DATE_ATOM),
      'history_file' => $log_file,
    ];

    $this->appendHistory($log_file, $summary);
    $this->channelLogger->notice('eroso_store migration run @run finished. total=@total created=@created updated=@updated skipped=@skipped failed=@failed', [
      '@run' => $run_id,
      '@total' => $total,
      '@created' => $created,
      '@updated' => $updated,
      '@skipped' => $skipped,
      '@failed' => $failed,
    ]);

    $this->io()->success("Done. Created=$created Updated=$updated Skipped=$skipped Failed=$failed");
    $this->io()->writeln("History log: $log_file");
    return $failed > 0 ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }

  /**
   * Import one product item.
   */
  protected function importSingleItem(array $item, bool $update_existing, bool $dry_run) : array {
    $fields = is_array($item['fields'] ?? NULL) ? $item['fields'] : [];
    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
      $title = 'Product store ' . date('Y-m-d H:i:s');
    }
    $sku = (string) ($this->extractScalar($fields, 'field_sku', 'value') ?? '');

    $node = NULL;
    if ($sku !== '') {
      $node = $this->loadBySku($sku);
    }

    $status = 'created';
    if ($node) {
      if (!$update_existing) {
        return [
          'status' => 'skipped',
          'title' => $title,
          'sku' => $sku,
          'message' => 'Existing SKU, update disabled',
        ];
      }
      $status = 'updated';
    }
    elseif (!$dry_run) {
      $node = Node::create([
        'type' => 'product_store',
        'title' => $title,
      ]);
    }

    if ($dry_run) {
      return ['status' => $node ? 'updated' : 'created', 'title' => $title, 'sku' => $sku, 'message' => 'dry-run'];
    }

    $node->setTitle($title);
    if (isset($item['langcode']) && is_string($item['langcode']) && $item['langcode'] !== '') {
      $node->set('langcode', $item['langcode']);
    }
    if (isset($item['status'])) {
      $node->setPublished(((int) $item['status']) === 1);
    }
    if (!empty($item['created']) && is_numeric($item['created'])) {
      $node->setCreatedTime((int) $item['created']);
    }

    $this->applyFieldMappings($node, $fields);
    $this->attachImages($node, $item, $fields);
    $node->save();

    return [
      'status' => $status,
      'nid' => (int) $node->id(),
      'title' => $title,
      'sku' => $sku,
      'message' => $status === 'created' ? 'Node created' : 'Node updated',
    ];
  }

  /**
   * Apply generic field mappings from API payload.
   */
  protected function applyFieldMappings(Node $node, array $fields) : void {
    $ignore = [
      'revision_default',
      'metatag',
      'path',
      'menu_link',
      'is_deploy',
      'medias',
    ];

    foreach ($fields as $field_name => $items) {
      if (in_array($field_name, $ignore, TRUE) || !$node->hasField($field_name) || !is_array($items)) {
        continue;
      }
      if ($items === []) {
        continue;
      }

      $normalized = [];
      foreach ($items as $item) {
        if (!is_array($item)) {
          continue;
        }
        if (array_key_exists('value', $item)) {
          $normalized[] = ['value' => $item['value']];
          continue;
        }
        if (isset($item['target_id']) && isset($item['target_revision_id'])) {
          $normalized[] = [
            'target_id' => $item['target_id'],
            'target_revision_id' => $item['target_revision_id'],
          ];
          continue;
        }
        if (isset($item['target_id'])) {
          $normalized[] = ['target_id' => $item['target_id']];
          continue;
        }
        if (isset($item['uri'])) {
          $entry = ['uri' => $item['uri']];
          if (isset($item['title'])) {
            $entry['title'] = $item['title'];
          }
          if (isset($item['options'])) {
            $entry['options'] = $item['options'];
          }
          $normalized[] = $entry;
          continue;
        }
      }

      if ($normalized !== []) {
        $node->set($field_name, $normalized);
      }
    }
  }

  /**
   * Attach images from remote payload if image fields exist.
   */
  protected function attachImages(Node $node, array $item, array $fields) : void {
    $urls = $this->extractImageUrls($item, $fields);
    if ($urls === []) {
      return;
    }

    $fids = [];
    foreach ($urls as $url) {
      $fid = $this->downloadImage($url);
      if ($fid) {
        $fids[] = $fid;
      }
    }
    if ($fids === []) {
      return;
    }

    if ($node->hasField('field_image')) {
      $node->set('field_image', [['target_id' => $fids[0], 'alt' => $node->label()]]);
    }
    if ($node->hasField('field_images')) {
      $this->attachToFieldAsFileOrMedia($node, 'field_images', $fids);
    }
    if ($node->hasField('field_media_image')) {
      $this->attachToFieldAsFileOrMedia($node, 'field_media_image', [$fids[0]]);
    }
  }

  /**
   * Attach downloaded files to field, handling file or media references.
   */
  protected function attachToFieldAsFileOrMedia(Node $node, string $field_name, array $fids) : void {
    if ($fids === [] || !$node->hasField($field_name)) {
      return;
    }
    $field_def = $node->get($field_name)->getFieldDefinition();
    $settings = $field_def->getSettings();
    $target_type = $settings['target_type'] ?? '';

    // Standard file/image references.
    if ($target_type === 'file') {
      $multi = [];
      foreach ($fids as $fid) {
        $multi[] = ['target_id' => (int) $fid];
      }
      $node->set($field_name, $multi);
      return;
    }

    // Media references (e.g. field_images, field_media_image).
    if ($target_type === 'media') {
      $media_items = [];
      foreach ($fids as $fid) {
        $mid = $this->createMediaImageFromFileId((int) $fid, $node->label());
        if ($mid) {
          $media_items[] = ['target_id' => $mid];
        }
      }
      if ($media_items !== []) {
        $node->set($field_name, $media_items);
      }
    }
  }

  /**
   * Download image URL and return file ID.
   */
  protected function downloadImage(string $url) : ?int {
    try {
      [$status, $content] = $this->fetchRawWithFileGetContents($url, 60);
      if ($status !== 200 || $content === '') {
        return NULL;
      }

      $dir = 'public://eroso_store_import';
      $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $path = parse_url($url, PHP_URL_PATH) ?: '';
      $base = basename($path) ?: ('img-' . substr(sha1($url), 0, 12) . '.jpg');
      $dest = $dir . '/' . substr(sha1($url), 0, 12) . '-' . $base;
      $file = $this->fileRepository->writeData($content, $dest, FileSystemInterface::EXISTS_REPLACE);
      if (!$file) {
        return NULL;
      }
      $file->setPermanent();
      $file->save();
      return (int) $file->id();
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Create media:image entity from file id.
   */
  protected function createMediaImageFromFileId(int $fid, string $label = '') : ?int {
    if ($fid <= 0 || !\Drupal::moduleHandler()->moduleExists('media')) {
      return NULL;
    }
    try {
      $name = trim($label) !== '' ? $label : ('Imported image #' . $fid);
      $media = Media::create([
        'bundle' => 'image',
        'name' => $name,
        'field_media_image' => [
          'target_id' => $fid,
          'alt' => $name,
          'title' => $name,
        ],
        'status' => 1,
      ]);
      $media->save();
      return (int) $media->id();
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Extract candidate image URLs from API payload.
   */
  protected function extractImageUrls(array $item, array $fields) : array {
    $candidates = [];

    if (!empty($item['image']) && is_string($item['image'])) {
      $candidates[] = $item['image'];
    }

    if (!empty($fields['medias']) && is_array($fields['medias'])) {
      foreach ($fields['medias'] as $media) {
        if (!is_array($media)) {
          continue;
        }
        foreach (['url', 'src', 'uri'] as $key) {
          if (!empty($media[$key]) && is_string($media[$key])) {
            $candidates[] = $media[$key];
          }
        }
      }
    }

    if (!empty($fields['field_image']) && is_array($fields['field_image'])) {
      foreach ($fields['field_image'] as $entry) {
        if (!is_array($entry)) {
          continue;
        }
        foreach (['url', 'src', 'uri'] as $key) {
          if (!empty($entry[$key]) && is_string($entry[$key])) {
            $candidates[] = $entry[$key];
          }
        }
      }
    }

    if (!empty($fields['field_images']) && is_array($fields['field_images'])) {
      foreach ($fields['field_images'] as $entry) {
        if (!is_array($entry)) {
          continue;
        }
        foreach (['url', 'src', 'uri'] as $key) {
          if (!empty($entry[$key]) && is_string($entry[$key])) {
            $candidates[] = $entry[$key];
          }
        }
      }
    }

    $out = [];
    foreach ($candidates as $url) {
      $u = trim($url);
      if ($u === '') {
        continue;
      }
      if (!preg_match('@^https?://@i', $u)) {
        continue;
      }
      $out[$u] = TRUE;
    }
    return array_keys($out);
  }

  /**
   * Load existing product_store by SKU if available.
   */
  protected function loadBySku(string $sku) : ?Node {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'product_store')
      ->condition('field_sku.value', $sku)
      ->range(0, 1)
      ->accessCheck(FALSE);
    $ids = $query->execute();
    if (!$ids) {
      return NULL;
    }
    $id = (int) reset($ids);
    $node = Node::load($id);
    return $node instanceof Node ? $node : NULL;
  }

  /**
   * Fetch JSON from URL.
   */
  protected function fetchJson(string $url, int $retries = 4, int $timeout = 120) : array {
    $attempt = 0;
    $max_attempts = $retries + 1;
    $last_error = '';

    while ($attempt < $max_attempts) {
      $attempt++;
      try {
        [$status, $body] = $this->fetchRawWithFileGetContents($url, $timeout);

        if ($status >= 500) {
          $last_error = "HTTP $status";
          $this->channelLogger->warning('Attempt @a/@m failed for @url with @status', [
            '@a' => $attempt,
            '@m' => $max_attempts,
            '@url' => $url,
            '@status' => $status,
          ]);
          $this->sleepBackoffMs($attempt);
          continue;
        }

        if ($status < 200 || $status >= 300) {
          throw new \RuntimeException("Unexpected HTTP status $status from endpoint.");
        }

        $decoded = json_decode($body, TRUE);
        if (!is_array($decoded)) {
          $snippet = trim(substr(strip_tags($body), 0, 220));
          if ($snippet !== '') {
            throw new \RuntimeException('Endpoint returned non-JSON response. Snippet: ' . $snippet);
          }
          throw new \RuntimeException('Endpoint did not return a valid JSON object/array.');
        }

        return $decoded;
      }
      catch (\RuntimeException $e) {
        $last_error = $e->getMessage();
        $this->channelLogger->warning('Attempt @a/@m failed for @url: @msg', [
          '@a' => $attempt,
          '@m' => $max_attempts,
          '@url' => $url,
          '@msg' => $last_error,
        ]);
        if ($attempt < $max_attempts) {
          $this->sleepBackoffMs($attempt);
          continue;
        }
      }
    }

    throw new \RuntimeException(
      "Failed to fetch products endpoint after $max_attempts attempts. Last error: $last_error"
    );
  }

  /**
   * Raw HTTP GET using PHP file_get_contents + stream context.
   *
   * @return array{0:int,1:string}
   *   [http_status_code, body]
   */
  protected function fetchRawWithFileGetContents(string $url, int $timeout = 120) : array {
    $ctx = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => $timeout,
        'ignore_errors' => TRUE,
        'header' => implode("\r\n", [
          'Accept: application/json, text/plain;q=0.9, */*;q=0.8',
          'User-Agent: eroso_store_migrator/1.0 (+drush)',
          'Connection: close',
        ]),
      ],
      'ssl' => [
        'verify_peer' => TRUE,
        'verify_peer_name' => TRUE,
      ],
    ]);

    $body = @file_get_contents($url, FALSE, $ctx);
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header_line) {
      if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header_line, $m)) {
        $status = (int) $m[1];
      }
    }
    if ($body === FALSE) {
      $body = '';
    }
    return [$status, $body];
  }

  /**
   * Try primary URL, then common fallback variants.
   */
  protected function fetchJsonWithFallback(string $url, int $retries = 4, int $timeout = 120) : array {
    $candidates = [$url];

    $last_exception = NULL;
    foreach (array_values(array_unique($candidates)) as $candidate) {
      try {
        if ($candidate !== $url) {
          $this->io()->writeln("Trying fallback endpoint: $candidate");
        }
        return $this->fetchJson($candidate, $retries, $timeout);
      }
      catch (\RuntimeException $e) {
        $last_exception = $e;
      }
    }

    throw $last_exception ?? new \RuntimeException('Unable to fetch products endpoint.');
  }

  /**
   * Extract numeric nids from list payload.
   */
  protected function extractNidsFromItems(array $items) : array {
    $nids = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $nid = $item['nid'] ?? $item['id'] ?? NULL;
      if ($nid === NULL || !is_numeric($nid)) {
        continue;
      }
      $nids[(int) $nid] = TRUE;
    }
    return array_keys($nids);
  }

  /**
   * Extract numeric ids/nids from metadata payload.
   */
  protected function extractNidsFromPayload(array $payload) : array {
    $nids = [];

    if (!empty($payload['ids']) && is_array($payload['ids'])) {
      foreach ($payload['ids'] as $id) {
        if (is_numeric($id)) {
          $nids[(int) $id] = TRUE;
        }
      }
    }

    foreach (['nid', 'id'] as $k) {
      if (isset($payload[$k]) && is_numeric($payload[$k])) {
        $nids[(int) $payload[$k]] = TRUE;
      }
    }

    return array_keys($nids);
  }

  /**
   * Load each detail from /api/products/{nid}.
   */
  protected function loadDetailsByNids(string $listUrl, array $nids, int $retries) : array {
    $details = [];
    $base = rtrim(preg_replace('@/api/products/?$@', '', $listUrl), '/');
    $total_nids = count($nids);
    foreach ($nids as $i => $nid) {
      $detail_url = $base . '/api/products/' . (int) $nid;
      try {
        if ($i > 0 && $i % 20 === 0) {
          $this->io()->writeln('  details progress: ' . $i . '/' . $total_nids);
        }
        $detail_payload = $this->fetchJsonWithFallback($detail_url, $retries, 35);
        $detail_items = $this->extractItems($detail_payload);
        if ($detail_items !== []) {
          // Some APIs wrap detail inside data array.
          $details[] = $detail_items[0];
          continue;
        }
        // Or return a single object directly.
        $details[] = $detail_payload;
      }
      catch (\RuntimeException $e) {
        $this->channelLogger->warning('Detail fetch failed for nid @nid: @msg', [
          '@nid' => $nid,
          '@msg' => $e->getMessage(),
        ]);
      }
    }
    return $details;
  }

  /**
   * Load all details by traversing paginated list endpoint.
   *
   * Expected list style:
   * /api/products?include_total=1&limit=50&offset=0
   */
  protected function loadAllDetailsFromPaginatedList(
    string $baseListUrl,
    int $retries,
    int $pageLimit,
    int $startOffset = 0,
    int $globalLimit = 0
  ) : array {
    $all_details = [];
    $offset = max(0, $startOffset);
    $total_hint = NULL;
    $page_no = 0;

    while (TRUE) {
      $page_no++;
      $page_url = $this->buildPaginatedUrl($baseListUrl, $pageLimit, $offset);
      $payload = $this->fetchJsonWithFallback($page_url, $retries, 45);
      $items = $this->extractItems($payload);

      if ($total_hint === NULL) {
        $total_hint = $this->extractTotal($payload);
      }

      $nids = $this->extractNidsFromItems($items);
      if ($nids === []) {
        $nids = $this->extractNidsFromPayload($payload);
      }
      if ($nids !== []) {
        $this->io()->writeln('Page ' . $page_no . ' offset=' . $offset . ': found ' . count($nids) . ' ids');
        $details = $this->loadDetailsByNids($baseListUrl, $nids, $retries);
        $this->io()->writeln('Page ' . $page_no . ': loaded ' . count($details) . ' details');
      }
      else {
        if ($items === []) {
          break;
        }
        $details = $items;
      }

      foreach ($details as $detail) {
        $all_details[] = $detail;
        if ($globalLimit > 0 && count($all_details) >= $globalLimit) {
          return $all_details;
        }
      }

      $offset += $pageLimit;
      if (isset($payload['has_next']) && $payload['has_next'] === FALSE) {
        break;
      }
      if (isset($payload['next_offset']) && is_numeric($payload['next_offset'])) {
        $offset = (int) $payload['next_offset'];
      }
      if (count($nids) > 0 && count($nids) < $pageLimit) {
        break;
      }
      if ($total_hint !== NULL && $offset >= $total_hint) {
        break;
      }
    }

    return $all_details;
  }

  /**
   * Build paginated list URL with include_total, limit and offset.
   */
  protected function buildPaginatedUrl(string $url, int $limit, int $offset) : string {
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
      return $url;
    }

    $query = [];
    if (!empty($parts['query'])) {
      parse_str($parts['query'], $query);
    }
    $query['include_total'] = 1;
    $query['limit'] = $limit;
    $query['offset'] = $offset;

    $path = $parts['path'] ?? '';
    $rebuilt = $parts['scheme'] . '://' . $parts['host'];
    if (!empty($parts['port'])) {
      $rebuilt .= ':' . $parts['port'];
    }
    $rebuilt .= $path . '?' . http_build_query($query);
    return $rebuilt;
  }

  /**
   * Extract total if provided by API.
   */
  protected function extractTotal(array $payload) : ?int {
    foreach (['total', 'count', 'total_count'] as $k) {
      if (isset($payload[$k]) && is_numeric($payload[$k])) {
        return (int) $payload[$k];
      }
    }
    return NULL;
  }

  /**
   * Read JSON payload from local file path.
   */
  protected function loadJsonFromFile(string $path) : array {
    if (!is_file($path) || !is_readable($path)) {
      throw new \RuntimeException("Input file not found or not readable: $path");
    }
    $raw = file_get_contents($path);
    if ($raw === FALSE || trim($raw) === '') {
      throw new \RuntimeException("Input file is empty: $path");
    }
    $decoded = json_decode($raw, TRUE);
    if (!is_array($decoded)) {
      throw new \RuntimeException("Input file is not a valid JSON object/array: $path");
    }
    return $decoded;
  }

  /**
   * Exponential backoff with small cap.
   */
  protected function sleepBackoffMs(int $attempt) : void {
    $ms = min(15000, 1000 * (2 ** max(0, $attempt - 1)));
    usleep($ms * 1000);
  }

  /**
   * Extract list of product items from flexible payload formats.
   */
  protected function extractItems(array $payload) : array {
    if ($this->isListArray($payload)) {
      return $payload;
    }
    foreach (['rows', 'data', 'items', 'products'] as $key) {
      if (!empty($payload[$key]) && is_array($payload[$key]) && $this->isListArray($payload[$key])) {
        return $payload[$key];
      }
    }
    return [];
  }

  /**
   * Determine if array is list-like.
   */
  protected function isListArray(array $arr) : bool {
    if ($arr === []) {
      return TRUE;
    }
    return array_keys($arr) === range(0, count($arr) - 1);
  }

  /**
   * Extract scalar from Drupal list field payload.
   */
  protected function extractScalar(array $fields, string $field_name, string $key) {
    if (empty($fields[$field_name]) || !is_array($fields[$field_name])) {
      return NULL;
    }
    $first = $fields[$field_name][0] ?? NULL;
    if (!is_array($first)) {
      return NULL;
    }
    return $first[$key] ?? NULL;
  }

  /**
   * Initialize JSONL history log destination.
   */
  protected function initHistoryLogFile() : string {
    $dir = 'public://eroso_store_migration_logs';
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    return $dir . '/history-' . date('Y-m') . '.jsonl';
  }

  /**
   * Append one JSON line to migration history.
   */
  protected function appendHistory(string $uri, array $payload) : void {
    $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $path = $this->fileSystem->realpath($uri);
    if (!$path) {
      return;
    }
    file_put_contents($path, $line, FILE_APPEND);
  }

}

