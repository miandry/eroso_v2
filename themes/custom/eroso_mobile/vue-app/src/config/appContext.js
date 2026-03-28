/** Selected commercial space — drives UI labels and optional API header. */
export const EROSO_APP_STORAGE_KEY = 'eroso_app';

/** All existing stock / caisse / produits screens belong to this space. */
export const SPACE_BOUTIQUE = 'boutique';
/** Order-driven flows (en construction). */
export const SPACE_SUR_COMMANDE = 'sur_commande';

export const EROSO_APPS = {
  boutique: {
    id: 'boutique',
    title: 'Eroso boutique',
    description: 'Vente en magasin, stock et caisse locale.',
    icon: 'ri-store-2-line',
    accent: 'from-emerald-600 to-teal-700',
  },
  sur_commande: {
    id: 'sur_commande',
    title: 'Eroso sur commande',
    description: 'Commandes, préparation et suivi des commandes.',
    icon: 'ri-shopping-bag-3-line',
    accent: 'from-indigo-600 to-violet-800',
  },
};

export function getSelectedAppId() {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem(EROSO_APP_STORAGE_KEY);
}

export function setSelectedAppId(id) {
  if (typeof window === 'undefined') return;
  if (id && EROSO_APPS[id]) {
    localStorage.setItem(EROSO_APP_STORAGE_KEY, id);
  }
}

export function clearSelectedApp() {
  if (typeof window === 'undefined') return;
  localStorage.removeItem(EROSO_APP_STORAGE_KEY);
}

export function getSelectedAppMeta() {
  const id = getSelectedAppId();
  return id ? EROSO_APPS[id] : null;
}

/** Default route after login / front-desk for the selected space. */
export function getHomePathForApp(appId) {
  if (appId === SPACE_SUR_COMMANDE) {
    return '/sur-commande/products';
  }
  return '/products';
}

export function isBoutiqueApp(appId) {
  return appId === SPACE_BOUTIQUE;
}
