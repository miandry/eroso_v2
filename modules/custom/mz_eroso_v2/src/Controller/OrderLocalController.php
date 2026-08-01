<?php

namespace Drupal\mz_eroso_v2\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mz_eroso_v2\Traits\RevisionLogTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;

/**
 * Controller for Order Local API.
 */
class OrderLocalController extends ControllerBase {

  use RevisionLogTrait;

  /**
   * Authenticate request using token (cookie, header, or body).
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param array|null $body
   *
   * @return \Drupal\user\Entity\User|null
   */
  private function authenticateRequest(Request $request, $body = NULL) {
    // 1. Cookie
    $token = $request->cookies->get('auth_token');

    // 2. Authorization header
    if (!$token) {
      $auth_header = $request->headers->get('Authorization');
      if ($auth_header && preg_match('/Bearer\s+(.+)/i', $auth_header, $matches)) {
        $token = trim($matches[1]);
      }
    }

    // 3. Body token
    if (!$token && $body) {
      $token = $body['token'] ?? NULL;
    }

    if (!$token) {
      return NULL;
    }

    $service = \Drupal::service('api_solutions.api_crud');
    return $service->validateBearerToken($token);
  }

  /**
   * Save order_local with cart items and update product stock.
   *
   * Expected POST body:
   * {
   *   "token": "...",
   *   "items": [
   *     { "product_nid": 123, "quantity": 2, "prix_unitaire": 5000 },
   *     ...
   *   ],
   *   "notes": "optional notes",
   *   "author": "username"
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function saveOrderLocal(Request $request) {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => false, 'message' => 'POST required'], 405);
    }

    $content = $request->getContent();
    $body = json_decode($content, TRUE);

    if (empty($body) || empty($body['items'])) {
      return new JsonResponse(['status' => false, 'message' => 'Données manquantes: items requis'], 400);
    }

    // Authenticate
    $user = $this->authenticateRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => false, 'message' => 'Token invalide ou session expirée'], 401);
    }

    $items = $body['items'];
    $notes = $body['notes'] ?? '';
    $author_name = $body['author'] ?? $user->getAccountName();

    // Validate stock availability for all items first
    $stock_errors = [];
    $products_to_update = [];

    foreach ($items as $index => $item) {
      $product_nid = $item['product_nid'] ?? NULL;
      $quantity = (int) ($item['quantity'] ?? 0);

      if (!$product_nid || $quantity <= 0) {
        $stock_errors[] = "Item #$index: product_nid et quantity requis";
        continue;
      }

      $product = Node::load($product_nid);
      if (!$product || $product->bundle() !== 'product') {
        $stock_errors[] = "Item #$index: Produit $product_nid introuvable";
        continue;
      }

      $current_stock = (int) ($product->hasField('field_quantite_disponible')
        ? $product->get('field_quantite_disponible')->value
        : 0);

      if ($current_stock < $quantity) {
        $stock_errors[] = "Stock insuffisant pour " . $product->getTitle() . " (dispo: $current_stock, demandé: $quantity)";
        continue;
      }

      $products_to_update[] = [
        'product' => $product,
        'quantity' => $quantity,
        'prix_unitaire' => $item['prix_unitaire'] ?? 0,
        'current_stock' => $current_stock,
      ];
    }

    if (!empty($stock_errors)) {
      return new JsonResponse([
        'status' => false,
        'message' => 'Erreur de validation du stock',
        'errors' => $stock_errors,
      ], 422);
    }

    // All stock checks passed — proceed with saving
    $cart_ids = [];
    $total = 0;

    try {
      foreach ($products_to_update as $entry) {
        $product = $entry['product'];
        $quantity = $entry['quantity'];
        $prix_unitaire = (float) $entry['prix_unitaire'];
        $line_total = $prix_unitaire * $quantity;
        $total += $line_total;

        // Create cart node (line item)
        $cart = Node::create([
          'type' => 'cart',
          'title' => $product->getTitle() . ' x' . $quantity,
          'uid' => $user->id(),
        ]);

        if ($cart->hasField('field_product_id')) {
          $cart->set('field_product_id', $product->id());
        }
        if ($cart->hasField('field_quantite')) {
          $cart->set('field_quantite', $quantity);
        }
        if ($cart->hasField('field_prix_unitaire')) {
          $cart->set('field_prix_unitaire', $prix_unitaire);
        }
        if ($cart->hasField('field_total')) {
          $cart->set('field_total', $line_total);
        }

        $this->saveNodeRevision(
          $cart,
          'API caisse locale : création ligne panier « ' . $product->getTitle() . ' » (qté ' . $quantity . ')',
          (int) $user->id(),
        );
        $cart_ids[] = $cart->id();


        // Record stock movement (out)
        $stock_node = Node::create([
          'type' => 'stock',
          'title' => 'Sortie - Vente locale - ' . $product->getTitle(),
          'uid' => $user->id(),
        ]);

        if ($stock_node->hasField('field_type')) {
          $stock_node->set('field_type', 'out');
        }
        if ($stock_node->hasField('field_product_id')) {
          $stock_node->set('field_product_id', $product->id());
        }
        if ($stock_node->hasField('field_quantite')) {
          $stock_node->set('field_quantite', $quantity);
        }
        if ($stock_node->hasField('field_prix_de_vente')) {
          $stock_node->set('field_prix_de_vente', $prix_unitaire);
        }
        if ($stock_node->hasField('field_total_price')) {
          $stock_node->set('field_total_price', $line_total);
        }
        if ($stock_node->hasField('field_date_entree')) {
          $stock_node->set('field_date_entree', date('Y-m-d'));
        }
        if ($stock_node->hasField('field_raison')) {
          $stock_node->set('field_raison', 'Vente locale');
        }

        $this->saveNodeRevision(
          $stock_node,
          'API caisse locale : mouvement stock sortie — « ' . $product->getTitle() . ' » (qté ' . $quantity . ')',
          (int) $user->id(),
        );
      }

      // Create order_local node
      $order = Node::create([
        'type' => 'order_local',
        'title' => 'Vente locale - ' . date('d/m/Y H:i'),
        'uid' => $user->id(),
      ]);

      if ($order->hasField('field_date')) {
        $order->set('field_date', date('Y-m-d'));
      }
      if ($order->hasField('field_carts') && !empty($cart_ids)) {
        $order->set('field_carts', $cart_ids);
      }
      if ($order->hasField('field_total')) {
        $order->set('field_total', $total);
      }
      if ($order->hasField('field_info')) {
        $order->set('field_info', $notes);
      }
      if ($order->hasField('field_status_commande')) {
        $order->set('field_status_commande', 'payer_recue');
      }
      if ($order->hasField('field_etat_commande')) {
        $order->set('field_etat_commande', '');
      }
      if ($order->hasField('field_status_local')) {
        $order->set('field_status_local', 'sortie');
      }

      $this->saveNodeRevision(
        $order,
        'API caisse locale : création commande order_local (total ' . $total . ' Ar)',
        (int) $user->id(),
      );

      // Build response with updated stock info
      $updated_products = [];
      foreach ($products_to_update as $entry) {
        $p = $entry['product'];
        $updated_products[] = [
          'nid' => $p->id(),
          'title' => $p->getTitle(),
          'new_stock' => $entry['current_stock'] - $entry['quantity'],
        ];
      }

      return new JsonResponse([
        'status' => true,
        'message' => 'Vente locale enregistrée avec succès',
        'order_id' => $order->id(),
        'total' => $total,
        'cart_ids' => $cart_ids,
        'updated_products' => $updated_products,
      ]);

    }
    catch (\Exception $e) {
      \Drupal::logger('mz_eroso_v2')->error('Order local save error: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'status' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Update field_status_local on an order_local (admin only).
   *
   * Expected POST body:
   * {
   *   "token": "...",
   *   "nid": 123,
   *   "status": "payer"
   * }
   */
  public function updateStatusLocal(Request $request) {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => false, 'message' => 'POST required'], 405);
    }

    $content = $request->getContent();
    $body = json_decode($content, TRUE);

    if (empty($body) || empty($body['nid']) || empty($body['status'])) {
      return new JsonResponse(['status' => false, 'message' => 'nid and status required'], 400);
    }

    $user = $this->authenticateRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => false, 'message' => 'Non autorisé'], 401);
    }

    $roles = $user->getRoles();
    $is_admin   = in_array('administrator', $roles, TRUE);
    $is_editor  = in_array('content_editor', $roles, TRUE);

    if (!$is_admin && !$is_editor) {
      return new JsonResponse(['status' => false, 'message' => 'Accès non autorisé'], 403);
    }

    $new_status = $body['status'];

    // content_editor : sortie, envoi livreur, annuler.
    $editor_statuses = ['sortie', 'envoi_livreur', 'annuler'];
    $admin_statuses  = ['sortie', 'envoi_livreur', 'livre_np', 'livre_p', 'annuler'];

    $allowed_statuses = $is_admin ? $admin_statuses : $editor_statuses;
    if (!in_array($new_status, $allowed_statuses, TRUE)) {
      return new JsonResponse(['status' => false, 'message' => 'Statut invalide ou non autorisé pour ce rôle'], 403);
    }

    $order = Node::load((int) $body['nid']);
    if (!$order || $order->bundle() !== 'order_local') {
      return new JsonResponse(['status' => false, 'message' => 'Commande introuvable'], 404);
    }

    $old_status = $order->hasField('field_status_local')
      ? (string) ($order->get('field_status_local')->value ?? '')
      : '';

    if ($order->hasField('field_status_local')) {
      $order->set('field_status_local', $new_status);
    }
    if ($order->hasField('field_status_commande')) {
      $commande_map = [
        'livre_p' => 'payer_recue',
        'livre_np' => 'no_payer',
        'envoi_livreur' => 'en_livraison',
        'annuler' => 'annuler',
        'sortie' => 'sortie',
      ];
      $order->set('field_status_commande', $commande_map[$new_status] ?? $new_status);
    }
    $this->saveNodeRevision(
      $order,
      'API order_local : statut « ' . $old_status . ' » → « ' . $new_status . ' »',
      (int) $user->id(),
    );

    return new JsonResponse([
      'status' => true,
      'message' => 'Statut mis à jour avec succès',
      'order_id' => $order->id(),
      'new_status' => $new_status,
    ]);
  }

  /**
   * Cancel an order_local and rollback stock.
   *
   * Expected POST body:
   * {
   *   "token": "...", // optional (cookie or Authorization header also supported)
   *   "nid": 123
   * }
   */
  public function cancelOrderLocal(Request $request) {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => false, 'message' => 'POST required'], 405);
    }

    $content = $request->getContent();
    $body = json_decode($content, TRUE);

    if (empty($body) || empty($body['nid'])) {
      return new JsonResponse(['status' => false, 'message' => 'nid requis'], 400);
    }

    // Authenticate
    $user = $this->authenticateRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => false, 'message' => 'Token invalide ou session expirée'], 401);
    }

    $order_nid = (int) $body['nid'];
    $order = Node::load($order_nid);
    if (!$order || $order->bundle() !== 'order_local') {
      return new JsonResponse(['status' => false, 'message' => 'order_local introuvable'], 404);
    }

    $current_status = NULL;
    if ($order->hasField('field_status_commande')) {
      $current_status = $order->get('field_status_commande')->value;
    }

    if ($current_status === 'annuler') {
      return new JsonResponse(['status' => false, 'message' => 'Commande déjà annulée'], 422);
    }

    // Get cart ids from the order.
    $cart_ids = [];
    if ($order->hasField('field_carts')) {
      foreach ($order->get('field_carts')->getValue() as $item) {
        $cart_id = $item['target_id'] ?? $item['value'] ?? NULL;
        if ($cart_id) {
          $cart_ids[] = (int) $cart_id;
        }
      }
    }

    if (empty($cart_ids)) {
      return new JsonResponse(['status' => false, 'message' => 'Aucun cart associé à la commande'], 422);
    }

    $updated_products = [];
    $errors = [];

    // Rollback: for each cart item, increase product stock and record stock movement (in).
    foreach ($cart_ids as $cart_id) {
      $cart = Node::load($cart_id);
      if (!$cart || $cart->bundle() !== 'cart') {
        $errors[] = "Cart $cart_id introuvable";
        continue;
      }

      // Product
      $product_id = NULL;
      if ($cart->hasField('field_product_id')) {
        $product_id = $cart->get('field_product_id')->target_id ?? $cart->get('field_product_id')->value ?? NULL;
      }

      if (!$product_id) {
        $errors[] = "Cart $cart_id: field_product_id manquant";
        continue;
      }

      $product = Node::load((int) $product_id);
      if (!$product || $product->bundle() !== 'product') {
        $errors[] = "Produit $product_id introuvable";
        continue;
      }

      // Quantity
      $quantity = 0;
      if ($cart->hasField('field_quantite')) {
        $quantity = (int) ($cart->get('field_quantite')->value ?? 0);
      }
      if ($quantity <= 0) {
        $errors[] = "Cart $cart_id: quantité invalide";
        continue;
      }

      // Unit price (if available)
      $unit_price = 0;
      if ($cart->hasField('field_prix_unitaire')) {
        $unit_price = (float) ($cart->get('field_prix_unitaire')->value ?? 0);
      }

      // Totals
      $line_total = $unit_price * $quantity;
      if ($cart->hasField('field_total')) {
        $line_total = (float) ($cart->get('field_total')->value ?? $line_total);
      }

      // Do NOT directly update `field_quantite_disponible`.
      // Rollback is implemented by inserting a "stock in" movement node.
      // (Your system is expected to update product stock based on stock movements.)
      $current_stock = 0;
      if ($product->hasField('field_quantite_disponible')) {
        $current_stock = (int) ($product->get('field_quantite_disponible')->value ?? 0);
      }

      $new_stock = $current_stock + $quantity;
      $updated_products[] = [
        'nid' => $product->id(),
        'title' => $product->getTitle(),
        'new_stock' => $new_stock,
      ];

      // Record stock movement (IN)
      $stock_node = Node::create([
        'type' => 'stock',
        'title' => 'Entrée - Annulation vente locale - ' . $product->getTitle(),
        'uid' => $user->id(),
      ]);

      if ($stock_node->hasField('field_type')) {
        $stock_node->set('field_type', 'in');
      }
      if ($stock_node->hasField('field_product_id')) {
        $stock_node->set('field_product_id', $product->id());
      }
      if ($stock_node->hasField('field_quantite')) {
        $stock_node->set('field_quantite', $quantity);
      }
      if ($stock_node->hasField('field_prix_de_vente')) {
        $stock_node->set('field_prix_de_vente', $unit_price);
      }
      if ($stock_node->hasField('field_total_price')) {
        $stock_node->set('field_total_price', $line_total);
      }
      if ($stock_node->hasField('field_date_entree')) {
        $stock_node->set('field_date_entree', date('Y-m-d'));
      }
      if ($stock_node->hasField('field_raison')) {
        $stock_node->set('field_raison', 'Annulation vente locale');
      }

      $this->saveNodeRevision(
        $stock_node,
        'API order_local : mouvement stock entrée (annulation) — « ' . $product->getTitle() . ' » (qté ' . $quantity . ')',
        (int) $user->id(),
      );
    }

    if (!empty($errors)) {
      // We still cancel the order if we rolled back what we could,
      // but return 422 to surface issues.
      foreach ($errors as $e) {
        \Drupal::logger('mz_eroso_v2')->warning('Cancel order rollback issue: @msg', ['@msg' => $e]);
      }
    }

    // Mark order as cancelled.
    if ($order->hasField('field_status_commande')) {
      $order->set('field_status_commande', 'annuler');
    }
    if ($order->hasField('field_status_local')) {
      $order->set('field_status_local', 'annuler');
    }
    if ($order->hasField('field_etat_commande')) {
      $order->set('field_etat_commande', '');
    }
    $this->saveNodeRevision(
      $order,
      'API order_local : annulation commande #' . $order->id(),
      (int) $user->id(),
    );

    if (!empty($errors)) {
      return new JsonResponse([
        'status' => false,
        'message' => 'Commande annulée avec des erreurs de rollback',
        'errors' => $errors,
        'order_id' => $order->id(),
        'updated_products' => $updated_products,
      ], 422);
    }

    return new JsonResponse([
      'status' => true,
      'message' => 'Commande annulée avec succès',
      'order_id' => $order->id(),
      'updated_products' => $updated_products,
    ]);
  }

  /**
   * Save order_commande with cart_commande lines (product_commande).
   *
   * Pas de mouvement de stock ni de nœud stock : la vente sur commande ne décrémente pas
   * field_quantite_disponible (product_commande peut ne pas exposer ce champ).
   */
  public function saveOrderCommande(Request $request) {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => false, 'message' => 'POST required'], 405);
    }

    $content = $request->getContent();
    $body = json_decode($content, TRUE);

    if (empty($body) || empty($body['items'])) {
      return new JsonResponse(['status' => false, 'message' => 'Données manquantes: items requis'], 400);
    }

    $user = $this->authenticateRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => false, 'message' => 'Token invalide ou session expirée'], 401);
    }

    $items = $body['items'];
    $notes = isset($body['notes']) ? trim((string) $body['notes']) : '';
    $client = isset($body['client']) ? trim((string) $body['client']) : '';
    $client_nid = isset($body['client_nid']) ? (int) $body['client_nid'] : 0;
    // Caisse sur commande : seuls draft et avance_payer depuis l’app (voir CaisseCommandePage.vue).
    $allowed_status_commande = ['draft', 'avance_payer'];
    $status_commande = isset($body['field_status_commande']) ? trim((string) $body['field_status_commande']) : 'draft';
    if (!in_array($status_commande, $allowed_status_commande, TRUE)) {
      $status_commande = 'draft';
    }

    $validation_errors = [];
    $products_to_update = [];

    foreach ($items as $index => $item) {
      $product_nid = $item['product_nid'] ?? NULL;
      $quantity = (int) ($item['quantity'] ?? 0);

      if (!$product_nid || $quantity <= 0) {
        $validation_errors[] = "Item #$index: product_nid et quantity requis";
        continue;
      }

      $product = Node::load($product_nid);
      if (!$product || $product->bundle() !== 'product_commande') {
        $validation_errors[] = "Item #$index: Produit sur commande $product_nid introuvable";
        continue;
      }

      // Pas de stock / pas de field_quantite_disponible pour la vente sur commande.
      $products_to_update[] = [
        'product' => $product,
        'quantity' => $quantity,
        'prix_unitaire' => $item['prix_unitaire'] ?? 0,
      ];
    }

    if (!empty($validation_errors)) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Erreur de validation',
        'errors' => $validation_errors,
      ], 422);
    }

    $cart_ids = [];
    $total = 0;

    try {
      foreach ($products_to_update as $entry) {
        $product = $entry['product'];
        $quantity = $entry['quantity'];
        $prix_unitaire = (float) $entry['prix_unitaire'];
        $line_total = $prix_unitaire * $quantity;
        $total += $line_total;

        $cart = Node::create([
          'type' => 'cart_commande',
          'title' => $product->getTitle() . ' x' . $quantity,
          'uid' => $user->id(),
        ]);

        if ($cart->hasField('field_product_id')) {
          $cart->set('field_product_id', $product->id());
        }
        if ($cart->hasField('field_quantite')) {
          $cart->set('field_quantite', $quantity);
        }
        if ($cart->hasField('field_prix_unitaire')) {
          $cart->set('field_prix_unitaire', $prix_unitaire);
        }
        if ($cart->hasField('field_price')) {
          $cart->set('field_price', $prix_unitaire);
        }
        if ($cart->hasField('field_total')) {
          $cart->set('field_total', $line_total);
        }
        if ($cart->hasField('field_status_cart_commande')) {
          $cart->set('field_status_cart_commande', 'process');
        }

        $this->saveNodeRevision(
          $cart,
          'API caisse sur commande : création cart_commande « ' . $product->getTitle() . ' » (qté ' . $quantity . ')',
          (int) $user->id(),
        );
        $cart_ids[] = $cart->id();
      }

      // Titre sans nom / tél. client (référence field_client + field_info pour l’identité).
      $order_title = 'Vente sur commande - ' . date('d/m/Y H:i');
      if (function_exists('mb_substr')) {
        $order_title = mb_substr($order_title, 0, 255);
      }
      else {
        $order_title = substr($order_title, 0, 255);
      }

      $info_lines = [];
      if ($client !== '') {
        $info_lines[] = 'Client : ' . $client;
      }
      if ($notes !== '') {
        $info_lines[] = $notes;
      }
      $field_info_combined = implode("\n\n", $info_lines);

      $order = Node::create([
        'type' => 'order_commande',
        'title' => $order_title,
        'uid' => $user->id(),
      ]);

      if ($order->hasField('field_date')) {
        $order->set('field_date', date('Y-m-d'));
      }
      if ($order->hasField('field_carts') && !empty($cart_ids)) {
        $order->set('field_carts', $cart_ids);
      }
      if ($order->hasField('field_total')) {
        $order->set('field_total', $total);
      }
      if ($order->hasField('field_info')) {
        $order->set('field_info', $field_info_combined);
      }
      if ($order->hasField('field_status_commande')) {
        $order->set('field_status_commande', $status_commande);
      }
      if ($order->hasField('field_etat_commande')) {
        $order->set('field_etat_commande', '');
      }
      if ($client_nid > 0 && $order->hasField('field_client')) {
        $client_node = Node::load($client_nid);
        if ($client_node && $client_node->bundle() === 'client') {
          $order->set('field_client', $client_nid);
        }
      }

      $this->saveNodeRevision(
        $order,
        'API caisse sur commande : création order_commande (statut « ' . $status_commande . ' », total ' . $total . ' Ar)',
        (int) $user->id(),
      );

      return new JsonResponse([
        'status' => TRUE,
        'message' => 'Vente sur commande enregistrée avec succès',
        'order_id' => $order->id(),
        'total' => $total,
        'cart_ids' => $cart_ids,
        'updated_products' => [],
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('mz_eroso_v2')->error('Order commande save error: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Update the unit price of a cart line belonging to an order_local, and
   * recompute the parent order's field_total. Administrator only.
   *
   * Expected POST body:
   * {
   *   "token": "...",
   *   "order_nid": 123,
   *   "cart_nid": 456,
   *   "prix_unitaire": 7500
   * }
   */
  public function updateCartPrice(Request $request) {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => FALSE, 'message' => 'POST required'], 405);
    }

    $body = json_decode($request->getContent(), TRUE);
    if (empty($body) || empty($body['order_nid']) || empty($body['cart_nid']) || !isset($body['prix_unitaire'])) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'order_nid, cart_nid et prix_unitaire sont requis',
      ], 400);
    }

    $user = $this->authenticateRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Non autorisé'], 401);
    }
    if (!in_array('administrator', $user->getRoles(), TRUE)) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Seuls les administrateurs peuvent modifier le prix d’un article.',
      ], 403);
    }

    $prix_unitaire = (float) $body['prix_unitaire'];
    if ($prix_unitaire < 0) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Le prix unitaire doit être positif.',
      ], 422);
    }

    $order = Node::load((int) $body['order_nid']);
    if (!$order || $order->bundle() !== 'order_local') {
      return new JsonResponse(['status' => FALSE, 'message' => 'Commande introuvable'], 404);
    }

    $current_status = $order->hasField('field_status_commande')
      ? (string) ($order->get('field_status_commande')->value ?? '')
      : '';
    if ($current_status === 'annuler') {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Commande annulée : édition impossible.',
      ], 422);
    }

    $cart_nid = (int) $body['cart_nid'];
    $cart = Node::load($cart_nid);
    if (!$cart || $cart->bundle() !== 'cart') {
      return new JsonResponse(['status' => FALSE, 'message' => 'Ligne panier introuvable'], 404);
    }

    // Make sure the cart actually belongs to this order.
    $cart_ids = [];
    if ($order->hasField('field_carts')) {
      foreach ($order->get('field_carts') as $ref) {
        $target = (int) ($ref->target_id ?? 0);
        if ($target > 0) {
          $cart_ids[] = $target;
        }
      }
    }
    if (!in_array($cart_nid, $cart_ids, TRUE)) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Cette ligne n’appartient pas à la commande spécifiée.',
      ], 422);
    }

    $quantity = 0;
    if ($cart->hasField('field_quantite')) {
      $quantity = (int) ($cart->get('field_quantite')->value ?? 0);
    }
    $line_total = $prix_unitaire * $quantity;

    try {
      if ($cart->hasField('field_prix_unitaire')) {
        $cart->set('field_prix_unitaire', $prix_unitaire);
      }
      if ($cart->hasField('field_total')) {
        $cart->set('field_total', $line_total);
      }
      $this->saveNodeRevision(
        $cart,
        'API order_local : mise à jour prix unitaire ligne panier #' . $cart_nid . ' → ' . $prix_unitaire . ' Ar',
        (int) $user->id(),
      );

      // Recompute the order total from all its carts.
      $order_total = 0.0;
      foreach ($cart_ids as $cid) {
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
      if ($order->hasField('field_total')) {
        $order->set('field_total', $order_total);
        $this->saveNodeRevision(
          $order,
          'API order_local : recalcul total commande #' . $order->id() . ' → ' . $order_total . ' Ar',
          (int) $user->id(),
        );
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('mz_eroso_v2')->error('Update cart price error: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
      ], 500);
    }

    return new JsonResponse([
      'status' => TRUE,
      'message' => 'Prix mis à jour.',
      'cart_nid' => $cart_nid,
      'order_nid' => (int) $order->id(),
      'prix_unitaire' => $prix_unitaire,
      'field_total' => $line_total,
      'quantite' => $quantity,
      'order_total' => $order_total,
    ]);
  }

  /**
   * Supprime une ligne panier d'une vente locale et remet le stock.
   *
   * Expected POST body:
   * {
   *   "token": "...",
   *   "order_nid": 123,
   *   "cart_nid": 456
   * }
   */
  public function deleteCartLine(Request $request) {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => FALSE, 'message' => 'POST required'], 405);
    }

    $body = json_decode($request->getContent(), TRUE);
    if (empty($body) || empty($body['order_nid']) || empty($body['cart_nid'])) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'order_nid et cart_nid sont requis',
      ], 400);
    }

    $user = $this->authenticateRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Non autorisé'], 401);
    }
    if (!in_array('administrator', $user->getRoles(), TRUE)) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Seuls les administrateurs peuvent supprimer un article.',
      ], 403);
    }

    $order = Node::load((int) $body['order_nid']);
    if (!$order || $order->bundle() !== 'order_local') {
      return new JsonResponse(['status' => FALSE, 'message' => 'Commande introuvable'], 404);
    }

    $status_commande = $order->hasField('field_status_commande')
      ? (string) ($order->get('field_status_commande')->value ?? '')
      : '';
    $status_local = $order->hasField('field_status_local')
      ? (string) ($order->get('field_status_local')->value ?? '')
      : '';
    if ($status_commande === 'annuler' || $status_local === 'annuler') {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Commande annulée : édition impossible.',
      ], 422);
    }

    $cart_nid = (int) $body['cart_nid'];
    $cart = Node::load($cart_nid);
    if (!$cart || $cart->bundle() !== 'cart') {
      return new JsonResponse(['status' => FALSE, 'message' => 'Ligne panier introuvable'], 404);
    }

    $cart_ids = [];
    if ($order->hasField('field_carts')) {
      foreach ($order->get('field_carts') as $ref) {
        $target = (int) ($ref->target_id ?? 0);
        if ($target > 0) {
          $cart_ids[] = $target;
        }
      }
    }
    if (!in_array($cart_nid, $cart_ids, TRUE)) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Cette ligne n’appartient pas à la commande spécifiée.',
      ], 422);
    }
    if (count($cart_ids) < 2) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Impossible de supprimer le dernier article. Annulez la vente si besoin.',
      ], 422);
    }

    $product_id = NULL;
    if ($cart->hasField('field_product_id')) {
      $product_id = $cart->get('field_product_id')->target_id ?? $cart->get('field_product_id')->value ?? NULL;
    }
    $quantity = 0;
    if ($cart->hasField('field_quantite')) {
      $quantity = (int) ($cart->get('field_quantite')->value ?? 0);
    }
    $unit_price = 0.0;
    if ($cart->hasField('field_prix_unitaire')) {
      $unit_price = (float) ($cart->get('field_prix_unitaire')->value ?? 0);
    }
    $line_total = $unit_price * $quantity;
    if ($cart->hasField('field_total') && !$cart->get('field_total')->isEmpty()) {
      $line_total = (float) $cart->get('field_total')->value;
    }

    try {
      if ($product_id && $quantity > 0) {
        $product = Node::load((int) $product_id);
        if ($product && $product->bundle() === 'product') {
          $stock_node = Node::create([
            'type' => 'stock',
            'title' => 'Entrée - Suppression ligne vente locale - ' . $product->getTitle(),
            'uid' => $user->id(),
          ]);
          if ($stock_node->hasField('field_type')) {
            $stock_node->set('field_type', 'in');
          }
          if ($stock_node->hasField('field_product_id')) {
            $stock_node->set('field_product_id', $product->id());
          }
          if ($stock_node->hasField('field_quantite')) {
            $stock_node->set('field_quantite', $quantity);
          }
          if ($stock_node->hasField('field_prix_de_vente')) {
            $stock_node->set('field_prix_de_vente', $unit_price);
          }
          if ($stock_node->hasField('field_total_price')) {
            $stock_node->set('field_total_price', $line_total);
          }
          if ($stock_node->hasField('field_date_entree')) {
            $stock_node->set('field_date_entree', date('Y-m-d'));
          }
          if ($stock_node->hasField('field_raison')) {
            $stock_node->set('field_raison', 'Suppression ligne vente locale');
          }
          $this->saveNodeRevision(
            $stock_node,
            'API order_local : entrée stock (suppression ligne) — « ' . $product->getTitle() . ' » (qté ' . $quantity . ')',
            (int) $user->id(),
          );
        }
      }

      $remaining_cart_ids = array_values(array_filter(
        $cart_ids,
        static fn ($id) => (int) $id !== $cart_nid
      ));
      if ($order->hasField('field_carts')) {
        $order->set('field_carts', $remaining_cart_ids);
      }

      $order_total = 0.0;
      foreach ($remaining_cart_ids as $cid) {
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
      if ($order->hasField('field_total')) {
        $order->set('field_total', $order_total);
      }
      $this->saveNodeRevision(
        $order,
        'API order_local : suppression ligne panier #' . $cart_nid . ' — total ' . $order_total . ' Ar',
        (int) $user->id(),
      );

      $cart->delete();
    }
    catch (\Exception $e) {
      \Drupal::logger('mz_eroso_v2')->error('Delete cart line error: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
      ], 500);
    }

    return new JsonResponse([
      'status' => TRUE,
      'message' => 'Article supprimé.',
      'cart_nid' => $cart_nid,
      'order_nid' => (int) $order->id(),
      'order_total' => $order_total,
      'remaining_carts' => $remaining_cart_ids,
    ]);
  }

}
