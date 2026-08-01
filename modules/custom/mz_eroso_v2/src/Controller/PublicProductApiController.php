<?php

namespace Drupal\mz_eroso_v2\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public JSON API for available boutique products (no auth).
 */
class PublicProductApiController extends ControllerBase {

  /**
   * Liste paginée des produits disponibles au public.
   *
   * Query:
   * - offset : taille de page (défaut 20)
   * - pager : index de page depuis 0
   * - search : ≥ 2 car. (titre ou SKU selon search_type)
   * - search_type : title|sku (défaut title)
   * - category : tid taxonomy category (optionnel)
   */
  public function list(Request $request) {
    try {
      $pager = max(0, (int) $request->query->get('pager', 0));
      $offset = (int) $request->query->get('offset', 20);
      if ($offset < 1) {
        $offset = 20;
      }
      if ($offset > 100) {
        $offset = 100;
      }

      $search = trim((string) $request->query->get('search', ''));
      $search_type = strtolower(trim((string) $request->query->get('search_type', 'title')));
      if (!in_array($search_type, ['title', 'sku'], TRUE)) {
        $search_type = 'title';
      }
      $category_tid = (int) $request->query->get('category', 0);

      $matching_nids = $this->loadMatchingNids($search, $search_type, $category_tid);
      $available_nids = $this->filterPubliclyAvailableNids($matching_nids);
      $total = count($available_nids);

      $page_nids = array_slice($available_nids, $pager * $offset, $offset);
      $rows = $this->parseProducts($page_nids);

      return new JsonResponse([
        'rows' => $rows,
        'total' => $total,
      ]);
    }
    catch (\Throwable $e) {
      \Drupal::logger('mz_eroso_v2')->error('public_products list: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'message' => 'Erreur catalogue public',
        'status' => 'error',
      ], 500);
    }
  }

  /**
   * Détail d'un produit public (404 si indisponible).
   */
  public function view(int $nid) {
    try {
      if ($nid < 1) {
        return new JsonResponse(['message' => 'Produit introuvable'], 404);
      }

      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      if (!$node instanceof NodeInterface || $node->bundle() !== 'product' || !$node->isPublished()) {
        return new JsonResponse(['message' => 'Produit introuvable'], 404);
      }

      if (!$this->isNodePubliclyAvailable($node)) {
        return new JsonResponse(['message' => 'Produit non disponible'], 404);
      }

      $parser = \Drupal::service('entity_parser.manager');
      $row = $parser->loader_entity_by_type($nid, 'node');
      if (!is_array($row)) {
        $row = [];
      }
      if (!isset($row['nid'])) {
        $row['nid'] = $nid;
      }

      return new JsonResponse($this->sanitizePublicProduct($row));
    }
    catch (\Throwable $e) {
      \Drupal::logger('mz_eroso_v2')->error('public_products view @nid: @msg', [
        '@nid' => $nid,
        '@msg' => $e->getMessage(),
      ]);
      return new JsonResponse([
        'message' => 'Erreur chargement produit',
        'status' => 'error',
      ], 500);
    }
  }

  /**
   * Catégories utilisées pour le filtre catalogue.
   */
  public function categories() {
    try {
      $tids = \Drupal::entityQuery('taxonomy_term')
        ->accessCheck(FALSE)
        ->condition('vid', 'category')
        ->sort('weight')
        ->sort('name')
        ->execute();

      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($tids);
      $rows = [];
      foreach ($terms as $term) {
        $rows[] = [
          'tid' => (int) $term->id(),
          'title' => $term->label(),
        ];
      }

      return new JsonResponse(['rows' => array_values($rows)]);
    }
    catch (\Throwable $e) {
      \Drupal::logger('mz_eroso_v2')->error('public_products categories: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'message' => 'Erreur catégories',
        'status' => 'error',
      ], 500);
    }
  }

  /**
   * NIDs triés par changed DESC correspondant aux filtres de recherche.
   */
  private function loadMatchingNids(string $search, string $search_type, int $category_tid): array {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'product')
      ->condition('status', 1)
      ->condition('field_quantite_disponible', 0, '>');

    $status_group = $query->orConditionGroup();
    $status_group->condition('field_status', ['dispo', 'disponible'], 'IN');
    $status_group->notExists('field_status');
    $query->condition($status_group);

    if ($category_tid > 0) {
      $query->condition('field_category.target_id', $category_tid);
    }

    if (strlen($search) >= 2) {
      if ($search_type === 'sku') {
        $query->condition('field_sku', '%' . $search . '%', 'LIKE');
      }
      else {
        $query->condition('title', '%' . $search . '%', 'LIKE');
      }
    }

    $nids = $query
      ->sort('changed', 'DESC')
      ->execute();

    return array_map('intval', array_values($nids));
  }

  /**
   * Filtre les NIDs dont le statut vide ou non reconnu exclut la vente.
   */
  private function filterPubliclyAvailableNids(array $nids): array {
    if (!$nids) {
      return [];
    }

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    $available = [];
    foreach ($nids as $nid) {
      $node = $nodes[$nid] ?? NULL;
      if ($node instanceof NodeInterface && $this->isNodePubliclyAvailable($node)) {
        $available[] = $nid;
      }
    }
    return $available;
  }

  /**
   * Produit publié, stock > 0, statut vide ou dispo/disponible.
   */
  private function isNodePubliclyAvailable(NodeInterface $node): bool {
    if ($node->bundle() !== 'product' || !$node->isPublished()) {
      return FALSE;
    }

    $stock = (float) ($node->get('field_quantite_disponible')->value ?? 0);
    if ($stock <= 0) {
      return FALSE;
    }

    $status = strtolower(trim((string) ($node->get('field_status')->value ?? '')));
    return $status === '' || $status === 'dispo' || $status === 'disponible';
  }

  /**
   * @param int[] $nids
   */
  private function parseProducts(array $nids): array {
    if (!$nids) {
      return [];
    }

    $parser = \Drupal::service('entity_parser.manager');
    $rows = [];
    foreach ($nids as $nid) {
      try {
        $row = $parser->loader_entity_by_type($nid, 'node');
        if (!is_array($row)) {
          continue;
        }
        if (!isset($row['nid'])) {
          $row['nid'] = $nid;
        }
        $rows[] = $this->sanitizePublicProduct($row);
      }
      catch (\Throwable $e) {
        \Drupal::logger('mz_eroso_v2')->warning('public_products parse nid @nid: @msg', [
          '@nid' => $nid,
          '@msg' => $e->getMessage(),
        ]);
      }
    }
    return array_values($rows);
  }

  /**
   * Retire les champs internes / sensibles de la réponse publique.
   */
  private function sanitizePublicProduct(array $row): array {
    $category = NULL;
    if (isset($row['field_category']) && is_array($row['field_category'])) {
      $category = [
        'tid' => $row['field_category']['tid'] ?? NULL,
        'title' => $row['field_category']['title'] ?? NULL,
      ];
    }

    $image = NULL;
    if (isset($row['field_media_image']['image']['url'])) {
      $image = [
        'url' => $row['field_media_image']['image']['url'],
        'alt' => $row['field_media_image']['image']['alt'] ?? NULL,
      ];
    }

    return [
      'nid' => $row['nid'] ?? NULL,
      'title' => $row['title'] ?? '',
      'field_sku' => $row['field_sku'] ?? '',
      'field_prix_vente' => $row['field_prix_vente'] ?? '',
      'field_description' => $row['field_description'] ?? '',
      'field_category' => $category,
      'field_media_image' => $image ? ['image' => $image] : NULL,
      'in_stock' => TRUE,
      'changed' => $row['changed'] ?? NULL,
    ];
  }

}
