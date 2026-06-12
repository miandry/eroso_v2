<template>
  <div class="relative z-[100]">
    <!-- Overlay -->
    <transition name="fade">
      <div v-if="isSidebarOpen" @click="uiStore.closeSidebar" class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity lg:hidden"></div>
    </transition>

    <!-- Sidebar Wrapper -->
    <div 
      :class="['fixed top-0 left-0 h-full w-64 bg-white shadow-2xl transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:shadow-none lg:border-r lg:border-gray-200', 
               isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']"
    >
      <!-- Header / User Info -->
      <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">
            {{ username ? username.charAt(0).toUpperCase() : 'U' }}
          </div>
          <div class="min-w-0">
            <h2 class="text-sm font-bold text-gray-900 truncate">{{ username || 'Utilisateur' }}</h2>
            <p class="text-[10px] text-gray-500 uppercase tracking-wider font-semibold">Admin Panel</p>
            <p v-if="spaceLabel" class="text-[11px] text-blue-600 font-medium truncate mt-0.5">{{ spaceLabel }}</p>
          </div>
        </div>
        <button @click="uiStore.closeSidebar" class="p-2 text-gray-400 hover:text-gray-600 lg:hidden">
          <i class="ri-close-line ri-lg"></i>
        </button>
      </div>

      <!-- Navigation — Eroso boutique (tout le parcours actuel) -->
      <div v-if="isBoutiqueSpace" class="p-4 space-y-2 overflow-y-auto h-[calc(100%-160px)]">
        <div class="px-2 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Menu Principal</div>

        <router-link v-if="isAdmin" to="/statistics" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-blue-50 hover:text-blue-600 group">
          <i class="ri-home-4-line text-lg"></i>
          <span class="text-sm font-medium">Tableau de bord</span>
        </router-link>

        <router-link to="/products" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-blue-50 hover:text-blue-600 group">
          <i class="ri-box-3-line text-lg"></i>
          <span class="text-sm font-medium">Produits Disponibles</span>
        </router-link>

        <router-link to="/commandes" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-blue-50 hover:text-blue-600 group">
          <i class="ri-shopping-bag-line text-lg"></i>
          <span class="text-sm font-medium">Commandes</span>
        </router-link>

        <router-link to="/commandes/en-livraison" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-sky-50 hover:text-sky-700 group">
          <i class="ri-truck-line text-lg"></i>
          <span class="text-sm font-medium">En livraison</span>
        </router-link>

        <router-link to="/caisse-locale" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-green-50 hover:text-green-600 group">
          <i class="ri-store-2-line text-lg"></i>
          <span class="text-sm font-medium">Caisse locale</span>
        </router-link>

        <router-link to="/stock-list" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-purple-50 hover:text-purple-600 group">
          <i class="ri-stack-line text-lg"></i>
          <span class="text-sm font-medium">Mouvements stock</span>
        </router-link>

        <router-link to="/stock-insert" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-purple-50 hover:text-purple-600 group">
          <i class="ri-arrow-up-down-line text-lg"></i>
          <span class="text-sm font-medium">Nouveau mouvement</span>
        </router-link>

        <div class="my-4 border-t border-gray-100"></div>
        <div class="px-2 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Paramètres</div>

        <button type="button" class="w-full flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-blue-50 hover:text-blue-600 group">
          <i class="ri-settings-3-line text-lg"></i>
          <span class="text-sm font-medium">Réglages</span>
        </button>
      </div>

      <!-- Eroso sur commande — démarrage du parcours dédié -->
      <div v-else class="p-4 space-y-2 overflow-y-auto h-[calc(100%-160px)]">
        <div class="px-2 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Sur commande</div>

        <router-link to="/sur-commande" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 group">
          <i class="ri-home-4-line text-lg"></i>
          <span class="text-sm font-medium">Accueil</span>
        </router-link>

        <router-link to="/sur-commande/products" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 group">
          <i class="ri-box-3-line text-lg"></i>
          <span class="text-sm font-medium">Catalogue</span>
        </router-link>

        <router-link to="/sur-commande/product-insert" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 group">
          <i class="ri-add-circle-line text-lg"></i>
          <span class="text-sm font-medium">Nouveau produit</span>
        </router-link>

        <router-link to="/sur-commande/caisse" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-violet-50 hover:text-violet-700 group">
          <i class="ri-store-2-line text-lg"></i>
          <span class="text-sm font-medium">Caisse sur commande</span>
        </router-link>

        <router-link to="/sur-commande/orders" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 group">
          <i class="ri-file-list-3-line text-lg"></i>
          <span class="text-sm font-medium">Commandes (order_commande)</span>
        </router-link>
      </div>

      <!-- Footer / Logout -->
      <div class="absolute bottom-0 w-full p-4 bg-gray-50 border-t border-gray-100 space-y-2">
        <button
          type="button"
          @click="goChangeSpace"
          class="w-full flex items-center justify-center space-x-2 bg-white border border-gray-200 text-gray-700 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-50 transition-colors"
        >
          <i class="ri-exchange-line"></i>
          <span>Changer d’espace</span>
        </button>
        <button @click="handleLogout" class="w-full flex items-center justify-center space-x-2 bg-red-50 text-red-600 py-3 rounded-xl font-bold hover:bg-red-100 transition-colors">
          <i class="ri-logout-box-r-line"></i>
          <span>Déconnexion</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useUIStore } from '../stores/useUIStore';
import { logout, logoutCrud } from '../services/api';
import { clearSelectedApp, getSelectedAppMeta, getSelectedAppId, SPACE_BOUTIQUE } from '../config/appContext';

const router = useRouter();
const uiStore = useUIStore();
const spaceLabel = computed(() => getSelectedAppMeta()?.title || '');
const isBoutiqueSpace = computed(() => getSelectedAppId() === SPACE_BOUTIQUE);
const isSidebarOpen = computed(() => uiStore.isSidebarOpen);

const username = localStorage.getItem('username');

// Check if user has administrator role
const isAdmin = computed(() => {
  const rolesStr = localStorage.getItem('roles');
  if (!rolesStr) return false;
  
  try {
    const roles = JSON.parse(rolesStr);
    return Array.isArray(roles) && roles.includes('administrator');
  } catch (e) {
    return false;
  }
});

const goChangeSpace = () => {
  clearSelectedApp();
  uiStore.closeSidebar();
  router.push('/front-desk');
};

const handleLogout = async () => {
  // Best effort: invalidate server token + clear HTTP-only cookie.
  try {
    await logout();
  } catch (e) {
    try {
      await logoutCrud();
    } catch (_) {
      // ignore - local logout still happens
    }
  } finally {
    localStorage.removeItem('token');
    localStorage.removeItem('username');
    localStorage.removeItem('uid');
    localStorage.removeItem('roles');
    uiStore.closeSidebar();
    router.push('/login');
  }
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.router-link-active {
  background-color: rgb(239 246 255);
  color: rgb(37 99 235);
}

.router-link-active i {
  color: rgb(37 99 235);
}
</style>
