<template>
  <div class="relative z-[100]">
    <!-- Overlay -->
    <transition name="fade">
      <div v-if="isSidebarOpen" @click="uiStore.closeSidebar" class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity"></div>
    </transition>

    <!-- Sidebar Wrapper -->
    <div 
      :class="['fixed top-0 left-0 h-full w-72 bg-white shadow-2xl transform transition-transform duration-300 ease-in-out', 
               isSidebarOpen ? 'translate-x-0' : '-translate-x-full']"
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
          </div>
        </div>
        <button @click="uiStore.closeSidebar" class="p-2 text-gray-400 hover:text-gray-600">
          <i class="ri-close-line ri-lg"></i>
        </button>
      </div>

      <!-- Navigation -->
      <div class="p-4 space-y-2 overflow-y-auto h-[calc(100%-160px)]">
        <div class="px-2 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Menu Principal</div>
        
        <router-link to="/stock-manager" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-blue-50 hover:text-blue-600 group">
          <i class="ri-home-4-line text-lg"></i>
          <span class="text-sm font-medium">Tableau de bord</span>
        </router-link>

        <router-link to="/products" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-blue-50 hover:text-blue-600 group">
          <i class="ri-box-3-line text-lg"></i>
          <span class="text-sm font-medium">Catalogue Produits</span>
        </router-link>

        <router-link to="/users" @click="uiStore.closeSidebar" class="flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-blue-50 hover:text-blue-600 group">
          <i class="ri-group-line text-lg"></i>
          <span class="text-sm font-medium">Utilisateurs</span>
        </router-link>

        <div class="my-4 border-t border-gray-100"></div>
        <div class="px-2 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Paramètres</div>

        <button class="w-full flex items-center space-x-3 px-3 py-3 rounded-xl transition-colors text-gray-600 hover:bg-blue-50 hover:text-blue-600 group">
          <i class="ri-settings-3-line text-lg"></i>
          <span class="text-sm font-medium">Réglages</span>
        </button>
      </div>

      <!-- Footer / Logout -->
      <div class="absolute bottom-0 w-full p-4 bg-gray-50 border-t border-gray-100">
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

const router = useRouter();
const uiStore = useUIStore();
const isSidebarOpen = computed(() => uiStore.isSidebarOpen);

const username = localStorage.getItem('username');

const handleLogout = () => {
  localStorage.removeItem('token');
  localStorage.removeItem('username');
  localStorage.removeItem('uid');
  uiStore.closeSidebar();
  router.push('/login');
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
