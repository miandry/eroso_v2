<?php

namespace Drupal\mz_crud\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Class ApiController.
 */
class ApiController extends ControllerBase
{

    /**
     * Login with HTTP-Only cookie.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $method = $request->getMethod();

        if ($method == "POST") {
            $content = $request->getContent();
            if (!empty($content)) {
                $data = json_decode($content, TRUE);

                if (empty($data['name']) || empty($data['password'])) {
                    return new JsonResponse([
                        'status' => false,
                        'message' => 'Nom d\'utilisateur et mot de passe requis'
                    ], 400);
                }

                $user = user_load_by_name($data['name']);

                if (is_object($user)) {
                    $hashed_password = $user->getPassword();
                    $password_hasher = \Drupal::service('password');
                    $password = $data['password'];

                    if ($password_hasher->check($password, $hashed_password)) {
                        $service = \Drupal::service('api.crud');

                        // Générer un nouveau token Bearer
                        $token = $service->generateBearerToken($user);

                        // Créer la réponse JSON (sans le token dans le body)
                        $response = new JsonResponse([
                            'status' => true,
                            'message' => 'Connexion réussie',
                            'user' => [
                                'id' => $user->id(),
                                'name' => $user->getAccountName(),
                                'mail' => $user->getEmail(),
                                'roles' => $user->getRoles()
                            ]
                        ]);

                        // Créer un cookie HTTP-Only avec le token
                        $cookie = new Cookie(
                            'auth_token',      // Nom du cookie
                            $token,            // Valeur (le token)
                            time() + 3600,     // Expiration (1 heure)
                            '/',                // Path (disponible sur tout le site)
                            null,               // Domain (null = domaine actuel)
                            false,               // Secure (HTTPS only)
                            true,               // HttpOnly (inaccessible par JavaScript)
                            false,              // Raw
                            'Lax'               // SameSite (protection CSRF)
                        );

                        // Ajouter le cookie à la réponse
                        $response->headers->setCookie($cookie);

                        return $response;
                    } else {
                        return new JsonResponse([
                            'status' => false,
                            'message' => 'Mot de passe incorrect'
                        ], 401);
                    }
                } else {
                    return new JsonResponse([
                        'status' => false,
                        'message' => 'Utilisateur non trouvé'
                    ], 404);
                }
            }
        }

        return new JsonResponse([
            'status' => false,
            'message' => 'Méthode non autorisée. Utilisez POST'
        ], 405);
    }

    /**
     * Register a new user and generate token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request)
    {
        $service = \Drupal::service('api.crud');
        $method = $request->getMethod();
        $json['status'] = false;

        if ($method == "POST") {
            $content = $request->getContent();

            if (!empty($content)) {
                $data = json_decode($content, TRUE);

                if (empty($data['name']) || empty($data['pass'])) {
                    return new JsonResponse([
                        'status' => false,
                        'message' => 'Nom d\'utilisateur et mot de passe requis'
                    ], 400);
                }

                $status = $service->isUserNameExist($data['name']);

                if ($status) {
                    $json['name'] = $data['name'];
                    $json['error'] = "Nom d'utilisateur existe déjà";
                    $json['status'] = false;
                } else {
                    $user = User::create();
                    $user->setPassword($data['pass']);
                    $user->enforceIsNew();
                    $user->setEmail($data['email'] ?? "email@yahoo.fr");
                    $user->setUsername($data['name']);

                    $saved = $user->save();

                    if ($saved) {
                        // Générer un token Bearer
                        $token = $service->generateBearerToken($user);

                        // Créer la réponse
                        $response = new JsonResponse([
                            'status' => true,
                            'message' => 'Inscription réussie',
                            'user' => [
                                'id' => $user->id(),
                                'name' => $user->getAccountName(),
                                'mail' => $user->getEmail()
                            ]
                        ]);

                        // Créer un cookie HTTP-Only avec le token
                        $cookie = new Cookie(
                            'auth_token',
                            $token,
                            time() + 3600,
                            '/',
                            null,
                            true,
                            true,
                            false,
                            'Lax'
                        );

                        $response->headers->setCookie($cookie);

                        return $response;
                    } else {
                        return new JsonResponse([
                            'status' => false,
                            'message' => 'Erreur lors de la création du compte'
                        ], 500);
                    }
                }
            }
        }

        return new JsonResponse($json, 400);
    }

    /**
     * Save content with HTTP-Only cookie authentication.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function save(Request $request)
    {
        $method = $request->getMethod();
        $id = null;
        $message = '';

        if ($method == "POST") {
            // Récupérer le token depuis le cookie HTTP-Only
            $token = $request->cookies->get('auth_token');

            if (!$token) {
                return new JsonResponse([
                    'message' => 'Non authentifié. Veuillez vous connecter.',
                    'status' => 'error'
                ], 401);
            }

            $content = $request->getContent();

            if (!empty($content)) {
                $content = json_decode($content, TRUE);
                $service = \Drupal::service('api.crud');

                // Valider le token
                $user = $service->validateBearerToken($token);

                if ($user) {
                    $entity_type = $content["entity_type"] ?? '';
                    $bundle = $content["bundle"] ?? '';

                    // Définir l'auteur automatiquement pour les nodes
                    if ($entity_type == 'node' && !isset($content['uid'])) {
                        $content['uid'] = $user->id();
                    }

                    unset($content["bundle"]);
                    unset($content["entity_type"]);

                    $elemt = \Drupal::service('crud')->save($entity_type, $bundle, $content);

                    if (is_object($elemt)) {
                        $id = $elemt->id();
                        return new JsonResponse([
                            'item' => $id,
                            'status' => true
                        ], 200);
                    } else {
                        $message = "Erreur lors de la sauvegarde";
                    }
                } else {
                    // Token invalide, supprimer le cookie
                    $message = "Session expirée. Veuillez vous reconnecter.";
                    $response = new JsonResponse([
                        'message' => $message,
                        'status' => 'error'
                    ], 401);

                    $response->headers->clearCookie('auth_token', '/');
                    return $response;
                }
            } else {
                $message = "Données non trouvées";
            }
        } else {
            $message = "Méthode non autorisée. Utilisez POST";
        }

        return new JsonResponse([
            'message' => $message,
            'status' => 'error'
        ], 400);
    }

    /**
     * Create a new user (admin function).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createUser(Request $request)
    {
        // Vérifier le token d'administration (optionnel)
        $token = $request->cookies->get('auth_token');
        if (!$token) {
            return new JsonResponse([
                'status' => false,
                'error' => 'Non authentifié'
            ], 401);
        }

        $service = \Drupal::service('api.crud');
        $admin_user = $service->validateBearerToken($token);

        // Vérifier si l'utilisateur a le rôle admin
        if (!$admin_user || !in_array('administrator', $admin_user->getRoles())) {
            return new JsonResponse([
                'status' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $method = $request->getMethod();

        if ($method == "POST") {
            $content = $request->getContent();

            if (!empty($content)) {
                $data = json_decode($content, TRUE);

                if (!empty($data['name']) && !empty($data['pass'])) {

                    $exist = $service->isUserNameExist($data['name']);

                    if ($exist) {
                        return new JsonResponse([
                            'status' => false,
                            'error' => 'Username existe déjà'
                        ], 400);
                    } else {

                        $user = User::create([
                            'name' => $data['name'],
                            'mail' => $data['mail'] ?? '',
                            'status' => 1
                        ]);

                        $user->setPassword($data['pass']);

                        // Ajouter plusieurs roles
                        if (!empty($data['roles']) && is_array($data['roles'])) {
                            foreach ($data['roles'] as $role) {
                                $user->addRole($role);
                            }
                        }

                        $user->save();

                        return new JsonResponse([
                            'status' => true,
                            'user' => [
                                'id' => $user->id(),
                                'name' => $user->getAccountName(),
                                'mail' => $user->getEmail(),
                                'roles' => $user->getRoles(),
                                'created' => $user->getCreatedTime(),
                            ]
                        ], 201);
                    }
                }
            }
        }

        return new JsonResponse([
            'status' => false,
            'error' => 'Données invalides'
        ], 400);
    }

    /**
     * Edit user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function user_edit(Request $request)
    {
        // Vérifier l'authentification
        $token = $request->cookies->get('auth_token');

        if (!$token) {
            return new JsonResponse([
                'status' => false,
                'error' => 'Non authentifié'
            ], 401);
        }

        $service = \Drupal::service('api.crud');
        $current_user = $service->validateBearerToken($token);

        if (!$current_user) {
            $response = new JsonResponse([
                'status' => false,
                'error' => 'Session expirée'
            ], 401);
            $response->headers->clearCookie('auth_token', '/');
            return $response;
        }

        $method = $request->getMethod();

        if ($method === "POST") {
            $content = $request->getContent();
            if (!empty($content)) {
                $data = json_decode($content, TRUE);

                // require uid to identify the user
                if (!empty($data['uid'])) {
                    // Vérifier que l'utilisateur édite son propre compte ou est admin
                    if ($data['uid'] != $current_user->id() && !in_array('administrator', $current_user->getRoles())) {
                        return new JsonResponse([
                            'status' => false,
                            'error' => 'Vous ne pouvez modifier que votre propre compte'
                        ], 403);
                    }

                    $user = User::load($data['uid']);
                    if (is_object($user)) {
                        // Vérifier l'unicité du nom si modifié
                        if (!empty($data['name']) && $data['name'] !== $user->getAccountName()) {
                            if ($service->isUserNameExist($data['name'])) {
                                return new JsonResponse([
                                    'status' => false,
                                    'error' => 'Le nom d\'utilisateur est déjà pris'
                                ], 400);
                            }
                            $user->setUsername($data['name']);
                        }

                        if (isset($data['mail'])) {
                            $user->setEmail($data['mail']);
                        }

                        if (isset($data['pass']) && $data['pass'] !== '') {
                            $user->setPassword($data['pass']);
                        }

                        if (isset($data['status']) && in_array('administrator', $current_user->getRoles())) {
                            // Seul l'admin peut changer le statut
                            $user->set('status', $data['status'] ? 1 : 0);
                        }

                        if (!empty($data['roles']) && is_array($data['roles']) && in_array('administrator', $current_user->getRoles())) {
                            // Seul l'admin peut changer les rôles
                            $user->set('roles', $data['roles']);
                        }

                        $saved = $user->save();

                        // Si le mot de passe a changé, régénérer le token
                        if (isset($data['pass']) && $data['pass'] !== '' && $data['uid'] == $current_user->id()) {
                            $service->invalidateUserTokens($user->id());
                            $new_token = $service->generateBearerToken($user);

                            $response = new JsonResponse([
                                'status' => (bool) $saved,
                                'message' => 'Compte mis à jour. Veuillez vous reconnecter.',
                                'user' => [
                                    'uid' => $user->id(),
                                    'name' => $user->getAccountName(),
                                    'mail' => $user->getEmail()
                                ]
                            ]);

                            // Supprimer l'ancien cookie
                            $response->headers->clearCookie('auth_token', '/');

                            return $response;
                        }

                        return new JsonResponse([
                            'status' => (bool) $saved,
                            'user' => [
                                'uid' => $user->id(),
                                'name' => $user->getAccountName(),
                                'mail' => $user->getEmail(),
                                'roles' => $user->getRoles(),
                                'status' => $user->isActive(),
                            ]
                        ]);
                    } else {
                        return new JsonResponse([
                            'status' => false,
                            'error' => 'Utilisateur introuvable'
                        ], 404);
                    }
                } else {
                    return new JsonResponse([
                        'status' => false,
                        'error' => 'UID manquant'
                    ], 400);
                }
            }
        }

        return new JsonResponse([
            'status' => false,
            'error' => 'Méthode non autorisée'
        ], 405);
    }

    /**
     * Logout - remove HTTP-Only cookie.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        $token = $request->cookies->get('auth_token');

        if ($token) {
            $service = \Drupal::service('api.crud');
            $service->invalidateBearerToken($token);
        }

        // Créer une réponse
        $response = new JsonResponse([
            'status' => true,
            'message' => 'Déconnexion réussie'
        ]);

        // Supprimer le cookie
        $response->headers->clearCookie('auth_token', '/');

        return $response;
    }

    /**
     * Check if user is authenticated.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAuth(Request $request)
    {
        $token = $request->cookies->get('auth_token');

        if (!$token) {
            return new JsonResponse([
                'authenticated' => false,
                'message' => 'Non authentifié'
            ], 401);
        }

        $service = \Drupal::service('api.crud');
        $user = $service->validateBearerToken($token);

        if ($user) {
            return new JsonResponse([
                'authenticated' => true,
                'user' => [
                    'id' => $user->id(),
                    'name' => $user->getAccountName(),
                    'mail' => $user->getEmail(),
                    'roles' => $user->getRoles()
                ]
            ]);
        } else {
            // Token invalide, supprimer le cookie
            $response = new JsonResponse([
                'authenticated' => false,
                'message' => 'Session expirée'
            ], 401);

            $response->headers->clearCookie('auth_token', '/');

            return $response;
        }
    }

    /**
     * Change user password.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request)
    {
        // Vérifier l'authentification
        $token = $request->cookies->get('auth_token');

        if (!$token) {
            return new JsonResponse([
                'status' => false,
                'error' => 'Non authentifié'
            ], 401);
        }

        $service = \Drupal::service('api.crud');
        $current_user = $service->validateBearerToken($token);

        if (!$current_user) {
            $response = new JsonResponse([
                'status' => false,
                'error' => 'Session expirée'
            ], 401);
            $response->headers->clearCookie('auth_token', '/');
            return $response;
        }

        $method = $request->getMethod();

        if ($method === "POST") {
            $content = $request->getContent();
            if (!empty($content)) {
                $data = json_decode($content, TRUE);

                // Vérifier les champs requis
                if (empty($data['current_password']) || empty($data['new_password'])) {
                    return new JsonResponse([
                        'status' => false,
                        'error' => 'Mot de passe actuel et nouveau mot de passe requis'
                    ], 400);
                }

                // Vérifier que le nouveau mot de passe est différent
                if ($data['current_password'] === $data['new_password']) {
                    return new JsonResponse([
                        'status' => false,
                        'error' => 'Le nouveau mot de passe doit être différent de l\'ancien'
                    ], 400);
                }

                // Vérifier l'ancien mot de passe
                $password_hasher = \Drupal::service('password');
                $current_hashed_password = $current_user->getPassword();

                if (!$password_hasher->check($data['current_password'], $current_hashed_password)) {
                    return new JsonResponse([
                        'status' => false,
                        'error' => 'Mot de passe actuel incorrect'
                    ], 401);
                }

                // Valider la force du nouveau mot de passe (optionnel)
                if (strlen($data['new_password']) < 6) {
                    return new JsonResponse([
                        'status' => false,
                        'error' => 'Le nouveau mot de passe doit contenir au moins 6 caractères'
                    ], 400);
                }

                // Changer le mot de passe
                $current_user->setPassword($data['new_password']);
                $saved = $current_user->save();

                if ($saved) {
                    // Invalider l'ancien token
                    $service->invalidateBearerToken($token);

                    // Générer un nouveau token
                    $new_token = $service->generateBearerToken($current_user);

                    // Créer la réponse avec le nouveau cookie
                    $response = new JsonResponse([
                        'status' => true,
                        'message' => 'Mot de passe changé avec succès. Veuillez vous reconnecter.',
                        'user' => [
                            'id' => $current_user->id(),
                            'name' => $current_user->getAccountName(),
                            'mail' => $current_user->getEmail()
                        ]
                    ]);

                    // Créer un nouveau cookie HTTP-Only avec le nouveau token
                    $cookie = new Cookie(
                        'auth_token',
                        $new_token,
                        time() + 3600,
                        '/',
                        null,
                        false,
                        true,
                        false,
                        'Lax'
                    );

                    $response->headers->setCookie($cookie);

                    return $response;
                } else {
                    return new JsonResponse([
                        'status' => false,
                        'error' => 'Erreur lors du changement de mot de passe'
                    ], 500);
                }
            }
        }

        return new JsonResponse([
            'status' => false,
            'error' => 'Méthode non autorisée'
        ], 405);
    }
}
