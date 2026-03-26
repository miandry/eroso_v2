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