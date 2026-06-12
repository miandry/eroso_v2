<?php

namespace Drupal\mz_eroso_v2\Traits;

use Drupal\mz_eroso_v2\Service\NodeRevisionLog;
use Drupal\node\NodeInterface;

/**
 * Helpers révision pour les contrôleurs mz_eroso_v2.
 */
trait RevisionLogTrait {

  /**
   * Service journal de révision.
   */
  protected function nodeRevisionLog(): NodeRevisionLog {
    return \Drupal::service('mz_eroso_v2.node_revision');
  }

  /**
   * Sauvegarde un nœud avec commentaire de révision.
   */
  protected function saveNodeRevision(NodeInterface $node, string $message, ?int $uid = NULL): void {
    $this->nodeRevisionLog()->save($node, $message, $uid);
  }

}
