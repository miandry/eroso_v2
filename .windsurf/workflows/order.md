---
description: Guide pour la page de commande (OrderPage)
---

# Page de Commande - OrderPage

## Vue d'ensemble
Page permettant de passer des commandes avec une interface mobile-first inspirée d'une interface de point de vente.

## Fonctionnalités

### Layout Mobile
- **Grille de produits** (2 colonnes)
  - Image du produit
  - Nom du produit
  - Prix (sans affichage du stock)
  - Clic pour ajouter au panier

- **Notification Toast**
  - Apparaît lors de l'ajout au panier
  - Affiche le nom du produit ajouté
  - Disparaît après 3 secondes

- **Panneau de commande en bas** (collapsible)
  - Barre réduite par défaut: "Placer commande actuelle" + nombre d'articles
  - S'ouvre uniquement au clic (pas automatiquement)
  - Contenu étendu:
    - Sélection client
    - Liste des articles avec contrôles quantité
    - Sous-total et Total
    - Bouton "Sauvegarder non payé"
    - Bouton "Finaliser la vente"

### Layout Desktop
- Panneau latéral fixe à droite
- Même fonctionnalités que mobile

## Routes
- `/order` - Page de commande

## Composants utilisés
- `OrderPage.vue`
- `Sidebar.vue`
- Stores: `useProductStore`, `useUIStore`

## Comportement
1. Affiche uniquement les produits avec statut "dispo"
2. Recherche par titre ou SKU
3. Ajout au panier par clic sur produit
4. Gestion des quantités (+/-)
5. Sauvegarde dans localStorage
6. Modal de sélection client
7. Modal de confirmation après finalisation

## Intégration
- Accessible depuis AllProductsPage via bouton "Passer commande"
- Navigation dans `main.js`