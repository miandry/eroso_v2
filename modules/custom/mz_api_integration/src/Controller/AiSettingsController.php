<?php

namespace Drupal\mz_api_integration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mz_api_integration\Service\AiProviderSettings;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API réglages IA (fournisseur actif : Gemini, Claude, ChatGPT).
 */
class AiSettingsController extends ControllerBase {

  public function __construct(
    protected AiProviderSettings $providerSettings,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('mz_api_integration.provider_settings'),
    );
  }

  /**
   * GET — fournisseur IA actif et options disponibles.
   */
  public function getSettings(Request $request): JsonResponse {
    $user = $this->authenticateRequest($request);
    if (!$user) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Non autorisé'], 401);
    }
    if (!$this->isAdministrator($user)) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Accès réservé aux administrateurs.'], 403);
    }

    return new JsonResponse([
      'status' => TRUE,
      ...$this->providerSettings->getSettingsSummary(),
    ]);
  }

  /**
   * POST — enregistre le fournisseur IA actif.
   */
  public function saveSettings(Request $request): JsonResponse {
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['status' => FALSE, 'message' => 'POST required'], 405);
    }

    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body)) {
      $body = [];
    }
    if ($request->request->count()) {
      $body = array_merge($body, $request->request->all());
    }

    $user = $this->authenticateRequest($request, $body);
    if (!$user) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Non autorisé'], 401);
    }
    if (!$this->isAdministrator($user)) {
      return new JsonResponse(['status' => FALSE, 'message' => 'Accès réservé aux administrateurs.'], 403);
    }

    $provider = trim((string) ($body['ai_provider'] ?? ''));
    if ($provider === '') {
      return new JsonResponse(['status' => FALSE, 'message' => 'Paramètre ai_provider requis.'], 400);
    }

    try {
      $this->providerSettings->saveActiveProvider($provider);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['status' => FALSE, 'message' => $e->getMessage()], 400);
    }
    catch (\RuntimeException $e) {
      return new JsonResponse(['status' => FALSE, 'message' => $e->getMessage()], 409);
    }

    return new JsonResponse([
      'status' => TRUE,
      'message' => 'Fournisseur IA mis à jour.',
      ...$this->providerSettings->getSettingsSummary(),
    ]);
  }

  protected function isAdministrator(UserInterface $user): bool {
    return in_array('administrator', $user->getRoles(), TRUE);
  }

  /**
   * Auth token (cookie, Bearer, body).
   */
  private function authenticateRequest(Request $request, $body = NULL) {
    $token = $request->cookies->get('auth_token');
    if (!$token) {
      $auth_header = $request->headers->get('Authorization');
      if ($auth_header && preg_match('/Bearer\s+(.+)/i', $auth_header, $matches)) {
        $token = trim($matches[1]);
      }
    }
    if (!$token) {
      $token = $request->headers->get('X-Auth-Token');
      if (is_string($token)) {
        $token = trim($token);
      }
      else {
        $token = NULL;
      }
    }
    if (!$token && $request->query->has('token')) {
      $token = trim((string) $request->query->get('token'));
    }
    if (!$token && is_array($body) && !empty($body['token'])) {
      $token = $body['token'];
    }
    if (!$token && $request->request->has('token')) {
      $token = $request->request->get('token');
    }
    if (!$token || $token === '') {
      return NULL;
    }
    if (!\Drupal::hasService('api_solutions.api_crud')) {
      return NULL;
    }
    return \Drupal::service('api_solutions.api_crud')->validateBearerToken($token);
  }

}
