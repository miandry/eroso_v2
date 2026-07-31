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
              :disabled="imageSearchActive"
            >
          </div>
          <label
            class="shrink-0 w-12 h-12 flex items-center justify-center bg-violet-50 text-violet-700 border border-violet-100 rounded-xl cursor-pointer hover:bg-violet-100 transition-colors"
            title="Rechercher par photo (Claude AI)"
          >
            <i class="ri-image-search-line text-xl"></i>
            <input
              ref="imageInputRef"
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              class="hidden"
              @change="onImageSelected"
            >
          </label>
        </div>

        <div
          v-if="imagePreview || imageSearchActive || imageSearchError"
          class="bg-white border border-violet-100 rounded-2xl p-3 shadow-sm space-y-3"
        >
          <div v-if="imagePreview" class="flex items-start gap-3">
            <img :src="imagePreview" alt="Aperçu recherche" class="w-16 h-16 rounded-xl object-cover border border-gray-100">
            <div class="flex-1 min-w-0">
              <p class="text-xs font-bold text-violet-800">Recherche par image (IA → field_search_image)</p>
              <p v-if="imageSearchMeta" class="text-[10px] text-violet-600 mt-0.5">
                {{ imageSearchMeta.total }} résultat(s) · {{ imageSearchMeta.scanned }} produit(s) analysé(s)
              </p>
              <p v-if="generatedSearchText" class="text-[10px] text-gray-500 mt-1 line-clamp-3 whitespace-pre-line">
                {{ generatedSearchText }}
              </p>
              <p v-else-if="imageAnalysis?.description_short" class="text-[11px] text-gray-600 mt-1 line-clamp-2">
                {{ imageAnalysis.description_short }}
              </p>
              <div v-if="imageAnalysis?.keywords?.length" class="flex flex-wrap gap-1 mt-2">
                <span
                  v-for="kw in imageAnalysis.keywords.slice(0, 8)"
                  :key="kw"
                  class="px-2 py-0.5 rounded-full bg-violet-50 text-violet-800 text-[10px] font-semibold"
                >
                  {{ kw }}
                </span>
              </div>
            </div>
            <button
              type="button"
              class="text-gray-400 hover:text-gray-600 p-1"
              @click="clearImageSearch"
            >
              <i class="ri-close-line text-lg"></i>
            </button>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              v-if="imageFile && !imageSearchActive"
              type="button"
              class="flex-1 min-w-[10rem] px-3 py-2 rounded-xl text-xs font-bold bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50"
              :disabled="imageSearching"
              @click="runImageSearch"
            >
              <i v-if="imageSearching" class="ri-loader-4-line animate-spin mr-1"></i>
              {{ imageSearching ? 'Recherche dans le catalogue…' : 'Rechercher dans field_search_image' }}
            </button>
            <button
              v-if="imageSearchActive"
              type="button"
              class="px-3 py-2 rounded-xl text-xs font-bold bg-gray-100 text-gray-700 hover:bg-gray-200"
              @click="clearImageSearch"
            >
              Revenir au catalogue
            </button>
          </div>
          <div v-if="imageSearchError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex gap-2 items-start">
            <i class="ri-error-warning-line shrink-0 text-lg"></i>
            <span>{{ imageSearchError }}</span>
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

      <div v-show="(loading || imageSearching) && displayProducts.length === 0" class="flex flex-col items-center justify-center py-20 space-y-4">
        <div class="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-gray-500 font-medium">{{ imageSearching ? 'Analyse IA et recherche textuelle…' : 'Chargement des produits…' }}</p>
      </div>

      <div v-show="!loading && !imageSearching && displayProducts.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
          <i class="ri-shopping-bag-3-line text-4xl text-gray-300"></i>
        </div>
        <h3 class="text-base font-semibold text-gray-900">
          {{ imageSearchActive ? 'Aucun produit correspondant' : 'Aucun produit' }}
        </h3>
        <p class="text-sm text-gray-500 mt-1">
          {{ imageSearchActive ? 'Essayez une autre photo ou effacez la recherche IA.' : 'Aucun produit sur commande pour l’instant.' }}
        </p>
      </div>

      <div v-show="displayProducts.length > 0" class="grid grid-cols-1 gap-4">
        <div
          v-for="product in displayProducts"
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
              <div
                v-if="imageSearchActive && product._ai_score != null"
                class="absolute -bottom-2 -left-2 bg-violet-600 text-white text-[10px] font-black px-2 py-1 rounded-lg border border-white shadow-sm"
              >
                {{ product._ai_score }}%
              </div>
            </div>

            <div class="flex-1 min-w-0">
              <h3 class="text-base font-bold text-gray-900 truncate">{{ product.title }}</h3>
              <p class="text-xs text-gray-500 mt-1 uppercase tracking-wider font-semibold">Réf: {{ product.field_sku || 'N/A' }}</p>
              <p
                v-if="imageSearchActive && product._ai_match_reason"
                class="mt-2 text-[11px] text-violet-800 bg-violet-50 rounded-lg px-2 py-1.5 leading-snug"
              >
                {{ product._ai_match_reason }}
              </p>
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
        <div v-show="(loading || imageSearching) && displayProducts.length > 0" class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        <div v-if="!imageSearchActive && !hasMore && products.length > 0" class="text-center">
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
import { searchProductsByImage, getApiErrorMessage } from '../../services/api';

