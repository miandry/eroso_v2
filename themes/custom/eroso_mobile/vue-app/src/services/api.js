import axios from 'axios'

const api = axios.create({
  baseURL: 'https://eroso-madagascar.com/',
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

export function saveItem(data) {
  return api.post('/api_solutions/save', data);
}

export function login(credentials) {
  return api.post('/api_solutions/user/login', credentials);
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