import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'

import App from './App.vue'
import Home from './pages/Home.vue'
import About from './pages/About.vue'
import LoginPage from './pages/LoginPage.vue'
import ProductList from './pages/ProductList.vue'
import ProductInsert from './pages/ProductInsert.vue'
import ProductDetail from './pages/ProductDetail.vue'
import StockInsertPage from './pages/StockInsertPage.vue'
import StatisticsPage from './pages/StatisticsPage.vue'
import CommandesPage from './pages/CommandesPage.vue'
import CaisseLocalePage from './pages/CaisseLocalePage.vue'


const routes = [
  { path: '/', component: StatisticsPage },
  { path: '/login', component: LoginPage, name: 'login' },
  { path: '/about', component: About },
  { path: '/products', component: ProductList },
  { path: '/stock-insert', component: StockInsertPage },
  { path: '/caisse-locale', component: CaisseLocalePage },
  { path: '/product-insert', component: ProductInsert },
  { path: '/product/:id', component: ProductDetail },
  { path: '/statistics', component: StatisticsPage },
  { path: '/commandes', component: CommandesPage }
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
    // Redirect to products if already authenticated
    next('/products');
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
