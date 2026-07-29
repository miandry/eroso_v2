<?php

namespace Drupal\mz_claude_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mz_claude_api\Service\ProductImageAnalyzer;
use Drupal\mz_claude_api\Service\ProductImageMatcher;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Recherche produits boutique par analyse IA + field_search_image.
 */
class ProductImageSearchController extends ControllerBase {

  public function __construct(
    protected ProductImageAnalyzer $imageAnalyzer,
    protected ProductImageMatcher $imageMatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('mz_claude_api.product_image_analyzer'),
      $container->get('mz_claude_api.product_image_matcher'),
    );
  }

  /**
   * POST multipart (image) ou JSON { image_base64, mime_type, token? }.
   */
  public function search(Request $request): JsonResponse {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => FALSE, 'message' => 'POST required'], 405);
    }

    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body)) {
      $body = [];
    }
    if ($request->request->count()) {
      $body = array_merge($body, $request->request->all());
    }

    $user = $this->authenticateRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Non autorisé'], 401);
    }

    try {
      [$binary, $mime] = $this->extractImagePayload($request, $body);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['status' => FALSE, 'message' => $e->getMessage()], 400);
    }

    if (strlen($binary) > 8 * 1024 * 1024) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Image trop volumineuse (max 8 Mo).'], 413);
    }

    $limit = (int) ($body['limit'] ?? $request->query->get('limit', 20));
    if ($limit < 1 || $limit > 40) {
      $limit = 20;
    }

    $analysis = NULL;
    $search_text = '';
    try {
      $analysis = $this->imageAnalyzer->analyze($binary, $mime);
      $search_text = $this->imageAnalyzer->formatSearchImageText($analysis);
    }
    catch (\RuntimeException $e) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => $e->getMessage(),
      ], 502);
    }

    $bundle = trim((string) ($body['bundle'] ?? $request->query->get('bundle', 'product')));
    if (!in_array($bundle, ['product', 'product_commande'], TRUE)) {
      $bundle = 'product';
    }

    try {
      $match_result = $this->imageMatcher->searchByFieldSearchImage($analysis, $limit, $bundle);
    }
    catch (\RuntimeException $e) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => $e->getMessage(),
      ], 502);
    }

    $matches = $match_result['matches'] ?? [];
    if (empty($matches)) {
      return new JsonResponse([
        'status' => TRUE,
        'provider' => 'claude',
        'mode' => 'field_search_image',
        'field_search_image' => $search_text,
        'analysis' => $analysis,
        'scanned' => $match_result['scanned'] ?? 0,
        'total' => 0,
        'rows' => [],
        'message' => $bundle === 'product_commande'
          ? 'Aucun produit trouvé dans field_search_image. Ajoutez des produits avec texte IA via /sur-commande/product-insert.'
          : 'Aucun produit trouvé dans field_search_image. Ajoutez des produits avec texte IA via /product-insert.',
      ]);
    }

    $parser = \Drupal::service('entity_parser.manager');
    $rows = [];
    foreach ($matches as $nid => $info) {
      try {
        $row = $parser->loader_entity_by_type((int) $nid, 'node');
        if (!is_array($row)) {
          continue;
        }
        if (!isset($row['nid'])) {
          $row['nid'] = (int) $nid;
        }
        $row['_ai_score'] = (int) ($info['score'] ?? 0);
        $row['_ai_match_reason'] = (string) ($info['reason'] ?? '');
        $rows[] = $row;
      }
      catch (\Throwable $e) {
        \Drupal::logger('mz_claude_api')->warning('image-search parse nid @nid: @msg', [
          '@nid' => $nid,
          '@msg' => $e->getMessage(),
        ]);
      }
    }

    usort($rows, static function ($a, $b) {
      return ($b['_ai_score'] ?? 0) <=> ($a['_ai_score'] ?? 0);
    });

    return new JsonResponse([
      'status' => TRUE,
      'provider' => 'claude',
      'mode' => 'field_search_image',
      'field_search_image' => $search_text,
      'analysis' => $analysis,
      'scanned' => $match_result['scanned'] ?? 0,
      'total' => count($rows),
      'rows' => array_values($rows),
    ]);
  }

  /**
   * POST multipart (image) — génère field_search_image via Claude Vision.
   */
  public function analyzeForSearch(Request $request): JsonResponse {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => FALSE, 'message' => 'POST required'], 405);
    }

    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body)) {
      $body = [];
    }
    if ($request->request->count()) {
      $body = array_merge($body, $request->request->all());
    }

    $user = $this->authenticateRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Non autorisé'], 401);
    }

    try {
      [$binary, $mime] = $this->extractImagePayload($request, $body);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['status' => FALSE, 'message' => $e->getMessage()], 400);
    }

    if (strlen($binary) > 8 * 1024 * 1024) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Image trop volumineuse (max 8 Mo).'], 413);
    }

    try {
      $analysis = $this->imageAnalyzer->analyze($binary, $mime);
      $search_text = $this->imageAnalyzer->formatSearchImageText($analysis);
    }
    catch (\RuntimeException $e) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => $e->getMessage(),
      ], 502);
    }

    return new JsonResponse([
      'status' => TRUE,
      'provider' => 'claude',
      'field_search_image' => $search_text,
      'analysis' => $analysis,
    ]);
  }

  /**
   * POST — génère et enregistre field_search_image pour un produit boutique.
   *
   * Multipart « image » optionnel (nouvelle photo non encore sauvegardée).
   */
  public function generateForProduct(Request $request, int $nid): JsonResponse {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => FALSE, 'message' => 'POST required'], 405);
    }

    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body)) {
      $body = [];
    }
    if ($request->request->count()) {
      $body = array_merge($body, $request->request->all());
    }

    $user = $this->authenticateRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Non autorisé'], 401);
    }

    $node = Node::load($nid);
    if (!$node || !in_array($node->bundle(), ['product', 'product_commande'], TRUE)) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Produit introuvable.'], 404);
    }
    if (!$node->hasField('field_search_image')) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Champ field_search_image absent.'], 400);
    }

    $search_text = NULL;
    $analysis = NULL;

    try {
      $files = $request->files;
      $upload = $files->get('image') ?? $files->get('file');
      if ($upload instanceof UploadedFile && $upload->isValid()) {
        $binary = (string) file_get_contents($upload->getPathname());
        $mime = $upload->getMimeType() ?: 'image/jpeg';
        if ($binary === '') {
          throw new \InvalidArgumentException('Fichier image vide.');
        }
        if (strlen($binary) > 8 * 1024 * 1024) {
          return new JsonResponse(['status' => FALSE, 'message' => 'Image trop volumineuse (max 8 Mo).'], 413);
        }
        $analysis = $this->imageAnalyzer->analyze($binary, $mime);
        $search_text = $this->imageAnalyzer->formatSearchImageText($analysis);
      }
      else {
        $data = $this->imageMatcher->buildSearchImageDataForNode($node);
        if ($data !== NULL) {
          $search_text = $data['field_search_image'];
          $analysis = $data['analysis'] ?? NULL;
        }
      }
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['status' => FALSE, 'message' => $e->getMessage()], 400);
    }
    catch (\RuntimeException $e) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => $e->getMessage(),
      ], 502);
    }

    if ($search_text === NULL || $search_text === '') {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Impossible de générer le texte : aucune image produit disponible.',
      ], 400);
    }

    $node->set('field_search_image', [
      'value' => $search_text,
      'format' => 'plain_text',
    ]);
    $node->save();

    return new JsonResponse([
      'status' => TRUE,
      'provider' => 'claude',
      'nid' => (int) $nid,
      'field_search_image' => $search_text,
      'analysis' => $analysis,
    ]);
  }

  /**
   * @return array{0: string, 1: string}
   */
  protected function extractImagePayload(Request $request, array $body): array {
    $files = $request->files;
    $upload = $files->get('image') ?? $files->get('file');
    if ($upload instanceof UploadedFile && $upload->isValid()) {
      $binary = (string) file_get_contents($upload->getPathname());
      $mime = $upload->getMimeType() ?: 'image/jpeg';
      if ($binary === '') {
        throw new \InvalidArgumentException('Fichier image vide.');
      }
      return [$binary, $mime];
    }

    $b64 = $body['image_base64'] ?? $body['image'] ?? '';
    if (!is_string($b64) || trim($b64) === '') {
      throw new \InvalidArgumentException('Image requise (multipart « image » ou JSON image_base64).');
    }

    if (preg_match('/^data:(image\/[a-z0-9.+-]+);base64,(.+)$/i', $b64, $m)) {
      $mime = $m[1];
      $b64 = $m[2];
    }
    else {
      $mime = (string) ($body['mime_type'] ?? 'image/jpeg');
    }

    $binary = base64_decode($b64, TRUE);
    if ($binary === FALSE || $binary === '') {
      throw new \InvalidArgumentException('image_base64 invalide.');
    }

    return [$binary, $mime];
  }

  /**
   * Auth token (cookie, Bearer, body).
   */
  private function authenticateRequest(Request $request, $body = NULL) {
    $token = $request->cookies->get('auth_token');
    if (!$token) {
      $auth_header = $request->headers->get('Authorization');
      if ($auth_header && preg_match('/Bearer\s+(.+)/i', $auth_header, $matches)) {
        $token = trim($matches[1]);
      }
    }
    if (!$token) {
      $token = $request->headers->get('X-Auth-Token');
      if (is_string($token)) {
        $token = trim($token);
      }
      else {
        $token = NULL;
      }
    }
    if (!$token && $request->query->has('token')) {
      $token = trim((string) $request->query->get('token'));
    }
    if (!$token && is_array($body) && !empty($body['token'])) {
      $token = $body['token'];
    }
    if (!$token && $request->request->has('token')) {
      $token = $request->request->get('token');
    }
    if (!$token || $token === '') {
      return NULL;
    }
    if (!\Drupal::hasService('api_solutions.api_crud')) {
      return NULL;
    }
    return \Drupal::service('api_solutions.api_crud')->validateBearerToken($token);
  }

}
