<template>
  <nav :class="navClass">
    <div class="grid grid-cols-4 h-16">
      <router-link to="/caisse-locale" class="flex flex-col items-center justify-center space-y-1" :class="isActive('/caisse-locale') ? 'text-blue-600' : 'text-gray-400'">
        <i class="ri-store-2-line text-xl"></i>
        <span class="text-xs" :class="isActive('/caisse-locale') ? 'font-semibold' : ''">Caisse</span>
      </router-link>
      <router-link to="/products" class="flex flex-col items-center justify-center space-y-1" :class="isActiveProducts() ? 'text-blue-600' : 'text-gray-400'">
        <i class="ri-box-3-line text-xl"></i>
        <span class="text-xs" :class="isActiveProducts() ? 'font-semibold' : ''">Produits</span>
      </router-link>
      <router-link to="/commandes" class="flex flex-col items-center justify-center space-y-1" :class="isActive('/commandes') ? 'text-blue-600' : 'text-gray-400'">
        <i class="ri-shopping-bag-line text-xl"></i>
        <span class="text-xs" :class="isActive('/commandes') ? 'font-semibold' : ''">Ventes</span>
      </router-link>
      <router-link to="/statistics" class="flex flex-col items-center justify-center space-y-1" :class="isActive('/statistics') ? 'text-blue-600' : 'text-gray-400'">
        <i class="ri-bar-chart-line text-xl"></i>
        <span class="text-xs" :class="isActive('/statistics') ? 'font-semibold' : ''">Statistique</span>
      </router-link>
    </div>
  </nav>
</template>

<script setup>
import { useRoute } from 'vue-router';

const route = useRoute();

const props = defineProps({
  // When true, show bottom nav on lg+ (desktop/tablet landscape).
  // Default is mobile-only to match the previous behavior.
  showOnDesktop: {
    type: Boolean,
    default: false,
  },
});

const navClass = [
  'fixed bottom-0 w-full bg-white border-t border-gray-200 z-50',
  props.showOnDesktop ? '' : 'lg:hidden',
];

const isActive = (path) => route.path === path;
const isActiveProducts = () => route.path === '/products' || route.path.startsWith('/product');
</script>
