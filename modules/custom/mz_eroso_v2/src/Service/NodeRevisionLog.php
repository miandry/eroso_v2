<?php

namespace Drupal\mz_eroso_v2\Service;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\node\NodeInterface;

/**
 * Enregistre un message de révision Drupal avant chaque save de nœud (API).
 */
class NodeRevisionLog {

  /**
   * Longueur max du champ revision_log (node).
   */
  private const MAX_LENGTH = 255;

  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected TimeInterface $time,
  ) {}

  /**
   * Sauvegarde un nœud avec journal de révision (nouvelle révision si existant).
   *
   * @param \Drupal\node\NodeInterface $node
   *   Nœud à enregistrer.
   * @param string $message
   *   Commentaire de révision décrivant l'action.
   * @param int|null $uid
   *   Auteur de la révision (défaut : utilisateur courant).
   */
  public function save(NodeInterface $node, string $message, ?int $uid = NULL): void {
    if (!$node->isNew()) {
      $node->setNewRevision(TRUE);
      $node->setRevisionCreationTime($this->time->getRequestTime());
    }

    $revision_uid = $uid ?? (int) $this->currentUser->id();
    if ($revision_uid > 0) {
      $node->setRevisionUserId($revision_uid);
    }

    $node->setRevisionLogMessage($this->truncate($message));
    $node->save();
  }

  /**
   * Tronque le message au format attendu par revision_log.
   */
  protected function truncate(string $message): string {
    $message = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
    if ($message === '') {
      return 'Mise à jour (mz_eroso_v2)';
    }
    return Unicode::truncate($message, self::MAX_LENGTH, TRUE, TRUE);
  }

}
