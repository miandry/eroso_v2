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

export function getOrderLocalList(parameters = null) {
  let path = 'api_solutions/api/v2/node/order_local';
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