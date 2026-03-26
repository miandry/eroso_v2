<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <!-- Navigation Haute -->
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button @click="uiStore.toggleSidebar" class="p-1 -ml-1 text-gray-600 cursor-pointer lg:hidden">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <div>
            <p class="text-xs text-gray-500">Tous Produits</p>
          </div>
        </div>
        <div class="flex items-center space-x-2">
       

          <button @click="productStore.fetchProducts(false)" class="w-8 h-8 flex items-center justify-center text-blue-600 bg-blue-50 rounded-lg cursor-pointer">
            <i class="ri-refresh-line"></i>
          </button>
         
        </div>
      </div>
    </nav>

    <main class="pt-16 pb-24 px-4 lg:ml-64">
      <!-- Search & Filter -->
      <div class="mb-6 space-y-4">
        <div class="flex space-x-2">
          <select 
            v-model="searchType" 
            class="px-3 py-3 bg-white border border-gray-200 rounded-xl shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="title">Titre</option>
            <option value="sku">Référence (SKU)</option>
          </select>
          <div class="relative flex-1">
            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input 
              v-model="searchQuery" 
              type="text" 
              :placeholder="searchType === 'sku' ? 'Rechercher par référence...' : 'Rechercher un produit...'" 
              class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
          </div>
        </div>
        
        <!-- Category Filter -->
 
      </div>

      <!-- Initial Loading State -->
      <div v-show="loading && products.length === 0" class="flex flex-col items-center justify-center py-20 space-y-4">
        <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-gray-500 font-medium">Chargement des produits...</p>
      </div>

      <!-- Empty State -->
      <div v-show="!loading && filteredProducts.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
          <i class="ri-box-3-line text-4xl text-gray-300"></i>
        </div>
        <h3 class="text-base font-semibold text-gray-900">Aucun produit trouvé</h3>
        <p class="text-sm text-gray-500 mt-1">Essayez d'ajuster votre recherche ou vos filtres.</p>
      </div>

      <!-- Product List -->
      <div v-show="filteredProducts.length > 0" class="grid grid-cols-1 gap-4">
        <div v-for="product in filteredProducts" :key="product.nid" @click="router.push(`/product/${product.nid}`)" class="bg-white rounded-2xl shadow-sm p-4 border border-gray-100 transition-all hover:shadow-md active:scale-[0.98] cursor-pointer">
          <div class="flex items-start space-x-4">
            <div class="relative">
              <img 
                :src="getProductImage(product)" 
                :alt="product.title" 
                class="w-24 h-24 rounded-xl object-cover bg-gray-50"
                loading="lazy"
              >
              <div v-if="getCategoryName(product)" class="absolute -top-2 -right-2 bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-1 rounded-lg border border-white shadow-sm">
                {{ getCategoryName(product) }}
              </div>
            </div>
            
            <div class="flex-1 min-w-0">
              <h3 class="text-base font-bold text-gray-900 truncate">{{ product.title }}</h3>
              <p class="text-xs text-gray-500 mt-1 uppercase tracking-wider font-semibold">Réf: {{ product.field_sku || 'N/A' }}</p>
              <div class="mt-1 flex items-center space-x-2">
                <span 
                  :class="[
                    'text-[10px] inline-flex items-center px-2 py-0.5 rounded-md font-bold uppercase tracking-tight',
                    getStatusClass(product.field_status)
                  ]"
                >
                  {{ getStatusLabel(product.field_status) }}
                </span>
                <span class="text-[10px] inline-flex items-center px-1.5 py-0.5 rounded-md font-bold uppercase tracking-tight" :class="parseInt(product.field_quantite_disponible || 0) > 5 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                  Stock: {{ product.field_quantite_disponible || 0 }}
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

      <!-- Bottom Loader & End State -->
      <div ref="loadMoreTrigger" class="flex justify-center py-8 min-h-[100px] items-center">
        <div v-show="loading && products.length > 0" class="w-8 h-8 border-3 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
        <div v-if="!hasMore && products.length > 0" class="text-center">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Fin du catalogue</p>
        </div>
      </div>
    </main>

    <!-- Floating Action Button -->
    <router-link to="/product-insert" class="fixed right-6 bottom-24 w-14 h-14 bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center z-40 active:scale-90 transition-transform lg:hidden">
      <i class="ri-add-line ri-2x"></i>
    </router-link>

    <BottomNav />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useProductStore } from '../stores/useProductStore';
