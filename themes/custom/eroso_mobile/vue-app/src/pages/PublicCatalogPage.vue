<template>
  <div class="public-home min-h-screen bg-[#f5f5f5] font-sans pb-[calc(3.5rem+env(safe-area-inset-bottom))]">
    <!-- Sticky header -->
    <header class="sticky top-0 z-50 bg-[#f5f5f5] pt-[env(safe-area-inset-top)]">
      <!-- Category tabs (Taobao style) -->
      <div class="flex items-center bg-[#f5f5f5]">
        <div
          ref="categoryScroll"
          class="flex-1 flex items-center gap-5 overflow-x-auto scrollbar-hide px-3 py-2.5"
        >
          <button
            type="button"
            @click="selectCategory('')"
            :class="[
              'shrink-0 text-[15px] whitespace-nowrap pb-0.5 transition-colors relative',
              !selectedCategory
                ? 'text-[#ff5000] font-bold'
                : 'text-gray-800 font-medium',
            ]"
          >
            Recommandé
            <span
              v-if="!selectedCategory"
              class="absolute -bottom-0.5 left-1/2 -translate-x-1/2 w-5 h-[3px] bg-[#ff5000] rounded-full"
            ></span>
          </button>
          <button
            v-for="cat in categories"
            :key="cat.tid"
            type="button"
            @click="selectCategory(String(cat.tid))"
            :class="[
              'shrink-0 text-[15px] whitespace-nowrap pb-0.5 transition-colors relative max-w-[7rem] truncate',
              selectedCategory === String(cat.tid)
                ? 'text-[#ff5000] font-bold'
                : 'text-gray-800 font-medium',
            ]"
          >
            {{ cat.title }}
            <span
              v-if="selectedCategory === String(cat.tid)"
              class="absolute -bottom-0.5 left-1/2 -translate-x-1/2 w-5 h-[3px] bg-[#ff5000] rounded-full"
            ></span>
          </button>
        </div>
        <button
          type="button"
          class="shrink-0 px-3 py-2 text-[#ff5000] active:opacity-70"
          aria-label="Eroso boutique"
          @click="goToBoutiqueApp"
        >
          <i class="ri-store-2-line text-xl"></i>
        </button>
      </div>

      <!-- Search bar -->
      <div ref="searchSection" class="px-3 pb-2.5">
        <div class="flex items-center gap-2 bg-white rounded-full border-2 border-[#ff5000] pl-3 pr-1 py-1 shadow-sm">
          <button type="button" class="text-[#ff5000] shrink-0 p-1" aria-label="Type recherche" @click="toggleSearchType">
            <i class="ri-barcode-line text-xl"></i>
          </button>
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="search"
            enterkeyhint="search"
            :placeholder="searchType === 'sku' ? 'Référence SKU…' : 'Rechercher un produit…'"
            class="flex-1 min-w-0 bg-transparent text-sm text-gray-800 placeholder:text-gray-400 outline-none py-1.5"
            @keydown.enter="fetchProducts(false)"
          >
          <button type="button" class="text-gray-500 shrink-0 p-1.5" aria-label="Effacer" @click="clearSearch">
            <i v-if="searchQuery" class="ri-close-circle-fill text-lg"></i>
            <i v-else class="ri-camera-line text-xl"></i>
          </button>
          <button
            type="button"
            class="shrink-0 w-9 h-9 rounded-full bg-[#ff5000] text-white flex items-center justify-center active:scale-95 transition-transform"
            aria-label="Rechercher"
            @click="fetchProducts(false)"
          >
            <i class="ri-search-line text-lg"></i>
          </button>
        </div>
        <p v-if="searchType === 'sku'" class="text-[10px] text-gray-400 mt-1 pl-3">Mode référence SKU</p>
      </div>
    </header>

    <main class="px-2">
      <div v-if="error" class="mx-1 mb-2 p-3 rounded-lg bg-red-50 text-red-600 text-xs">
        {{ error }}
      </div>

      <!-- Loading -->
      <div v-if="loading && products.length === 0" class="flex flex-col items-center justify-center py-16">
        <div class="w-10 h-10 border-[3px] border-[#ff5000] border-t-transparent rounded-full animate-spin"></div>
        <p class="text-xs text-gray-400 mt-3">Chargement…</p>
      </div>

      <!-- Empty -->
      <div v-else-if="!loading && products.length === 0" class="flex flex-col items-center py-16 text-center px-6">
        <i class="ri-shopping-bag-3-line text-5xl text-gray-300"></i>
        <p class="text-sm text-gray-600 mt-3 font-medium">Aucun produit trouvé</p>
        <p class="text-xs text-gray-400 mt-1">Essayez une autre recherche ou catégorie</p>
      </div>

      <!-- 2-column grid -->
      <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 max-w-6xl mx-auto">
        <article
          v-for="product in products"
          :key="product.nid"
          @click="goToProduct(product.nid)"
          class="bg-white rounded-lg overflow-hidden active:opacity-90 transition-opacity cursor-pointer"
        >
          <div class="aspect-square bg-gray-100 relative overflow-hidden">
            <img
              v-if="getProductImageUrl(product)"
              :src="getProductImage(product)"
              :alt="product.title"
              class="w-full h-full object-cover"
              loading="lazy"
            >
            <div v-else class="w-full h-full flex items-center justify-center text-gray-300">
              <i class="ri-image-line text-4xl"></i>
            </div>
            <span class="absolute top-1.5 left-1.5 bg-[#ff5000] text-white text-[9px] font-bold px-1.5 py-0.5 rounded">
              En stock
            </span>
          </div>
          <div class="p-2 pb-2.5">
            <h3 class="text-[13px] text-gray-900 leading-snug line-clamp-2 min-h-[2.25rem]">
              {{ product.title }}
            </h3>
            <div class="mt-1.5 flex items-baseline gap-0.5">
              <span class="text-[11px] text-[#ff5000] font-bold">Ar</span>
              <span class="text-[17px] text-[#ff5000] font-bold leading-none tracking-tight">
                {{ formatPrice(product.field_prix_vente) }}
              </span>
            </div>
            <p v-if="product.field_sku" class="text-[10px] text-gray-400 mt-1 truncate">
              Réf. {{ product.field_sku }}
            </p>
            <p v-else class="text-[10px] text-gray-400 mt-1">Disponible en boutique</p>
          </div>
        </article>
      </div>

      <div ref="loadMoreTrigger" class="flex justify-center py-6 min-h-[60px] items-center">
        <div v-if="loading && products.length > 0" class="w-6 h-6 border-2 border-[#ff5000] border-t-transparent rounded-full animate-spin"></div>
        <p v-else-if="!hasMore && products.length > 0" class="text-[10px] text-gray-400">— Fin —</p>
      </div>
    </main>

    <!-- Bottom tab bar -->
    <nav class="fixed bottom-0 inset-x-0 z-50 bg-white border-t border-gray-200 pb-[env(safe-area-inset-bottom)]">
      <div class="grid grid-cols-4 h-14 max-w-lg mx-auto">
        <button type="button" class="flex flex-col items-center justify-center gap-0.5 text-[#ff5000]">
          <i class="ri-home-5-fill text-[22px]"></i>
          <span class="text-[10px] font-medium">Accueil</span>
        </button>
        <button
          type="button"
          class="flex flex-col items-center justify-center gap-0.5 text-gray-500"
          @click="showCategorySheet = true"
        >
          <i class="ri-apps-2-line text-[22px]"></i>
          <span class="text-[10px]">Catégories</span>
        </button>
        <button
          type="button"
          class="flex flex-col items-center justify-center gap-0.5 text-gray-500"
          @click="focusSearch"
        >
          <i class="ri-search-line text-[22px]"></i>
          <span class="text-[10px]">Rechercher</span>
        </button>
        <button
          type="button"
          class="flex flex-col items-center justify-center gap-0.5 text-gray-500 relative"
          @click="scrollToTop"
        >
          <i class="ri-arrow-up-circle-line text-[22px]"></i>
          <span class="text-[10px]">Haut</span>
          <span
            v-if="total > 0"
            class="absolute top-1 right-[calc(50%-18px)] min-w-[16px] h-4 px-1 bg-[#ff5000] text-white text-[9px] font-bold rounded-full flex items-center justify-center"
          >
            {{ total > 99 ? '99+' : total }}
          </span>
        </button>
      </div>
    </nav>

    <!-- Category sheet -->
    <div
      v-if="showCategorySheet"
      class="fixed inset-0 z-[60] bg-black/40"
      @click.self="showCategorySheet = false"
    >
      <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-2xl max-h-[70vh] overflow-y-auto pb-[env(safe-area-inset-bottom)]">
        <div class="sticky top-0 bg-white border-b px-4 py-3 flex items-center justify-between">
          <h2 class="text-base font-bold text-gray-900">Catégories</h2>
          <button type="button" class="text-gray-400 p-1" @click="showCategorySheet = false">
            <i class="ri-close-line text-2xl"></i>
          </button>
        </div>
        <div class="p-3 grid grid-cols-3 gap-2">
          <button
            type="button"
            @click="selectCategory(''); showCategorySheet = false"
            :class="[
              'py-3 px-2 rounded-lg text-xs font-medium text-center',
              !selectedCategory ? 'bg-[#fff0eb] text-[#ff5000] ring-1 ring-[#ff5000]/30' : 'bg-gray-50 text-gray-700',
            ]"
          >
            Tous
          </button>
          <button
            v-for="cat in categories"
            :key="cat.tid"
            type="button"
            @click="selectCategory(String(cat.tid)); showCategorySheet = false"
            :class="[
              'py-3 px-2 rounded-lg text-xs font-medium text-center line-clamp-2',
              selectedCategory === String(cat.tid)
                ? 'bg-[#fff0eb] text-[#ff5000] ring-1 ring-[#ff5000]/30'
                : 'bg-gray-50 text-gray-700',
            ]"
          >
            {{ cat.title }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { getPublicProducts, getPublicCategories } from '../services/api';
import { proxyImage } from '../services/image';
import { setSelectedAppId, getDashboardPathForApp, SPACE_BOUTIQUE } from '../config/appContext';

const router = useRouter();

const products = ref([]);
const categories = ref([]);
const loading = ref(false);
const error = ref('');
const searchQuery = ref('');
const searchType = ref('title');
const selectedCategory = ref('');
const pager = ref(0);
const hasMore = ref(true);
const total = ref(0);
const loadMoreTrigger = ref(null);
const searchInput = ref(null);
const searchSection = ref(null);
const showCategorySheet = ref(false);

const PAGE_SIZE = 20;
let searchTimeout = null;
let observer = null;

function buildParams(append) {
  const params = new URLSearchParams();
  params.set('offset', String(PAGE_SIZE));
  params.set('pager', append ? String(pager.value) : '0');
  if (searchQuery.value.trim().length >= 2) {
    params.set('search', searchQuery.value.trim());
    params.set('search_type', searchType.value);
  }
  if (selectedCategory.value) {
    params.set('category', selectedCategory.value);
  }
  return params.toString();
}

async function fetchProducts(append = false) {
  if (loading.value) return;
  loading.value = true;
  error.value = '';

  if (!append) {
    pager.value = 0;
    products.value = [];
    hasMore.value = true;
  }

  try {
    const res = await getPublicProducts(buildParams(append));
    const data = res?.data || {};
    const rows = Array.isArray(data.rows) ? data.rows : [];
    total.value = Number(data.total) || 0;

    if (append) {
      products.value = [...products.value, ...rows];
    } else {
      products.value = rows;
    }

    hasMore.value = products.value.length < total.value;
    if (rows.length > 0) {
      pager.value += 1;
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Impossible de charger le catalogue.';
    hasMore.value = false;
  } finally {
    loading.value = false;
    nextTick(setupIntersectionObserver);
  }
}

async function loadCategories() {
  try {
    const res = await getPublicCategories();
    categories.value = res?.data?.rows || [];
  } catch {
    categories.value = [];
  }
}

function selectCategory(tid) {
  selectedCategory.value = tid;
}

function toggleSearchType() {
  searchType.value = searchType.value === 'title' ? 'sku' : 'title';
  if (searchQuery.value.trim().length >= 2) {
    fetchProducts(false);
  }
}

function clearSearch() {
  if (searchQuery.value) {
    searchQuery.value = '';
    fetchProducts(false);
  }
}

function focusSearch() {
  searchSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  nextTick(() => searchInput.value?.focus());
}

function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToProduct(nid) {
  router.push(`/home/${nid}`);
}

function goToBoutiqueApp() {
  setSelectedAppId(SPACE_BOUTIQUE);
  const token = localStorage.getItem('token');
  if (token) {
    router.push(getDashboardPathForApp(SPACE_BOUTIQUE));
  } else {
    router.push('/login');
  }
}

function getProductImageUrl(p) {
  if (p.field_media_image?.image?.url) return p.field_media_image.image.url;
  if (p.field_images?.[0]?.image?.url) return p.field_images[0].image.url;
  return '';
}

function getProductImage(p) {
  const url = getProductImageUrl(p);
  if (!url) return '';
  return proxyImage(url, { w: 400, h: 400, fit: 'cover' });
}

function formatPrice(price) {
  if (!price) return '0';
  return Number(price).toLocaleString('fr-MG');
}

function setupIntersectionObserver() {
  if (observer) observer.disconnect();
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0]?.isIntersecting && !loading.value && hasMore.value) {
        fetchProducts(true);
      }
    },
    { root: null, rootMargin: '300px', threshold: 0 }
  );
  if (loadMoreTrigger.value) {
    observer.observe(loadMoreTrigger.value);
  }
}

watch(selectedCategory, () => fetchProducts(false));

watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => fetchProducts(false), 450);
});

onMounted(() => {
  loadCategories();
  fetchProducts(false);
});

onUnmounted(() => {
  if (observer) observer.disconnect();
  if (searchTimeout) clearTimeout(searchTimeout);
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

.public-home {
  -webkit-tap-highlight-color: transparent;
}
</style>