const BUNDLE = 'product_commande';

const router = useRouter();
const productStore = useProductStore();
const uiStore = useUIStore();
const { products, categories, loading, hasMore } = storeToRefs(productStore);

const searchQuery = ref('');
const searchType = ref('title');
const selectedCategory = ref('');
const loadMoreTrigger = ref(null);
const imageInputRef = ref(null);
const imageFile = ref(null);
const imagePreview = ref('');
const imageSearchResults = ref([]);
const imageSearchActive = ref(false);
const imageSearching = ref(false);
const imageSearchError = ref('');
const imageAnalysis = ref(null);
const generatedSearchText = ref('');
const imageSearchMeta = ref(null);
let searchTimeout = null;
let observer = null;

const availableProducts = computed(() => {
  return [...products.value].sort((a, b) => parseInt(b.nid) - parseInt(a.nid));
});

const displayProducts = computed(() => {
  if (imageSearchActive.value) {
    return [...imageSearchResults.value].sort((a, b) => {
      const sa = Number(a._ai_score || 0);
      const sb = Number(b._ai_score || 0);
      if (sb !== sa) return sb - sa;
      return parseInt(b.nid) - parseInt(a.nid);
    });
  }
  return availableProducts.value;
});

function onImageSelected(event) {
  const file = event.target.files?.[0];
  if (!file) return;
  if (!file.type.startsWith('image/')) {
    imageSearchError.value = 'Veuillez choisir une image (JPG, PNG, WebP).';
    return;
  }
  if (file.size > 8 * 1024 * 1024) {
    imageSearchError.value = 'Image trop volumineuse (max 8 Mo).';
    return;
  }
  imageFile.value = file;
  imageSearchError.value = '';
  imageAnalysis.value = null;
  generatedSearchText.value = '';
  imageSearchActive.value = false;
  imageSearchResults.value = [];
  const reader = new FileReader();
  reader.onload = (e) => {
    imagePreview.value = e.target?.result || '';
  };
  reader.readAsDataURL(file);
  runImageSearch();
}

async function runImageSearch() {
  if (!imageFile.value || imageSearching.value) return;
  imageSearching.value = true;
  imageSearchError.value = '';
  try {
    const res = await searchProductsByImage(imageFile.value, BUNDLE);
    const data = res?.data;
    if (!data?.status) {
      throw new Error(data?.message || 'Recherche IA échouée.');
    }
    imageAnalysis.value = data.analysis || null;
    generatedSearchText.value = data.field_search_image || '';
    imageSearchResults.value = data.rows || [];
    imageSearchMeta.value = {
      total: data.total ?? (data.rows?.length || 0),
      scanned: data.scanned ?? 0,
    };
    imageSearchActive.value = true;
    if (!imageSearchResults.value.length && data.message) {
      imageSearchError.value = data.message;
    }
  } catch (e) {
    imageSearchError.value = getApiErrorMessage(e, 'Erreur lors de la recherche par image.');
    imageSearchActive.value = false;
    imageSearchResults.value = [];
  } finally {
    imageSearching.value = false;
  }
}

function clearImageSearch() {
  imageFile.value = null;
  imagePreview.value = '';
  imageSearchResults.value = [];
  imageSearchActive.value = false;
  imageSearchError.value = '';
  imageAnalysis.value = null;
  generatedSearchText.value = '';
  imageSearchMeta.value = null;
  if (imageInputRef.value) {
    imageInputRef.value.value = '';
  }
}

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
      if (entry.isIntersecting && !loading.value && hasMore.value && !imageSearchActive.value) {
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
  if (imageSearchActive.value) return;
  performFetch(false);
});

watch(searchType, () => {
  if (imageSearchActive.value) return;
  performFetch(false);
});

watch(searchQuery, () => {
  if (imageSearchActive.value) return;
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