import { useUIStore } from '../stores/useUIStore';
import { proxyImage } from '../services/image';
import BottomNav from '../components/BottomNav.vue';

const router = useRouter();
const productStore = useProductStore();
const uiStore = useUIStore();
const { products, categories, loading, hasMore } = storeToRefs(productStore);

const searchQuery = ref('');
const searchType = ref('title');
const selectedCategory = ref('');
const selectedStatus = ref('');
const loadMoreTrigger = ref(null);
let searchTimeout = null;
let observer = null;

// Filter products by status and category, sort by nid DESC
const filteredProducts = computed(() => {
  let filtered = [...products.value];

  // Filter by status if selected
  if (selectedStatus.value) {
    filtered = filtered.filter(product => {
      const status = product.field_status;
      return status && status.toLowerCase() === selectedStatus.value.toLowerCase();
    });
  }

  // Sort by nid descending
  filtered.sort((a, b) => parseInt(b.nid) - parseInt(a.nid));

  return filtered;
});

const getProductCountByStatus = (status) => {
  return products.value.filter(product => {
    const productStatus = product.field_status;
    return productStatus && productStatus.toLowerCase() === status.toLowerCase();
  }).length;
};

const getStatusClass = (status) => {
  if (!status) return 'bg-gray-100 text-gray-700';
  
  const statusLower = status.toLowerCase();
  const classes = {
    'dispo': 'bg-green-100 text-green-700',
    'disponible': 'bg-green-100 text-green-700',
    'rupture': 'bg-orange-100 text-orange-700',
    'indispo': 'bg-red-100 text-red-700',
    'indisponible': 'bg-red-100 text-red-700'
  };
  
  return classes[statusLower] || 'bg-gray-100 text-gray-700';
};

const getStatusLabel = (status) => {
  if (!status) return 'N/A';
  return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
};

const getCategoryName = (p) => {
  if (p.field_category && p.field_category.title) return p.field_category.title;
  if (Array.isArray(p.field_category) && p.field_category.length > 0) return p.field_category[0].title;
  return null;
};

const getProductImage = (p) => {
  let url = '';
  if (p.field_media_image && p.field_media_image.image && p.field_media_image.image.url) {
    url = p.field_media_image.image.url;
  } else if (p.field_images && p.field_images[0] && p.field_images[0].image && p.field_images[0].image.url) {
    url = p.field_images[0].image.url;
  } else {
    url = 'https://readdy.ai/api/search-image?query=icon%2C%20generic%20product';
  }
  
  return proxyImage(url, { w: 120, h: 120, fit: 'cover' });
};

const formatPrice = (price) => {
  if (!price) return '0';
  return Number(price).toLocaleString('fr-MG');
};

const formatDate = (timestamp) => {
  if (!timestamp) return 'Récemment';
  const date = isNaN(timestamp) ? new Date(timestamp) : new Date(timestamp * 1000);
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
};

const performFetch = (append = false) => {
    productStore.fetchProducts(append, {
        search: searchQuery.value,
        searchType: searchType.value,
        category: selectedCategory.value
    });
};

const setupIntersectionObserver = () => {
    if (observer) observer.disconnect();
    
    observer = new IntersectionObserver(
        (entries) => {
            const entry = entries[0];
            if (entry.isIntersecting && !loading.value && hasMore.value) {
                performFetch(true);
            }
        },
        {
            root: null,
            rootMargin: '300px',
            threshold: 0
        }
    );
    
    nextTick(() => {
        if (loadMoreTrigger.value) {
            observer.observe(loadMoreTrigger.value);
        }
    });
};

onMounted(() => {
  performFetch(false);
  productStore.fetchCategories();
  setupIntersectionObserver();
});

onUnmounted(() => {
    if (observer) observer.disconnect();
    if (searchTimeout) clearTimeout(searchTimeout);
});

watch(selectedCategory, () => {
    performFetch(false);
});

watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        performFetch(false);
    }, 500);
});

watch(searchType, () => {
    if (searchQuery.value) {
        performFetch(false);
    }
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
