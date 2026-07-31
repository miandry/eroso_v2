import axios from 'axios'

const isLocal = typeof window !== 'undefined' &&
  (window.location.hostname === 'localhost' ||
    window.location.hostname.endsWith('.local') ||
    window.location.hostname.includes('127.0.0.1'));

const BASE_URL_LOCAL = 'http://eroso.local:8888';
const BASE_URL_ONLINE = 'https://eroso-madagascar.com';

const API_BASE_URL = isLocal ? BASE_URL_LOCAL : BASE_URL_ONLINE;

const api = axios.create({
  baseURL: API_BASE_URL,
  // Allow HTTP-only cookie auth (auth_token) for mz_crud/api_solutions.
  withCredentials: true,
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const app = typeof localStorage !== 'undefined' ? localStorage.getItem('eroso_app') : null;
  if (app) {
    config.headers = config.headers || {};
    config.headers['X-Eroso-App'] = app;
  }

  // Attach bearer token header on every request when a token exists in storage.
  const token = typeof localStorage !== 'undefined' ? localStorage.getItem('token') : null;
  if (token) {
    config.headers = config.headers || {};
    if (!config.headers['Authorization']) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
    if (!config.headers['X-Auth-Token']) {
      config.headers['X-Auth-Token'] = token;
    }
  }

  return config;
})

// Global 401 handler: token expired/invalid → clear session and redirect to /login.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status;
    const data   = error?.response?.data;

    const isTokenError =
      status === 401 &&
      typeof data === 'object' &&
      data !== null &&
      (
        String(data.message || '').toLowerCase().includes('token') ||
        String(data.message || '').toLowerCase().includes('session') ||
        String(data.error   || '').toLowerCase().includes('token')
      );

    if (isTokenError && typeof localStorage !== 'undefined') {
      localStorage.removeItem('token');
      localStorage.removeItem('username');
      localStorage.removeItem('uid');
      localStorage.removeItem('roles');
      localStorage.setItem('login_redirect_message', 'Votre session a expiré. Veuillez vous reconnecter.');
      if (typeof window !== 'undefined') {
        window.location.href = '/login';
      }
    }

    return Promise.reject(error);
  }
)

// /api/v2/[entity]/[content_type]


export function getListUser(parameters = null) {
  let path = '/api/v2/users';
  if (parameters) {
    path = path + (path.includes('?') ? '&' : '?') + parameters;
  }
  return api.get(path);
}



export function getLists(entity, content_type, parameters = null) {
  let path = 'api_solutions/api/v2/' + entity + '/' + content_type;
  if (parameters) {
    path = path + (path.includes('?') ? '&' : '?') + parameters;
  }
  return api.get(path);
}

export function getDetail(entity, content_type, id, parameters = null) {
  let path = 'api_solutions/api/v2/' + entity + '/' + content_type + '/' + id;
  if (parameters) {
    path = path + (path.includes('?') ? '&' : '?') + parameters;
  }
  return api.get(path);
}

export function saveItem(data) {
  const payload = {
    ...data,
    author: data.author || localStorage.getItem('username') || '',
    token: data.token || localStorage.getItem('token') || ''
  };
  return api.post('/api_solutions/save', payload);
}

export function login(credentials) {
  return api.post('/api_solutions/user/login', credentials);
}

export function logout() {
  // Prefer api_solutions endpoint; some environments expose /crud/logout instead.
  return api.post('/api_solutions/user/logout');
}

export function logoutCrud() {
  return api.post('/crud/logout');
}

export function loginCrud(credentials) {
  return api.post('/crud/login', credentials);
}

// mz_eroso_v2 - cancel an order_local + rollback stock
export function cancelOrderLocal(payload) {
  // payload example: { nid: 123, token?: '...' }
  return api.post('/api/v2/order-local/cancel', payload);
}

// mz_eroso_v2 - update field_status_local (admin only)
export function updateOrderLocalStatus(payload) {
  // payload example: { nid: 123, status: 'payer', token: '...' }
  return api.post('/api/v2/order-local/update-status', payload);
}

// mz_eroso_v2 - update a cart line's unit price on an order_local (admin only).
// Also recomputes and persists the parent order's field_total.
export function updateOrderLocalCartPrice(payload) {
  // payload example: { order_nid: 123, cart_nid: 456, prix_unitaire: 7500, token: '...' }
  return api.post('/api/v2/order-local/update-cart-price', payload);
}

// mz_eroso_v2 - supprime une ligne panier order_local + remise en stock (admin only).
export function deleteOrderLocalCartLine(payload) {
  // payload example: { order_nid: 123, cart_nid: 456, token: '...' }
  return api.post('/api/v2/order-local/delete-cart-line', payload);
}

export function uploadFile(file) {
  const formData = new FormData();
  formData.append('file', file);
  return api.post('/api_solutions/action/uploader', formData, {
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  });
}

export function getCategories() {
  return api.get('/api_solutions/api/v2/taxonomy_term/category');
}

// Stock Statistics API from mz_eroso_v2 module
export function getStockStats(period = 'today') {
  return api.get(`/api/v2/stock/stats?period=${period}`);
}

export function getStockEntries(period = 'today', limit = 50, offset = 0) {
  return api.get(`/api/v2/stock/entries?period=${period}&limit=${limit}&offset=${offset}`);
}

