<?php

namespace Drupal\mz_api_integration\Service;

/**
 * Contrat commun pour les clients IA vision (Gemini, Claude, ChatGPT).
 */
interface AiVisionClientInterface {

  /**
   * Identifiant du fournisseur actif (gemini, claude, chatgpt).
   */
  public function getProviderId(): string;

  /**
   * Envoie une requête multimodale.
   *
   * @param array<int, array<string, mixed>> $messages
   *   Messages au format interne (role + content string ou blocks image/text).
   * @param array<string, mixed> $options
   *   model, max_tokens, system, skip_french_system, timeout, …
   *
   * @return array<string, mixed>
   *   Réponse JSON décodée du fournisseur.
   *
   * @throws \RuntimeException
   */
  public function sendMessages(array $messages, array $options = []): array;

  /**
   * Extrait le texte principal de la réponse.
   */
  public function extractText(array $response): string;

}
