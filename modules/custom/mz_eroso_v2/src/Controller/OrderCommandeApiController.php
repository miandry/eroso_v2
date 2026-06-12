<?php

namespace Drupal\mz_eroso_v2\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
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

  /**
   * Transfère des lignes cart_commande vers le catalogue product (+ stock in).
   *
   * POST JSON:
   * - token (optionnel si cookie / Bearer)
   * - order_nid (int)
   * - lines (array) — [{ cart_nid, quantity }] quantité à transférer par ligne
   * - cart_nids (int[], legacy) — transfère la quantité restante de chaque ligne
   *
   * Pour chaque ligne : product_commande → product (match SKU ou création),
   * entrée stock boutique, sortie stock product_commande si applicable,
   * décrémente la quantité cart ; supprime cart / product_commande si qty = 0.
   */
  public function transferCartLinesToBoutique(Request $request) {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => FALSE, 'message' => 'POST required'], 405);
    }

    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body) || empty($body['order_nid'])) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'order_nid est requis',
      ], 400);
    }

    $transfer_lines = $this->parseTransferLinesRequest($body);
    if (empty($transfer_lines)) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'lines ou cart_nids (tableau) est requis',
      ], 400);
    }

    $user = $this->authenticateTransferRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Non autorisé'], 401);
    }

    $roles = $user->getRoles();
    if (!in_array('administrator', $roles, TRUE) && !in_array('content_editor', $roles, TRUE)) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Accès non autorisé'], 403);
    }

    $order = Node::load((int) $body['order_nid']);
    if (!$order || $order->bundle() !== 'order_commande') {
      return new JsonResponse(['status' => FALSE, 'message' => 'Commande introuvable'], 404);
    }

    $order_status = $order->hasField('field_status_commande')
      ? (string) ($order->get('field_status_commande')->value ?? '')
      : '';
    if ($order_status === 'annuler') {
      return new JsonResponse(['status' => FALSE, 'message' => 'Commande annulée'], 422);
    }

    $allowed_cart_nids = [];
    if ($order->hasField('field_carts') && !$order->get('field_carts')->isEmpty()) {
      foreach ($order->get('field_carts') as $item) {
        if (!empty($item->target_id)) {
          $allowed_cart_nids[(int) $item->target_id] = TRUE;
        }
      }
    }

    $results = [];
    $errors = [];
    $order_dirty = FALSE;

    foreach ($transfer_lines as $line_request) {
      $cart_nid = (int) ($line_request['cart_nid'] ?? 0);
      $requested_qty = (int) ($line_request['quantity'] ?? 0);
      if ($cart_nid < 1) {
        continue;
      }

      if (empty($allowed_cart_nids[$cart_nid])) {
        $errors[] = "Ligne $cart_nid : n'appartient pas à cette commande";
        continue;
      }

      $cart = Node::load($cart_nid);
      if (!$cart || $cart->bundle() !== 'cart_commande') {
        $errors[] = "Ligne $cart_nid : cart_commande introuvable";
        continue;
      }

      $line_status = $cart->hasField('field_status_cart_commande')
        ? (string) ($cart->get('field_status_cart_commande')->value ?? '')
        : '';
      if ($line_status === 'transfer_vers_boutique') {
        $results[] = [
          'cart_nid' => $cart_nid,
          'skipped' => TRUE,
          'message' => 'Déjà entièrement transféré',
        ];
        continue;
      }

      $line_qty = 0;
      if ($cart->hasField('field_quantite')) {
        $line_qty = (int) ($cart->get('field_quantite')->value ?? 0);
      }
      if ($line_qty <= 0) {
        $errors[] = "Ligne $cart_nid : quantité restante nulle";
        continue;
      }

      $transfer_qty = $requested_qty > 0 ? $requested_qty : $line_qty;
      if ($transfer_qty > $line_qty) {
        $errors[] = "Ligne $cart_nid : quantité demandée ($transfer_qty) supérieure au reste ($line_qty)";
        continue;
      }
      if ($transfer_qty <= 0) {
        $errors[] = "Ligne $cart_nid : quantité de transfert invalide";
        continue;
      }

      $pc_nid = NULL;
      if ($cart->hasField('field_product_id') && !$cart->get('field_product_id')->isEmpty()) {
        $pc_nid = (int) $cart->get('field_product_id')->target_id;
      }
      if (!$pc_nid) {
        $errors[] = "Ligne $cart_nid : product_commande manquant";
        continue;
      }

      $pc = Node::load($pc_nid);
      if (!$pc || $pc->bundle() !== 'product_commande') {
        $errors[] = "Ligne $cart_nid : product_commande $pc_nid introuvable";
        continue;
      }

      $unit_price = 0.0;
      if ($cart->hasField('field_prix_unitaire') && !$cart->get('field_prix_unitaire')->isEmpty()) {
        $unit_price = (float) $cart->get('field_prix_unitaire')->value;
      }
      elseif ($pc->hasField('field_prix_vente') && !$pc->get('field_prix_vente')->isEmpty()) {
        $unit_price = (float) $pc->get('field_prix_vente')->value;
      }

      $transfer_total = $unit_price * $transfer_qty;
      $remaining_qty = $line_qty - $transfer_qty;
      $pc_title = $pc->getTitle();

      try {
        $product_result = $this->findOrCreateProductFromProductCommande($pc, $user);
        $product = $product_result['node'];
        $this->createStockInMovement(
          $product,
          $transfer_qty,
          $unit_price,
          $transfer_total,
          $user,
          'Transfert commande sur commande #' . $order->id() . ' (ligne #' . $cart_nid . ')'
        );

        $pc_deleted = FALSE;
        if ($pc->hasField('field_quantite_disponible')) {
          $pc_qty = (int) ($pc->get('field_quantite_disponible')->value ?? 0);
          if ($pc_qty > 0) {
            $out_qty = min($transfer_qty, $pc_qty);
            $this->createStockOutMovement(
              $pc,
              $out_qty,
              $user,
              'Transfert vers boutique — commande #' . $order->id()
            );
            $pc = Node::load($pc_nid);
            if ($pc && $pc->hasField('field_quantite_disponible')) {
              $pc_remaining = (int) ($pc->get('field_quantite_disponible')->value ?? 0);
              if ($pc_remaining <= 0) {
                $pc->delete();
                $pc_deleted = TRUE;
              }
            }
          }
        }

        $cart_removed = FALSE;
        if ($remaining_qty <= 0) {
          if ($cart->hasField('field_status_cart_commande')) {
            $cart->set('field_status_cart_commande', 'transfer_vers_boutique');
          }
          $cart->save();
          $this->removeCartFromOrder($order, $cart_nid);
          $cart->delete();
          unset($allowed_cart_nids[$cart_nid]);
          $cart_removed = TRUE;
          $order_dirty = TRUE;
        }
        else {
          if ($cart->hasField('field_quantite')) {
            $cart->set('field_quantite', $remaining_qty);
          }
          $remaining_total = $unit_price * $remaining_qty;
          if ($cart->hasField('field_total')) {
            $cart->set('field_total', $remaining_total);
          }
          if ($cart->hasField('field_price')) {
            $cart->set('field_price', $unit_price);
          }
          $cart->setTitle($pc_title . ' x' . $remaining_qty);
          $cart->save();
          $order_dirty = TRUE;
        }

        $product = Node::load($product->id());
        $stock_after = $product && $product->hasField('field_quantite_disponible')
          ? (int) ($product->get('field_quantite_disponible')->value ?? 0)
          : NULL;

        $results[] = [
          'cart_nid' => $cart_nid,
          'product_commande_nid' => $pc_nid,
          'product_commande_deleted' => $pc_deleted,
          'product_nid' => (int) $product->id(),
          'product_title' => $product->getTitle(),
          'quantity_transferred' => $transfer_qty,
          'quantity_remaining' => $remaining_qty,
          'cart_removed' => $cart_removed,
          'stock_after' => $stock_after,
          'created_product' => $product_result['created'],
        ];
      }
      catch (\Throwable $e) {
        $errors[] = "Ligne $cart_nid : " . $e->getMessage();
        \Drupal::logger('mz_eroso_v2')->error('transfer-to-boutique cart @cid: @msg', [
          '@cid' => $cart_nid,
          '@msg' => $e->getMessage(),
        ]);
      }
    }

    if ($order_dirty) {
      $this->recomputeOrderCommandeTotal($order);
      $order->save();
    }

    if (empty($results) && !empty($errors)) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Aucune ligne transférée',
        'errors' => $errors,
      ], 422);
    }

    return new JsonResponse([
      'status' => TRUE,
      'message' => count($results) . ' ligne(s) transférée(s) vers la boutique',
      'results' => $results,
      'errors' => $errors,
    ], empty($errors) ? 200 : 207);
  }

  /**
   * Normalise lines[] ou cart_nids[] en [{ cart_nid, quantity }].
   */
  private function parseTransferLinesRequest(array $body) : array {
    $lines = [];
    if (!empty($body['lines']) && is_array($body['lines'])) {
      foreach ($body['lines'] as $item) {
        if (!is_array($item) || empty($item['cart_nid'])) {
          continue;
        }
        $lines[] = [
          'cart_nid' => (int) $item['cart_nid'],
          'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 0,
        ];
      }
      return $lines;
    }
    if (!empty($body['cart_nids']) && is_array($body['cart_nids'])) {
      foreach ($body['cart_nids'] as $raw_cart_nid) {
        $lines[] = [
          'cart_nid' => (int) $raw_cart_nid,
          'quantity' => 0,
        ];
      }
    }
    return $lines;
  }

  /**
   * Retire une ligne cart de la commande order_commande.
   */
  private function removeCartFromOrder(Node $order, int $cart_nid) : void {
    if (!$order->hasField('field_carts')) {
      return;
    }
    $values = [];
    foreach ($order->get('field_carts') as $item) {
      $target = (int) ($item->target_id ?? 0);
      if ($target > 0 && $target !== $cart_nid) {
        $values[] = ['target_id' => $target];
      }
    }
    $order->set('field_carts', $values);
  }

  /**
   * Recalcule field_total de order_commande depuis ses cart_commande.
   */
  private function recomputeOrderCommandeTotal(Node $order) : void {
    if (!$order->hasField('field_total') || !$order->hasField('field_carts')) {
      return;
    }
    $order_total = 0.0;
    foreach ($order->get('field_carts') as $ref) {
      $cid = (int) ($ref->target_id ?? 0);
      if ($cid < 1) {
        continue;
      }
      $line = Node::load($cid);
      if (!$line) {
        continue;
      }
      if ($line->hasField('field_total') && !$line->get('field_total')->isEmpty()) {
        $order_total += (float) $line->get('field_total')->value;
      }
      elseif ($line->hasField('field_prix_unitaire') && $line->hasField('field_quantite')) {
        $order_total += (float) $line->get('field_prix_unitaire')->value
          * (int) $line->get('field_quantite')->value;
      }
    }
    $order->set('field_total', $order_total);
  }

  /**
   * Auth token (cookie, Bearer, body) — même contrat que OrderLocalController.
   */
  private function authenticateTransferRequest(Request $request, $body = NULL) {
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
    if (!\Drupal::hasService('api_solutions.api_crud')) {
      return NULL;
    }
    return \Drupal::service('api_solutions.api_crud')->validateBearerToken($token);
  }

  /**
   * Trouve un product par SKU ou crée depuis product_commande.
   *
   * @return array{node: \Drupal\node\Entity\Node, created: bool}
   */
  private function findOrCreateProductFromProductCommande(Node $pc, $user) {
    $sku = $pc->hasField('field_sku') ? trim((string) ($pc->get('field_sku')->value ?? '')) : '';
    if ($sku !== '') {
      $nids = \Drupal::entityQuery('node')
        ->condition('type', 'product')
        ->condition('field_sku', $sku)
        ->accessCheck(FALSE)
        ->range(0, 1)
        ->execute();
      if (!empty($nids)) {
        $existing = Node::load((int) reset($nids));
        if ($existing) {
          return ['node' => $existing, 'created' => FALSE];
        }
      }
    }

    $product = Node::create([
      'type' => 'product',
      'title' => $pc->getTitle(),
      'uid' => $user->id(),
      'status' => 1,
    ]);

    $copy_fields = [
      'field_sku',
      'field_category',
      'field_description',
      'field_prix_vente',
      'field_price',
      'field_media_image',
      'field_images',
      'field_produits_attribute',
    ];
    foreach ($copy_fields as $field_name) {
      $this->copyNodeField($pc, $product, $field_name);
    }

    if ($product->hasField('field_quantite_disponible')) {
      $product->set('field_quantite_disponible', 0);
    }
    if ($product->hasField('field_status')) {
      $product->set('field_status', 'dispo');
    }

    $product->save();
    return ['node' => $product, 'created' => TRUE];
  }

  /**
   * Copie la valeur d'un champ entre deux nœuds si présent des deux côtés.
   */
  private function copyNodeField(Node $from, Node $to, string $field_name) : void {
    if (!$from->hasField($field_name) || !$to->hasField($field_name)) {
      return;
    }
    if ($from->get($field_name)->isEmpty()) {
      return;
    }
    $to->set($field_name, $from->get($field_name)->getValue());
  }

  /**
   * Crée un mouvement stock « in » (hook met à jour field_quantite_disponible).
   */
  private function createStockInMovement(Node $product, int $quantity, float $unit_price, float $line_total, $user, string $reason) : void {
    $stock = Node::create([
      'type' => 'stock',
      'title' => 'Entrée - Transfert sur commande - ' . $product->getTitle(),
      'uid' => $user->id(),
    ]);
    if ($stock->hasField('field_type')) {
      $stock->set('field_type', 'in');
    }
    if ($stock->hasField('field_product_id')) {
      $stock->set('field_product_id', $product->id());
    }
    if ($stock->hasField('field_quantite')) {
      $stock->set('field_quantite', $quantity);
    }
    if ($stock->hasField('field_prix_de_vente') && $unit_price > 0) {
      $stock->set('field_prix_de_vente', $unit_price);
    }
    if ($stock->hasField('field_price') && $unit_price > 0) {
      $stock->set('field_price', $unit_price);
    }
    if ($stock->hasField('field_total_price') && $line_total > 0) {
      $stock->set('field_total_price', $line_total);
    }
    if ($stock->hasField('field_date_entree')) {
      $stock->set('field_date_entree', date('Y-m-d'));
    }
    if ($stock->hasField('field_raison')) {
      $stock->set('field_raison', $reason);
    }
    $stock->save();
  }

  /**
   * Crée un mouvement stock « out » sur product (ou product_commande).
   */
  private function createStockOutMovement(Node $product, int $quantity, $user, string $reason) : void {
    $stock = Node::create([
      'type' => 'stock',
      'title' => 'Sortie - Transfert vers boutique - ' . $product->getTitle(),
      'uid' => $user->id(),
    ]);
    if ($stock->hasField('field_type')) {
      $stock->set('field_type', 'out');
    }
    if ($stock->hasField('field_product_id')) {
      $stock->set('field_product_id', $product->id());
    }
    if ($stock->hasField('field_quantite')) {
      $stock->set('field_quantite', $quantity);
    }
    if ($stock->hasField('field_date_entree')) {
      $stock->set('field_date_entree', date('Y-m-d'));
    }
    if ($stock->hasField('field_raison')) {
      $stock->set('field_raison', $reason);
    }
    $stock->save();
  }

}
