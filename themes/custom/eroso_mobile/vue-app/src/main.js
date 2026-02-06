import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'

import App from './App.vue'
import Home from './pages/Home.vue'
import About from './pages/About.vue'
import LoginPage from './pages/LoginPage.vue'
import StockManager from './pages/StockManager.vue'
import ProductList from './pages/ProductList.vue'
import UserList from './pages/user/UserList.vue'


const routes = [
  { path: '/', component: StockManager },
  { path: '/login', component: LoginPage, name: 'login' },
  { path: '/about', component: About },
  { path: '/users', component: UserList },
  { path: '/stock-manager', component: StockManager },
  { path: '/products', component: ProductList }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Navigation Guard
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token');

  if (to.path !== '/login' && !token) {
    // Redirect to login if not authenticated
    next('/login');
  } else if (to.path === '/login' && token) {
    // Redirect to stock-manager if already authenticated
    next('/stock-manager');
  } else {
    next();
  }
});
import { createPinia } from 'pinia'

const pinia = createPinia()

document.addEventListener("DOMContentLoaded", () => {
  const el = document.querySelector('#vue-app')
  if (el) {
    createApp(App).use(router).use(pinia).mount('#vue-app')
  }
})
