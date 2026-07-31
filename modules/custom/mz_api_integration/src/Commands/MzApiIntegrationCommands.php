<?php

namespace Drupal\mz_api_integration\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mz_api_integration\Service\ProductImageMatcher;
use Drupal\node\NodeInterface;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerInterface;

/**
 * Drush commands for mz_api_integration.
 */
class MzApiIntegrationCommands extends DrushCommands {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ProductImageMatcher $productImageMatcher,
    protected LoggerInterface $channelLogger,
  ) {
    parent::__construct();
  }

  /**
   * Remplit field_search_image (IA) pour les produits où il est vide.
   *
   * Lit l'image principale (field_media_image ou field_images) et appelle
   * l'IA configurée (Gemini, Claude ou ChatGPT) pour générer le texte de recherche.
   *
   * @command mz-api-integration:fill-search-image
   * @aliases mzc-fill-search-image,fill-search-image,mz-claude-api:fill-search-image
   * @option dry-run Simuler sans enregistrer les nœuds.
   * @option limit Nombre max de produits à traiter (0 = tous).
   * @option offset Ignorer les N premiers produits éligibles.
   * @option nid Traiter un seul produit (nid).
   * @option sleep-ms Pause en millisecondes entre chaque appel IA (défaut 800).
   * @option include-unpublished Inclure les produits non publiés.
   * @option bundle Type de contenu (product ou product_commande).
   * @usage drush mz-api-integration:fill-search-image
   * @usage drush mz-api-integration:fill-search-image --bundle=product_commande
   * @usage drush mz-api-integration:fill-search-image --dry-run --limit=5
   * @usage drush mz-api-integration:fill-search-image --nid=1584
   * @usage drush mz-api-integration:fill-search-image --limit=50 --offset=100 --sleep-ms=1200
   */
  public function fillSearchImage(array $options = [
    'dry-run' => FALSE,
    'limit' => 0,
    'offset' => 0,
    'nid' => 0,
    'sleep-ms' => 800,
    'include-unpublished' => FALSE,
    'bundle' => 'product',
  ]): int {
    $dry_run = (bool) $options['dry-run'];
    $limit = max(0, (int) $options['limit']);
    $offset = max(0, (int) $options['offset']);
    $single_nid = max(0, (int) $options['nid']);
    $sleep_ms = max(0, (int) $options['sleep-ms']);
    $include_unpublished = (bool) $options['include-unpublished'];
    $bundle = trim((string) ($options['bundle'] ?? 'product'));
    if (!in_array($bundle, ['product', 'product_commande'], TRUE)) {
      $bundle = 'product';
    }

    if ($single_nid > 0) {
      $nids = [$single_nid];
      $this->io()->writeln("Traitement du produit nid=$single_nid…");
    }
    else {
      $nids = $this->loadEligibleProductNids($include_unpublished, $bundle);
      $total_eligible = count($nids);
      if ($offset > 0) {
        $nids = array_slice($nids, $offset);
      }
      if ($limit > 0) {
        $nids = array_slice($nids, 0, $limit);
      }
      $this->io()->writeln(sprintf(
        'Produits éligibles : %d (offset=%d, à traiter=%d%s)',
        $total_eligible,
        $offset,
        count($nids),
        $dry_run ? ', mode simulation' : ''
      ));
    }

    if ($nids === []) {
      $this->io()->success('Aucun produit à traiter.');
      return self::EXIT_SUCCESS;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $updated = 0;
    $skipped = 0;
    $failed = 0;
    $no_image = 0;
    $already_filled = 0;
    $processed = 0;
    $total = count($nids);

    foreach ($nids as $index => $nid) {
      $processed++;
      /** @var \Drupal\node\NodeInterface|null $node */
      $node = $storage->load($nid);
      if (!$node instanceof NodeInterface || $node->bundle() !== $bundle) {
        $skipped++;
        $this->io()->warning("[$processed/$total] nid $nid : ignoré (introuvable ou pas un $bundle).");
        continue;
      }

      if (!$node->hasField('field_search_image')) {
        $skipped++;
        $this->io()->warning("[$processed/$total] nid $nid : champ field_search_image absent.");
        continue;
      }

      if ($single_nid === 0 && !$this->isSearchImageEmpty($node)) {
        $already_filled++;
        continue;
      }

      if (!$this->nodeHasImage($node)) {
        $no_image++;
        $this->io()->writeln("[$processed/$total] nid $nid « {$node->getTitle()} » : pas d'image.");
        continue;
      }

      $this->io()->write("[$processed/$total] nid $nid « {$node->getTitle()} »… ");

      try {
        $data = $this->productImageMatcher->buildSearchImageDataForNode($node);
      }
      catch (\Throwable $e) {
        $failed++;
        $this->io()->writeln('<error>échec IA</error> — ' . $e->getMessage());
        $this->channelLogger->error('fill-search-image nid @nid: @msg', [
          '@nid' => $nid,
          '@msg' => $e->getMessage(),
        ]);
        if ($sleep_ms > 0 && $processed < $total) {
          usleep($sleep_ms * 1000);
        }
        continue;
      }

      if ($data === NULL) {
        $failed++;
        $this->io()->writeln('<error>échec</error> — image illisible ou analyse IA impossible.');
        if ($sleep_ms > 0 && $processed < $total) {
          usleep($sleep_ms * 1000);
        }
        continue;
      }

      $text = trim((string) ($data['field_search_image'] ?? ''));
      if ($text === '') {
        $failed++;
        $this->io()->writeln('<error>texte vide</error>');
        if ($sleep_ms > 0 && $processed < $total) {
          usleep($sleep_ms * 1000);
        }
        continue;
      }

      if ($dry_run) {
        $updated++;
        $preview = mb_strlen($text) > 120 ? mb_substr($text, 0, 117) . '…' : $text;
        $this->io()->writeln("<info>OK (simulation)</info> — $preview");
      }
      else {
        $node->set('field_search_image', [
          'value' => $text,
          'format' => 'plain_text',
        ]);
        $node->save();
        $updated++;
        $this->io()->writeln('<info>enregistré</info>');
      }

      $storage->resetCache([$nid]);
      if ($sleep_ms > 0 && $processed < $total) {
        usleep($sleep_ms * 1000);
      }
    }

    $this->io()->newLine();
    $this->io()->success(sprintf(
      'Terminé. Mis à jour=%d, déjà rempli=%d, sans image=%d, ignorés=%d, échecs=%d',
      $updated,
      $already_filled,
      $no_image,
      $skipped,
      $failed
    ));

    return $failed > 0 ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }

  /**
   * Remplit field_search_image (IA) pour les product_commande où il est vide.
   *
   * @command mz-api-integration:fill-search-image-commande
   * @aliases mzc-fill-search-image-commande,fill-search-image-commande,mz-claude-api:fill-search-image-commande
   * @option dry-run Simuler sans enregistrer les nœuds.
   * @option limit Nombre max de produits à traiter (0 = tous).
   * @option offset Ignorer les N premiers produits éligibles.
   * @option nid Traiter un seul product_commande (nid).
   * @option sleep-ms Pause en millisecondes entre chaque appel IA (défaut 800).
   * @option include-unpublished Inclure les produits non publiés.
   * @usage drush mz-api-integration:fill-search-image-commande
   * @usage drush mz-api-integration:fill-search-image-commande --dry-run --limit=5
   * @usage drush mz-api-integration:fill-search-image-commande --limit=50 --sleep-ms=1200
   */
  public function fillSearchImageCommande(array $options = [
    'dry-run' => FALSE,
    'limit' => 0,
    'offset' => 0,
    'nid' => 0,
    'sleep-ms' => 800,
    'include-unpublished' => FALSE,
  ]): int {
    $options['bundle'] = 'product_commande';
    $this->io()->title('Remplissage field_search_image — product_commande (IA)');
    return $this->fillSearchImage($options);
  }

  /**
   * @return int[]
   */
  protected function loadEligibleProductNids(bool $include_unpublished, string $bundle = 'product'): array {
    if (!in_array($bundle, ['product', 'product_commande'], TRUE)) {
      $bundle = 'product';
    }
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->sort('nid', 'ASC');

    if (!$include_unpublished) {
      $query->condition('status', 1);
    }

    $empty_group = $query->orConditionGroup();
    $empty_group->notExists('field_search_image');
    $empty_group->condition('field_search_image.value', '', '=');
    $query->condition($empty_group);

    $image_group = $query->orConditionGroup();
    $image_group->exists('field_media_image');
    $image_group->exists('field_images');
    $query->condition($image_group);

    $nids = $query->execute();
    return array_map('intval', array_values($nids));
  }

  protected function isSearchImageEmpty(NodeInterface $node): bool {
    if (!$node->hasField('field_search_image') || $node->get('field_search_image')->isEmpty()) {
      return TRUE;
    }
    return trim((string) $node->get('field_search_image')->value) === '';
  }

  protected function nodeHasImage(NodeInterface $node): bool {
    if ($node->hasField('field_media_image') && !$node->get('field_media_image')->isEmpty()) {
      return TRUE;
    }
    if ($node->hasField('field_images') && !$node->get('field_images')->isEmpty()) {
      return TRUE;
    }
    return FALSE;
  }

}
