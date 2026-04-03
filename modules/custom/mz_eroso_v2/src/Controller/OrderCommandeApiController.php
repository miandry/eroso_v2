<?php

namespace Drupal\mz_eroso_v2\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * JSON API for order_commande (liste, filtres, recherche produit).
 */
class OrderCommandeApiController extends ControllerBase {

  /**
   * Liste paginée des commandes sur commande.
   *
   * Query:
   * - offset : taille de page (défaut 20)
   * - pager : index de page depuis 0
   * - sort[val], sort[op] : tri (défaut created DESC)
   * - filters[field_status_commande][val] : filtre statut (valeur machine)
   * - search : ≥ 2 car. — titre, field_info, nom ou SKU produit (lignes panier)
   * - date_from, date_to : Y-m-d (optionnel) — filtre sur created (timezone site)
   *
   * Réponse : { "rows": [ ... entity_parser node ... ], "total": int }
   */
  public function list(Request $request) {
    try {
      $pager = (int) $request->query->get('pager', 0);
      $offset = (int) $request->query->get('offset', 20);
      if ($offset < 1) {
        $offset = 20;
      }
      if ($pager < 0) {
        $pager = 0;
      }

      $query_params = $request->query->all();
      $sort = isset($query_params['sort']) && is_array($query_params['sort']) ? $query_params['sort'] : [];
      $sort_field = isset($sort['val']) ? (string) $sort['val'] : 'created';
      $sort_dir = isset($sort['op']) ? strtoupper((string) $sort['op']) : 'DESC';
      if (!in_array($sort_field, ['created', 'changed', 'nid', 'title'], TRUE)) {
        $sort_field = 'created';
      }
      if (!in_array($sort_dir, ['ASC', 'DESC'], TRUE)) {
        $sort_dir = 'DESC';
      }

      $filters = isset($query_params['filters']) && is_array($query_params['filters']) ? $query_params['filters'] : [];
      $status = '';
      if (isset($filters['field_status_commande']['val'])) {
        $status = trim((string) $filters['field_status_commande']['val']);
      }

      $search = trim((string) $request->query->get('search', ''));

      $date_from = $this->normalizeDateParam($request->query->get('date_from'));
      $date_to = $this->normalizeDateParam($request->query->get('date_to'));
      $created_min = $date_from ? $this->dayStartTimestamp($date_from) : NULL;
      $created_max = $date_to ? $this->dayEndTimestamp($date_to) : NULL;
      if ($created_min !== NULL && $created_max !== NULL && $created_min > $created_max) {
        $tmp = $created_min;
        $created_min = $created_max;
        $created_max = $tmp;
      }

      $query_count = \Drupal::entityQuery('node')->accessCheck(FALSE);
      $this->applyListConditions($query_count, $status, $search, $created_min, $created_max);
      $total = (int) $query_count->count()->execute();

      $query_list = \Drupal::entityQuery('node')->accessCheck(FALSE);
      $this->applyListConditions($query_list, $status, $search, $created_min, $created_max);
      $nids = $query_list
        ->sort($sort_field, $sort_dir)
        ->range($pager * $offset, $offset)
        ->execute();

      $results = [];
      $parser = \Drupal::service('entity_parser.manager');
      foreach ($nids as $nid) {
        $nid = (int) $nid;
        if ($nid < 1) {
          continue;
        }
        try {
          $row = $parser->loader_entity_by_type($nid, 'node');
          if (!is_array($row)) {
            $row = [];
          }
          if (!isset($row['nid']) && !isset($row['id'])) {
            $row['nid'] = $nid;
          }
          $results[] = $row;
        }
        catch (\Throwable $e) {
          \Drupal::logger('mz_eroso_v2')->warning('order_commande list parse nid @nid: @msg', [
            '@nid' => $nid,
            '@msg' => $e->getMessage(),
          ]);
        }
      }

      return new JsonResponse([
        'rows' => array_values($results),
        'total' => $total,
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('mz_eroso_v2')->error('order_commande list: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'message' => 'Erreur liste commandes',
        'status' => 'error',
      ], 500);
    }
  }

  /**
   * Conditions EntityQuery pour le bundle order_commande.
   */
  private function applyListConditions($query, $status, $search, $created_min = NULL, $created_max = NULL) {
    $query->condition('type', 'order_commande');

    if ($created_min !== NULL) {
      $query->condition('created', $created_min, '>=');
    }
    if ($created_max !== NULL) {
      $query->condition('created', $created_max, '<=');
    }

    if ($status !== '') {
      $query->condition('field_status_commande.value', $status, '=');
    }

    if (strlen($search) >= 2) {
      $or = $query->orConditionGroup();
      $or->condition('title', $search, 'CONTAINS');
      $or->condition('field_info', $search, 'CONTAINS');

      $pq = \Drupal::entityQuery('node')->accessCheck(FALSE)
        ->condition('type', 'product_commande');
      $p_or = $pq->orConditionGroup();
      $p_or->condition('title', $search, 'CONTAINS');
      $p_or->condition('field_sku', $search, 'CONTAINS');
      $pq->condition($p_or);
      $pids = $pq->execute();

      if (!empty($pids)) {
        $pids = array_values($pids);
        $cq = \Drupal::entityQuery('node')->accessCheck(FALSE)
          ->condition('type', 'cart_commande')
          ->condition('field_product_id', $pids, 'IN');
        $cart_ids = $cq->execute();
        if (!empty($cart_ids)) {
          $or->condition('field_carts', array_values($cart_ids), 'IN');
        }
      }

      $query->condition($or);
    }
  }

  /**
   * Valide Y-m-d et retour la chaîne ou NULL.
   */
  private function normalizeDateParam($value): ?string {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    $s = trim((string) $value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
      return NULL;
    }
    $parts = explode('-', $s);
    if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
      return NULL;
    }
    return $s;
  }

  /**
   * Début de journée (timezone Drupal) en timestamp Unix.
   */
  private function dayStartTimestamp(string $ymd): ?int {
    try {
      $tz_name = \Drupal::config('system.date')->get('timezone.default') ?: @date_default_timezone_get() ?: 'UTC';
      $tz = new \DateTimeZone($tz_name);
      $d = new \DateTimeImmutable($ymd . ' 00:00:00', $tz);
      return $d->getTimestamp();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Fin de journée (timezone Drupal) en timestamp Unix.
   */
  private function dayEndTimestamp(string $ymd): ?int {
    try {
      $tz_name = \Drupal::config('system.date')->get('timezone.default') ?: @date_default_timezone_get() ?: 'UTC';
      $tz = new \DateTimeZone($tz_name);
      $d = new \DateTimeImmutable($ymd . ' 23:59:59', $tz);
      return $d->getTimestamp();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}
