<?php

namespace Drupal\eroso_store\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Import product_store from JSON payload.
 */
class ProductStoreImportController extends ControllerBase {

  /**
   * Import endpoint.
   */
  public function import(Request $request) {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => FALSE, 'message' => 'POST required'], 405);
    }

    $raw = $request->getContent();
    $body = json_decode($raw, TRUE);
    if (!is_array($body)) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Invalid JSON body'], 400);
    }

    $user = $this->authenticateRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Token invalide ou session expirée'], 401);
    }

    $fields = is_array($body['fields'] ?? NULL) ? $body['fields'] : [];
    $title = trim((string) ($body['title'] ?? ''));
    if ($title === '') {
      $title = 'Product store - ' . date('Y-m-d H:i');
    }

    try {
      $node = Node::create([
        'type' => 'product_store',
        'title' => $title,
        'uid' => $user->id(),
      ]);

      $this->applyBaseFields($node, $body);
      $this->applyMappedFields($node, $fields);
      $node->save();

      return new JsonResponse([
        'status' => TRUE,
        'nid' => (int) $node->id(),
        'message' => 'product_store created',
      ]);
    }
    catch (\Throwable $e) {
      \Drupal::logger('eroso_store')->error('Import failed: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Erreur import product_store: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Authenticate request using cookie/header/body token.
   */
  private function authenticateRequest(Request $request, ?array $body = NULL) {
    $token = $request->cookies->get('auth_token');

    if (!$token) {
      $auth_header = $request->headers->get('Authorization');
      if ($auth_header && preg_match('/Bearer\s+(.+)/i', $auth_header, $matches)) {
        $token = trim($matches[1]);
      }
    }

    if (!$token && is_array($body)) {
      $token = $body['token'] ?? NULL;
    }

    if (!$token) {
      return NULL;
    }

    $service = \Drupal::service('api_solutions.api_crud');
    return $service->validateBearerToken($token);
  }

  /**
   * Apply base node fields.
   */
  private function applyBaseFields(Node $node, array $body) : void {
    if (array_key_exists('langcode', $body) && is_string($body['langcode']) && $body['langcode'] !== '') {
      $node->set('langcode', $body['langcode']);
    }
    if (array_key_exists('status', $body)) {
      $node->setPublished(((int) $body['status']) === 1);
    }
    if (!empty($body['created']) && is_numeric($body['created'])) {
      $node->setCreatedTime((int) $body['created']);
    }
  }

  /**
   * Map selected JSON fields into product_store fields.
   */
  private function applyMappedFields(Node $node, array $fields) : void {
    $sku = $this->extractScalar($fields, 'field_sku', 'value');
    if ($sku !== NULL && $node->hasField('field_sku')) {
      $node->set('field_sku', (string) $sku);
    }

    $prix_achat = $this->extractScalar($fields, 'field_prix_achat', 'value');
    if ($prix_achat !== NULL && $node->hasField('field_prix_achat')) {
      $node->set('field_prix_achat', (float) $prix_achat);
    }

    $transport = $this->extractScalar($fields, 'field_transport_en_chine', 'value');
    if ($transport !== NULL && $node->hasField('field_transport_en_chine')) {
      $node->set('field_transport_en_chine', (float) $transport);
    }

    $catalogue_tid = $this->extractScalar($fields, 'field_catalogue', 'target_id');
    if ($catalogue_tid !== NULL && $node->hasField('field_catalogue')) {
      $node->set('field_catalogue', [(int) $catalogue_tid]);
    }

    if ($node->hasField('field_taobao')) {
      $text = $this->extractTaobaoUrlText($fields);
      if ($text !== NULL && $text !== '') {
        $node->set('field_taobao', [['value' => $text]]);
      }
    }
  }

  /**
   * Accepts API shapes: plain string, or [{value}], or [{uri}] (legacy link).
   */
  private function extractTaobaoUrlText(array $fields) : ?string {
    foreach (['field_taobao_url', 'field_taobao'] as $key) {
      if (!array_key_exists($key, $fields)) {
        continue;
      }
      $raw = $fields[$key];
      if (is_string($raw)) {
        $raw = trim($raw);
        return $raw !== '' ? $raw : NULL;
      }
      if (!is_array($raw) || $raw === []) {
        continue;
      }
      $first = $raw[0] ?? NULL;
      if (is_array($first)) {
        if (isset($first['value']) && $first['value'] !== '') {
          return (string) $first['value'];
        }
        if (isset($first['uri']) && $first['uri'] !== '') {
          return (string) $first['uri'];
        }
      }
    }
    return NULL;
  }

  /**
   * Extract value from Drupal JSON field array format.
   */
  private function extractScalar(array $fields, string $field_name, string $key) {
    $items = $fields[$field_name] ?? NULL;
    if (!is_array($items) || empty($items) || !is_array($items[0])) {
      return NULL;
    }
    return $items[0][$key] ?? NULL;
  }

}
