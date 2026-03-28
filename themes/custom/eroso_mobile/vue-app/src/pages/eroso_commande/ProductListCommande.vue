<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button type="button" @click="uiStore.toggleSidebar" class="p-1 -ml-1 text-gray-600 cursor-pointer lg:hidden">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <h1 class="text-lg font-semibold text-gray-900">Catalogue sur commande</h1>
        </div>
        <div class="flex items-center space-x-2">
          <button type="button" @click="performFetch(false)" class="w-8 h-8 flex items-center justify-center text-indigo-600 bg-indigo-50 rounded-lg cursor-pointer">
            <i class="ri-refresh-line"></i>
          </button>
          <router-link
            to="/sur-commande/product-insert"
            class="w-8 h-8 flex items-center justify-center text-white bg-indigo-600 rounded-lg shadow-sm cursor-pointer"
            aria-label="Nouveau produit sur commande"
          >
            <i class="ri-add-line"></i>
          </router-link>
        </div>
      </div>
    </nav>

    <main class="pt-16 pb-24 px-4 lg:ml-64">
      <div class="mb-6 space-y-4">
        <div class="flex space-x-2">
          <select
            v-model="searchType"
            class="px-3 py-3 bg-white border border-gray-200 rounded-xl shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
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
              class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            >
          </div>
        </div>

        <div class="flex items-center space-x-2 overflow-x-auto pb-2 scrollbar-hide">
          <button
            type="button"
            @click="selectedCategory = ''"
            :class="['px-4 py-2 rounded-full text-xs font-medium whitespace-nowrap transition-colors',
              selectedCategory === '' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-100']"
          >
            Tous
          </button>
          <button
            v-for="cat in categories"
            :key="cat.tid"
            type="button"
            @click="selectedCategory = cat.tid"
            :class="['px-4 py-2 rounded-full text-xs font-medium whitespace-nowrap transition-colors',
              selectedCategory === cat.tid ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-100']"
          >
            {{ cat.name }}
          </button>
        </div>
      </div>

      <div v-show="loading && products.length === 0" class="flex flex-col items-center justify-center py-20 space-y-4">
        <div class="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-gray-500 font-medium">Chargement des produits…</p>
      </div>

      <div v-show="!loading && availableProducts.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
          <i class="ri-shopping-bag-3-line text-4xl text-gray-300"></i>
        </div>
        <h3 class="text-base font-semibold text-gray-900">Aucun produit</h3>
        <p class="text-sm text-gray-500 mt-1">Aucun produit sur commande pour l’instant.</p>
      </div>

      <div v-show="availableProducts.length > 0" class="grid grid-cols-1 gap-4">
        <div
          v-for="product in availableProducts"
          :key="product.nid"
          class="bg-white rounded-2xl shadow-sm p-4 border border-gray-100 transition-all hover:shadow-md active:scale-[0.98] cursor-pointer"
          @click="router.push(`/sur-commande/product/${product.nid}`)"
        >
          <div class="flex items-start space-x-4">
            <div class="relative">
              <img
                :src="getProductImage(product)"
                :alt="product.title"
                class="w-24 h-24 rounded-xl object-cover bg-gray-50"
                loading="lazy"
              >
              <div v-if="getCategoryName(product)" class="absolute -top-2 -right-2 bg-indigo-100 text-indigo-700 text-[10px] font-bold px-2 py-1 rounded-lg border border-white shadow-sm">
                {{ getCategoryName(product) }}
              </div>
            </div>

            <div class="flex-1 min-w-0">
              <h3 class="text-base font-bold text-gray-900 truncate">{{ product.title }}</h3>
              <p class="text-xs text-gray-500 mt-1 uppercase tracking-wider font-semibold">Réf: {{ product.field_sku || 'N/A' }}</p>
              <div v-if="getLinkFieldUri(product.field_taobao_url)" class="mt-2">
                <a
                  :href="getLinkFieldUri(product.field_taobao_url)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center text-xs font-semibold text-indigo-600"
                  @click.stop
                >
                  <i class="ri-external-link-line mr-1"></i>
                  Lien externe
                </a>
              </div>

              <div class="mt-4 flex items-center justify-between">
                <div class="text-lg font-black text-indigo-600">
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

      <div ref="loadMoreTrigger" class="flex justify-center py-8 min-h-[100px] items-center">
        <div v-show="loading && products.length > 0" class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        <div v-if="!hasMore && products.length > 0" class="text-center">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Fin du catalogue</p>
        </div>
      </div>
    </main>

    <router-link
      to="/sur-commande/product-insert"
      class="fixed right-6 bottom-6 w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg flex items-center justify-center z-40 active:scale-90 transition-transform lg:bottom-8"
      aria-label="Ajouter un produit sur commande"
    >
      <i class="ri-add-line ri-2x"></i>
    </router-link>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useProductStore } from '../../stores/useProductStore';
import { useUIStore } from '../../stores/useUIStore';
import { proxyImage } from '../../services/image';
import { getLinkFieldUri } from '../../utils/drupalLink';

const BUNDLE = 'product_commande';

const router = useRouter();
const productStore = useProductStore();
const uiStore = useUIStore();
const { products, categories, loading, hasMore } = storeToRefs(productStore);

const searchQuery = ref('');
const searchType = ref('title');
const selectedCategory = ref('');
const loadMoreTrigger = ref(null);
let searchTimeout = null;
let observer = null;

const availableProducts = computed(() => {
  return [...products.value].sort((a, b) => parseInt(b.nid) - parseInt(a.nid));
});

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
    bundle: BUNDLE,
    search: searchQuery.value,
    searchType: searchType.value,
    category: selectedCategory.value,
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
      threshold: 0,
    },
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

watch(searchType, () => {
  performFetch(false);
});

watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    performFetch(false);
  }, 400);
});

watch(products, () => {
  if (!observer && loadMoreTrigger.value) {
    setupIntersectionObserver();
  }
}, { deep: false });
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
