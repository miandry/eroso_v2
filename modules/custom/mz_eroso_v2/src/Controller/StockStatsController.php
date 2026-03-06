<?php

namespace Drupal\mz_eroso_v2\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;

/**
 * Controller for Stock Statistics API.
 */
class StockStatsController extends ControllerBase {

  /**
   * Get stock statistics by period.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with stock statistics.
   */
  public function getStats(Request $request) {
    $period = $request->query->get('period', 'today');
    
    // Get timestamp for period
    $timestamp = $this->getPeriodTimestamp($period);
    
    // Query stock nodes
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'stock')
      ->accessCheck(TRUE);
    
    if ($timestamp > 0) {
      $query->condition('created', $timestamp, '>=');
    }
    
    $nids = $query->execute();
    
    // Initialize counters
    $total_in = 0;
    $total_out = 0;
    $total_in_value = 0;
    $total_out_value = 0;
    $in_count = 0;
    $out_count = 0;
    
    if (!empty($nids)) {
      $nodes = Node::loadMultiple($nids);
      
      foreach ($nodes as $node) {
        $type = $node->hasField('field_type') ? $node->get('field_type')->value : null;
        $quantity = $node->hasField('field_quantite') ? (float) $node->get('field_quantite')->value : 0;
        $prix_vente = $node->hasField('field_prix_de_vente') ? (float) $node->get('field_prix_de_vente')->value : 0;
        $total_price = $node->hasField('field_total_price') ? (float) $node->get('field_total_price')->value : 0;
        
        // Use total_price if available, otherwise calculate from quantity * prix_vente
        $value = $total_price > 0 ? $total_price : ($quantity * $prix_vente);
        
        if ($type === 'in') {
          $total_in += $quantity;
          $total_in_value += $value;
          $in_count++;
        } elseif ($type === 'out') {
          $total_out += $quantity;
          $total_out_value += $value;
          $out_count++;
        }
      }
    }
    
    // Get product statistics
    $product_stats = $this->getProductStats();
    
