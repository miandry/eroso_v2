<?php

namespace Drupal\mz_crud;

use Drupal\user\Entity\User;

/**
 * Class APIService.
 */
class APIService
{
    /**
     * Vérifie si un token est valide (ancienne méthode basée sur le password).
     *
     * @param string $name
     *   Le nom d'utilisateur.
     * @param string $token
     *   Le token à vérifier.
     *
     * @return bool
     *   TRUE si valide, FALSE sinon.
     */
    public function isTokenValid($name, $token)
    {
        $user = user_load_by_name($name);
        if (!is_object($user)) {
            return false;
        }
        $hashed_password = $user->getPassword();
        $token_new = \Drupal\Component\Utility\Crypt::hashBase64($hashed_password);
        return ($token_new == $token);
    }

    /**
     * Vérifie si un nom d'utilisateur existe déjà.
     *
     * @param string $name
     *   Le nom d'utilisateur à vérifier.
     *
     * @return bool
     *   TRUE si existe, FALSE sinon.
     */
    public function isUserNameExist($name)
    {
        $query = \Drupal::entityQuery('user')
            ->condition('name', $name);
        $query->range(0, 1);
        $result = $query->execute();
        if (!empty($result)) {
            return true;
        }
        return false;
    }

    /**
     * Génère un token basé sur le password (ancienne méthode).
     *
     * @param object $user
     *   L'utilisateur.
     *
     * @return string|false
     *   Le token ou FALSE si erreur.
     */
    public function generateToken($user)
    {
        if (!is_object($user)) {
            return false;
        }
        $hashed_password = $user->getPassword();
        $token_new = \Drupal\Component\Utility\Crypt::hashBase64($hashed_password);
        return $token_new;
    }

    // =============== NOUVELLES MÉTHODES POUR TOKEN BEARER ===============

    /**
     * Génère un token Bearer unique pour un utilisateur.
     *
     * @param \Drupal\user\Entity\User $user
     *   L'utilisateur pour lequel générer le token.
     * @param int $days
     *   Nombre de jours avant expiration (défaut: 30).
     *
     * @return string
     *   Le token généré.
     */
    public function generateBearerToken($user, $days = 30)
    {
        // Générer un token unique et sécurisé (64 caractères hexadécimaux)
        $token = bin2hex(random_bytes(32));

        // Calculer la date d'expiration
        $expiration = time() + ($days * 24 * 60 * 60);

        // Supprimer les anciens tokens de cet utilisateur
        \Drupal::database()->delete('mz_crud_tokens')
            ->condition('uid', $user->id())
            ->execute();

        // Insérer le nouveau token
        \Drupal::database()->insert('mz_crud_tokens')
            ->fields([
                'uid' => $user->id(),
                'token' => $token,
                'created' => time(),
                'expiration' => $expiration,
            ])
            ->execute();

        return $token;
    }

    /**
     * Valide un token Bearer et retourne l'utilisateur associé.
     *
     * @param string $token
     *   Le token à valider.
     *
     * @return \Drupal\user\Entity\User|null
     *   L'utilisateur ou null si token invalide/expiré.
     */
    public function validateBearerToken($token)
    {
        if (empty($token)) {
            return null;
        }

        $query = \Drupal::database()->select('mz_crud_tokens', 't')
            ->fields('t', ['uid'])
            ->condition('token', $token)
            ->condition('expiration', time(), '>')
            ->execute()
            ->fetchAssoc();

        if ($query && isset($query['uid'])) {
            return User::load($query['uid']);
        }

        return null;
    }

    /**
     * Récupère un utilisateur par son token (alias de validateBearerToken).
     *
     * @param string $token
     *   Le token.
     *
     * @return \Drupal\user\Entity\User|null
     *   L'utilisateur ou null.
     */
    public function getUserByToken($token)
    {
        return $this->validateBearerToken($token);
    }

    /**
     * Vérifie si un token Bearer est valide (sans charger l'utilisateur).
     *
     * @param string $token
     *   Le token à vérifier.
     *
     * @return bool
     *   TRUE si valide, FALSE sinon.
     */
    public function isBearerTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $count = \Drupal::database()->select('mz_crud_tokens', 't')
            ->fields('t', ['uid'])
            ->condition('token', $token)
            ->condition('expiration', time(), '>')
            ->countQuery()
            ->execute()
            ->fetchField();

        return $count > 0;
    }

    /**
     * Invalide un token Bearer (pour logout).
     *
     * @param string $token
     *   Le token à invalider.
     */
    public function invalidateBearerToken($token)
    {
        if (!empty($token)) {
            \Drupal::database()->delete('mz_crud_tokens')
                ->condition('token', $token)
                ->execute();
        }
    }

    /**
     * Invalide tous les tokens d'un utilisateur.
     *
     * @param int $uid
     *   L'ID de l'utilisateur.
     */
    public function invalidateUserTokens($uid)
    {
        if (!empty($uid)) {
            \Drupal::database()->delete('mz_crud_tokens')
                ->condition('uid', $uid)
                ->execute();
        }
    }

    /**
     * Nettoie les tokens expirés (à appeler périodiquement).
     *
     * @return int
     *   Nombre de tokens supprimés.
     */
    public function cleanupExpiredTokens()
    {
        return \Drupal::database()->delete('mz_crud_tokens')
            ->condition('expiration', time(), '<')
            ->execute();
    }
}