export function getStockExits(period = 'today', limit = 50, offset = 0) {
  return api.get(`/api/v2/stock/exits?period=${period}&limit=${limit}&offset=${offset}`);
}

/**
 * Liste order_local — mz_eroso_v2 OrderLocalApiController::list.
 * Query: offset, pager, sort[val|op], filters[field_status_local][val], search (≥2 chars — produit nom/description, notes),
 * date_from / date_to (Y-m-d, created entre ces jours inclus, timezone site).
 */
export function getOrderLocalList(parameters = null) {
  let path = 'api_solutions/api/v2/mz_eroso/order_local/list';
  if (parameters) {
    path = path + (path.includes('?') ? '&' : '?') + parameters;
  }
  return api.get(path);
}

export function saveOrderLocal(data) {
  const payload = {
    ...data,
    token: data.token || localStorage.getItem('token') || '',
    author: data.author || localStorage.getItem('username') || '',
  };
  return api.post('/api/v2/order-local/save', payload);
}

/** product_commande + cart_commande + order_commande (mz_eroso_v2). */
export function saveOrderCommande(data) {
  const payload = {
    ...data,
    token: data.token || localStorage.getItem('token') || '',
    author: data.author || localStorage.getItem('username') || '',
  };
  return api.post('/api/v2/order-commande/save', payload);
}

/** Transfère des lignes cart_commande → product boutique + entrée stock.
 *  payload: { order_nid, lines: [{ cart_nid, quantity }] } ou cart_nids (legacy).
 */
export function transferOrderCommandeToBoutique(data) {
  const payload = {
    ...data,
    token: data.token || localStorage.getItem('token') || '',
  };
  return api.post('/api/v2/order-commande/transfer-to-boutique', payload);
}

/**
 * Liste order_commande — mz_eroso_v2 OrderCommandeApiController::list.
 * Query: offset, pager, sort[val|op], filters[field_status_commande][val], search (≥2 chars),
 * date_from / date_to (Y-m-d, created entre ces jours inclus, timezone site).
 */
export function getOrderCommandeList(parameters = null) {
  let path = 'api_solutions/api/v2/mz_eroso/order_commande/list';
  if (parameters) {
    path = path + (path.includes('?') ? '&' : '?') + parameters;
  }
  return api.get(path);
}

/** Recherche produits par image (IA — mz_api_integration). */
export function searchProductsByImage(file, bundle = 'product') {
  const formData = new FormData();
  formData.append('image', file);
  formData.append('bundle', bundle);
  const token = localStorage.getItem('token') || '';
  if (token) {
    formData.append('token', token);
  }
  const path = token
    ? `/api_solutions/api/v2/product/search-by-image?token=${encodeURIComponent(token)}`
    : '/api_solutions/api/v2/product/search-by-image';
  return api.post(path, formData);
}

/** Génère field_search_image depuis une photo produit (Claude Vision). */
export function analyzeProductImageForSearch(file) {
  const formData = new FormData();
  formData.append('image', file);
  const token = localStorage.getItem('token') || '';
  if (token) {
    formData.append('token', token);
  }
  const path = token
    ? `/api_solutions/api/v2/product/analyze-image-for-search?token=${encodeURIComponent(token)}`
    : '/api_solutions/api/v2/product/analyze-image-for-search';
  return api.post(path, formData);
}

/** Génère et enregistre field_search_image pour un produit (nid). Fichier optionnel. */
export function generateProductSearchImage(nid, file = null) {
  const token = localStorage.getItem('token') || '';
  const basePath = `/api_solutions/api/v2/product/${nid}/generate-search-image`;

  if (file) {
    const formData = new FormData();
    formData.append('image', file);
    if (token) {
      formData.append('token', token);
    }
    const path = token
      ? `${basePath}?token=${encodeURIComponent(token)}`
      : basePath;
    return api.post(path, formData);
  }

  // Sans fichier : JSON (comme saveItem) — plus fiable en prod que multipart vide.
  const path = token
    ? `${basePath}?token=${encodeURIComponent(token)}`
    : basePath;
  return api.post(path, { token });
}

/** Réglages IA — fournisseur actif (admin). */
export function getAiSettings() {
  return api.get('/api_solutions/api/v2/ai/settings');
}

/** Enregistre le fournisseur IA actif : gemini | claude | chatgpt (admin). */
export function saveAiSettings(aiProvider) {
  return api.post('/api_solutions/api/v2/ai/settings', {
    ai_provider: aiProvider,
    token: localStorage.getItem('token') || '',
  });
}

/** Extrait le message d'erreur d'une réponse API (axios ou JSON status:false). */
export function getApiErrorMessage(error, fallback = 'Une erreur est survenue.') {
  if (!error) return fallback;
  const data = error?.response?.data;
  if (data && typeof data === 'object' && data.message) {
    return String(data.message);
  }
  if (typeof data === 'string' && data.trim() !== '') {
    try {
      const parsed = JSON.parse(data);
      if (parsed?.message) return String(parsed.message);
    } catch {
      // Not JSON — use raw string if short enough.
      if (data.length < 500) return data;
    }
  }
  if (error.message) {
    return String(error.message);
  }
  return fallback;
}