    return new JsonResponse([
      'status' => 'success',
      'period' => $period,
      'timestamp' => $timestamp,
      'data' => [
        'entries' => [
          'total_units' => $total_in,
          'total_value' => $total_in_value,
          'count' => $in_count,
        ],
        'exits' => [
          'total_units' => $total_out,
          'total_value' => $total_out_value,
          'count' => $out_count,
        ],
        'products' => $product_stats,
      ],
    ]);
  }

  /**
   * Get product statistics.
   *
   * @return array
   *   Product statistics.
   */
  private function getProductStats() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'product')
      ->accessCheck(TRUE);
    
    $nids = $query->execute();
    
    $total_products = count($nids);
    $total_stock = 0;
    $total_stock_value = 0;
    $low_stock_count = 0;
    
    if (!empty($nids)) {
      $products = Node::loadMultiple($nids);
      
      foreach ($products as $product) {
        $qty = $product->hasField('field_quantite_disponible') 
          ? (int) $product->get('field_quantite_disponible')->value 
          : 0;
        
        $price = 0;
        if ($product->hasField('field_prix_vente') && !$product->get('field_prix_vente')->isEmpty()) {
          $price = (float) $product->get('field_prix_vente')->value;
        } elseif ($product->hasField('field_price') && !$product->get('field_price')->isEmpty()) {
          $price = (float) $product->get('field_price')->value;
        }
        
        $total_stock += $qty;
        $total_stock_value += ($qty * $price);
        
        if ($qty <= 5) {
          $low_stock_count++;
        }
      }
    }
    
    return [
      'total_products' => $total_products,
      'total_stock' => $total_stock,
      'total_stock_value' => $total_stock_value,
      'low_stock_count' => $low_stock_count,
    ];
  }

  /**
   * Get timestamp for period filtering.
   *
   * @param string $period
   *   Period string.
   *
   * @return int
   *   Unix timestamp.
   */
  private function getPeriodTimestamp($period) {
    $now = time();
    
    switch ($period) {
      case 'today':
      case 'Aujourd\'hui':
        return strtotime('today');
      
      case '7days':
      case '7 jours':
        return strtotime('-7 days');
      
      case '30days':
      case '30 jours':
        return strtotime('-30 days');
      
      case 'all':
      case 'Tout':
      default:
        return 0;
    }
  }

  /**
   * Get list of stock entries.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with stock entries list.
   */
  public function getEntries(Request $request) {
    $period = $request->query->get('period', 'today');
    $limit = $request->query->get('limit', 50);
    $offset = $request->query->get('offset', 0);
    
    $timestamp = $this->getPeriodTimestamp($period);
    
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'stock')
      ->condition('field_type', 'in')
      ->sort('created', 'DESC')
      ->range($offset, $limit)
      ->accessCheck(TRUE);
    
    if ($timestamp > 0) {
      $query->condition('created', $timestamp, '>=');
    }
    
    $nids = $query->execute();
    $entries = [];
    
    if (!empty($nids)) {
      $nodes = Node::loadMultiple($nids);
      foreach ($nodes as $node) {
        $entries[] = $this->formatStockItem($node);
      }
    }
    
    return new JsonResponse([
      'status' => 'success',
      'period' => $period,
      'count' => count($entries),
      'data' => $entries,
    ]);
  }

  /**
   * Get list of stock exits.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with stock exits list.
   */
  public function getExits(Request $request) {
    $period = $request->query->get('period', 'today');
    $limit = $request->query->get('limit', 50);
    $offset = $request->query->get('offset', 0);
    
    $timestamp = $this->getPeriodTimestamp($period);
    
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'stock')
      ->condition('field_type', 'out')
      ->sort('created', 'DESC')
      ->range($offset, $limit)
      ->accessCheck(TRUE);
    
    if ($timestamp > 0) {
      $query->condition('created', $timestamp, '>=');
    }
    
    $nids = $query->execute();
    $exits = [];
    
    if (!empty($nids)) {
      $nodes = Node::loadMultiple($nids);
      foreach ($nodes as $node) {
        $exits[] = $this->formatStockItem($node);
      }
    }
    
    return new JsonResponse([
      'status' => 'success',
      'period' => $period,
      'count' => count($exits),
      'data' => $exits,
    ]);
  }

  /**
   * Format stock item for API response.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The stock node.
   *
   * @return array
   *   Formatted stock item data.
   */
  private function formatStockItem($node) {
    $product_info = null;
    $product_title = '';
    $product_sku = '';
    
    // Get product reference
    if ($node->hasField('field_product_id') && !$node->get('field_product_id')->isEmpty()) {
      $product = $node->get('field_product_id')->entity;
      if ($product) {
        $product_title = $product->getTitle();
        $product_info = [
          'nid' => $product->id(),
          'title' => $product_title,
        ];
        
        if ($product->hasField('field_sku') && !$product->get('field_sku')->isEmpty()) {
          $product_sku = $product->get('field_sku')->value;
          $product_info['sku'] = $product_sku;
        }
      }
    }
    
    $quantity = $node->hasField('field_quantite') ? (float) $node->get('field_quantite')->value : 0;
    $prix_vente = $node->hasField('field_prix_de_vente') ? (float) $node->get('field_prix_de_vente')->value : 0;
    $total_price = $node->hasField('field_total_price') ? (float) $node->get('field_total_price')->value : 0;
    
    // Calculate value
    $value = $total_price > 0 ? $total_price : ($quantity * $prix_vente);
    
    return [
      'nid' => $node->id(),
      'title' => $node->getTitle(),
      'product_title' => $product_title,
      'product_sku' => $product_sku,
      'product' => $product_info,
      'field_type' => $node->hasField('field_type') ? $node->get('field_type')->value : null,
      'field_quantite' => $quantity,
      'field_prix_de_vente' => $prix_vente,
      'field_price' => $node->hasField('field_price') ? (float) $node->get('field_price')->value : 0,
      'field_total_price' => $total_price,
      'field_raison' => $node->hasField('field_raison') ? $node->get('field_raison')->value : '',
      'field_description' => $node->hasField('field_description') ? $node->get('field_description')->value : '',
      'field_date_entree' => $node->hasField('field_date_entree') ? $node->get('field_date_entree')->value : null,
      'calculated_value' => $value,
      'created' => $node->getCreatedTime(),
      'created_date' => date('Y-m-d H:i:s', $node->getCreatedTime()),
    ];
  }

}
