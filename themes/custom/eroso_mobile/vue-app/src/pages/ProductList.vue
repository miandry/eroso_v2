<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <!-- Navigation Haute -->
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button @click="uiStore.toggleSidebar" class="p-1 -ml-1 text-gray-600 cursor-pointer">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <h1 class="text-lg font-semibold text-gray-900">Catalogue</h1>
        </div>
        <div class="flex items-center space-x-2">
          <button @click="productStore.fetchProducts()" class="w-8 h-8 flex items-center justify-center text-blue-600 bg-blue-50 rounded-lg cursor-pointer">
            <i class="ri-refresh-line"></i>
          </button>
        </div>
      </div>
    </nav>

    <main class="pt-16 pb-24 px-4">
      <!-- Search & Filter -->
      <div class="mb-6 space-y-4">
        <div class="relative">
          <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Rechercher un produit..." 
            class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
        </div>
        
        <div class="flex items-center space-x-2 overflow-x-auto pb-2 scrollbar-hide">
          <button 
            @click="selectedCategory = ''"
            :class="['px-4 py-2 rounded-full text-xs font-medium whitespace-nowrap transition-colors', 
                    selectedCategory === '' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-100']"
          >
            Tous
          </button>
          <button 
            v-for="cat in categories" 
            :key="cat.tid"
            @click="selectedCategory = cat.name"
            :class="['px-4 py-2 rounded-full text-xs font-medium whitespace-nowrap transition-colors', 
                    selectedCategory === cat.name ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-100']"
          >
            {{ cat.name }}
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20 space-y-4">
        <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-gray-500 font-medium">Chargement des produits...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredProducts.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
          <i class="ri-box-3-line text-4xl text-gray-300"></i>
        </div>
        <h3 class="text-base font-semibold text-gray-900">Aucun produit trouvé</h3>
        <p class="text-sm text-gray-500 mt-1">Essayez d'ajuster votre recherche ou vos filtres.</p>
      </div>

      <div v-else class="grid grid-cols-1 gap-4">
        <div v-for="product in filteredProducts" :key="product.nid" class="bg-white rounded-2xl shadow-sm p-4 border border-gray-100 transition-all hover:shadow-md active:scale-[0.98]">
          <div class="flex items-start space-x-4">
            <div class="relative">
              <img 
                :src="getProductImage(product)" 
                :alt="product.title" 
                class="w-24 h-24 rounded-xl object-cover bg-gray-50"
              >
              <div v-if="getCategoryName(product)" class="absolute -top-2 -right-2 bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-1 rounded-lg border border-white shadow-sm">
                {{ getCategoryName(product) }}
              </div>
            </div>
            
            <div class="flex-1 min-w-0">
              <h3 class="text-base font-bold text-gray-900 truncate">{{ product.title }}</h3>
              <p class="text-xs text-gray-500 mt-1 uppercase tracking-wider font-semibold">Réf: {{ product.field_sku || 'N/A' }}</p>
              <div class="mt-1 flex items-center space-x-1">
                <span class="text-[10px] inline-flex items-center px-1.5 py-0.5 rounded-md font-bold uppercase tracking-tight" :class="parseInt(product.field_quantite_disponible || 0) > 5 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                  Stock dispo: {{ product.field_quantite_disponible || 0 }}
                </span>
              </div>
              
              <div class="mt-4 flex items-center justify-between">
                <div class="text-lg font-black text-blue-600">
                  {{ formatPrice(product.field_prix_vente || product.field_price) }} <span class="text-xs">Ar</span>
                </div>
                <div class="flex items-center space-x-1 text-xs text-gray-400">
                  <i class="ri-time-line"></i>
                  <span>{{ formatDate(product.changed) }}</span>
                </div>
              </div>
            </div>
          </div>
          
          <div v-if="product.field_description" class="mt-4 pt-3 border-t border-gray-50">
            <div class="text-xs text-gray-600 line-clamp-2 leading-relaxed italic" v-html="product.field_description"></div>
          </div>
        </div>
      </div>
    </main>

    <!-- Bottom Navigation (Shared Pattern) -->
    <nav class="fixed bottom-0 w-full bg-white border-t border-gray-200 z-50">
      <div class="grid grid-cols-4 h-16">
        <router-link to="/stock-manager" class="flex flex-col items-center justify-center space-y-1 text-gray-400">
          <i class="ri-home-line ri-lg"></i>
          <span class="text-xs">Accueil</span>
        </router-link>
        <router-link to="/products" class="flex flex-col items-center justify-center space-y-1 text-blue-600">
          <i class="ri-box-3-fill ri-lg"></i>
          <span class="text-xs font-semibold">Produits</span>
        </router-link>
        <button class="flex flex-col items-center justify-center space-y-1 text-gray-400 cursor-pointer">
          <i class="ri-bar-chart-line ri-lg"></i>
          <span class="text-xs">Stats</span>
        </button>
        <button class="flex flex-col items-center justify-center space-y-1 text-gray-400 cursor-pointer">
          <i class="ri-settings-3-line ri-lg"></i>
          <span class="text-xs">Paramètres</span>
        </button>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useProductStore } from '../stores/useProductStore';
import { useUIStore } from '../stores/useUIStore';

const productStore = useProductStore();
const uiStore = useUIStore();
const { products, categories, loading } = storeToRefs(productStore);

const searchQuery = ref('');
const selectedCategory = ref('');

const filteredProducts = computed(() => {
  return products.value.filter(p => {
    const nameMatch = (p.title || '').toLowerCase().includes(searchQuery.value.toLowerCase());
    const refMatch = (p.field_sku || '').toLowerCase().includes(searchQuery.value.toLowerCase());
    const categoryName = getCategoryName(p);
    const categoryMatch = !selectedCategory.value || (categoryName === selectedCategory.value);
    return (nameMatch || refMatch) && categoryMatch;
  });
});

const getCategoryName = (p) => {
  if (p.field_category && p.field_category.title) return p.field_category.title;
  if (Array.isArray(p.field_category) && p.field_category.length > 0) return p.field_category[0].title;
  return null;
};

const getProductImage = (p) => {
  if (p.field_media_image && p.field_media_image.image && p.field_media_image.image.url) {
    return p.field_media_image.image.url;
  }
  if (p.field_images && p.field_images[0] && p.field_images[0].image && p.field_images[0].image.url) {
    return p.field_images[0].image.url;
  }
  return 'https://readdy.ai/api/search-image?query=icon%2C%20generic%20product&width=120&height=120';
};

const formatPrice = (price) => {
  if (!price) return '0';
  return Number(price).toLocaleString('fr-MG');
};

const formatDate = (timestamp) => {
  if (!timestamp) return 'Récemment';
  // Check if it's already a date string or timestamp in seconds
  const date = isNaN(timestamp) ? new Date(timestamp) : new Date(timestamp * 1000);
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
};

onMounted(() => {
  productStore.fetchProducts();
  productStore.fetchCategories();
});
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
