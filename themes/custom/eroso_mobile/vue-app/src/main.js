import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'

import App from './App.vue'
import About from './pages/About.vue'
import LoginPage from './pages/LoginPage.vue'
import ProductList from './pages/ProductList.vue'
import ProductInsert from './pages/ProductInsert.vue'
import ProductDetail from './pages/ProductDetail.vue'
import StockInsertPage from './pages/StockInsertPage.vue'
import StatisticsPage from './pages/StatisticsPage.vue'
import CommandesPage from './pages/CommandesPage.vue'
import CaisseLocalePage from './pages/CaisseLocalePage.vue'
import FrontDeskPage from './pages/FrontDeskPage.vue'
import SurCommandeHomePage from './pages/eroso_commande/SurCommandeHomePage.vue'
import ProductListCommande from './pages/eroso_commande/ProductListCommande.vue'
import ProductInsertCommande from './pages/eroso_commande/ProductInsertCommande.vue'
import CaisseCommandePage from './pages/eroso_commande/CaisseCommandePage.vue'
import OrderListCommande from './pages/eroso_commande/OrderListCommande.vue'
import OrderDetailCommande from './pages/eroso_commande/OrderDetailCommande.vue'
import { EROSO_APP_STORAGE_KEY, getHomePathForApp } from './config/appContext'

const spaceBoutique = { space: 'boutique' }
const spaceSurCommande = { space: 'sur_commande' }

const routes = [
  { path: '/front-desk', component: FrontDeskPage, name: 'front-desk' },
  { path: '/', component: StatisticsPage, meta: spaceBoutique },
  { path: '/login', component: LoginPage, name: 'login' },
  { path: '/about', component: About, meta: spaceBoutique },
  { path: '/products', component: ProductList, meta: spaceBoutique },
  { path: '/stock-insert', component: StockInsertPage, meta: spaceBoutique },
  { path: '/caisse-locale', component: CaisseLocalePage, meta: spaceBoutique },
  { path: '/product-insert', component: ProductInsert, meta: spaceBoutique },
  { path: '/product/:id', component: ProductDetail, meta: spaceBoutique },
  { path: '/statistics', component: StatisticsPage, meta: spaceBoutique },
  { path: '/commandes', component: CommandesPage, meta: spaceBoutique },
  { path: '/sur-commande/products', component: ProductListCommande, meta: spaceSurCommande },
  { path: '/sur-commande/caisse', component: CaisseCommandePage, meta: spaceSurCommande },
  { path: '/sur-commande/orders', component: OrderListCommande, meta: spaceSurCommande },
  { path: '/sur-commande/order/:nid', component: OrderDetailCommande, meta: spaceSurCommande },
  { path: '/sur-commande/product-insert', component: ProductInsertCommande, meta: spaceSurCommande },
  { path: '/sur-commande/product/:id', component: ProductDetail, meta: { space: 'sur_commande', productBundle: 'product_commande' } },
  { path: '/sur-commande', component: SurCommandeHomePage, name: 'sur-commande-home', meta: spaceSurCommande },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Navigation Guard: commercial space → auth
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token');
  const app = localStorage.getItem(EROSO_APP_STORAGE_KEY);

  if (!app && to.path !== '/front-desk') {
    next('/front-desk');
    return;
  }

  // Logged-in users with an app already chosen should not stay on the picker (bookmark / back).
  if (to.path === '/front-desk' && token && app) {
    next(getHomePathForApp(app));
    return;
  }

  if (to.path !== '/login' && to.path !== '/front-desk' && !token) {
    next('/login');
    return;
  }

  if (to.path === '/login' && token) {
    next(getHomePathForApp(app));
    return;
  }

  // Boutique routes vs Eroso sur commande — each space only sees its own screens.
  if (token && app && to.meta.space && to.meta.space !== app) {
    next(getHomePathForApp(app));
    return;
  }

  next();
});
import { createPinia } from 'pinia'

const pinia = createPinia()

document.addEventListener("DOMContentLoaded", () => {
  const el = document.querySelector('#vue-app')
  if (el) {
    createApp(App).use(router).use(pinia).mount('#vue-app')
  }
})
