<template>
  <div class="public-home min-h-screen bg-[#fdf2f9] font-sans pb-[calc(3.5rem+env(safe-area-inset-bottom))]">
    <transition name="slide-down">
      <div
        v-if="toastMessage"
        class="fixed top-4 left-1/2 -translate-x-1/2 z-[80] w-[calc(100%-2rem)] max-w-sm rounded-xl border border-[#e8d4f0] bg-white p-3 shadow-xl"
      >
        <div class="flex items-center gap-2 text-sm text-[#4b2c82]">
          <i class="ri-check-line text-lg text-emerald-600"></i>
          <span>{{ toastMessage }}</span>
        </div>
      </div>
    </transition>

    <!-- Sticky header -->
    <header class="sticky top-0 z-50 bg-[#fdf2f9]/95 backdrop-blur-md pt-[env(safe-area-inset-top)] border-b border-[#e8d4f0]/60">
      <!-- Brand bar -->
      <div class="flex items-center justify-between gap-3 px-3 pt-2 pb-1">
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#9b59b6] to-[#4b2c82] flex items-center justify-center text-white shadow-md shadow-[#4b2c82]/25 shrink-0">
            <i class="ri-shopping-bag-3-fill text-xl"></i>
          </div>
          <div class="min-w-0">
            <p class="eroso-brand text-xl font-extrabold tracking-tight leading-none">e-roso</p>
            <p class="text-[11px] font-semibold text-[#8e44ad] mt-0.5 flex items-center gap-1">
              Import Chine
              <span class="inline-flex w-4 h-2.5 rounded-[2px] overflow-hidden shrink-0 shadow-sm" aria-hidden="true">
                <span class="flex-1 bg-[#da251d]"></span>
                <span class="w-[6px] bg-[#da251d] flex items-center justify-center">
                  <span class="w-1 h-1 bg-[#ffde00] rounded-full"></span>
                </span>
              </span>
            </p>
          </div>
        </div>
        <button
          v-if="showBoutiqueButton"
          type="button"
          class="shrink-0 w-9 h-9 rounded-full bg-white/80 text-[#5e35b1] border border-[#e8d4f0] flex items-center justify-center active:scale-95 transition-transform shadow-sm"
          aria-label="Espace boutique"
          @click="goToBoutiqueApp"
        >
          <i class="ri-store-2-line text-lg"></i>
        </button>
      </div>

      <!-- Category tabs -->
      <div class="flex items-center">
        <div
          ref="categoryScroll"
          class="flex-1 flex items-center gap-5 overflow-x-auto scrollbar-hide px-3 py-2"
        >
          <button
            type="button"
            @click="selectCategory('')"
            :class="[
              'shrink-0 text-[15px] whitespace-nowrap pb-0.5 transition-colors relative',
              !selectedCategory
                ? 'text-[#5e35b1] font-bold'
                : 'text-[#5a4a6a] font-medium',
            ]"
          >
            Recommandé
            <span
              v-if="!selectedCategory"
              class="absolute -bottom-0.5 left-1/2 -translate-x-1/2 w-5 h-[3px] bg-gradient-to-r from-[#9b59b6] to-[#4b2c82] rounded-full"
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
                ? 'text-[#5e35b1] font-bold'
                : 'text-[#5a4a6a] font-medium',
            ]"
          >
            {{ cat.title }}
            <span
              v-if="selectedCategory === String(cat.tid)"
              class="absolute -bottom-0.5 left-1/2 -translate-x-1/2 w-5 h-[3px] bg-gradient-to-r from-[#9b59b6] to-[#4b2c82] rounded-full"
            ></span>
          </button>
        </div>
      </div>

      <!-- Search bar -->
      <div ref="searchSection" class="px-3 pb-2.5">
        <div class="flex items-center gap-2 bg-white rounded-full border-2 border-[#9b59b6]/40 pl-3 pr-1 py-1 shadow-sm shadow-[#4b2c82]/5">
          <button type="button" class="text-[#8e44ad] shrink-0 p-1" aria-label="Type recherche" @click="toggleSearchType">
            <i class="ri-barcode-line text-xl"></i>
          </button>
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="search"
            enterkeyhint="search"
            :placeholder="searchType === 'sku' ? 'Référence SKU…' : 'Rechercher un produit…'"
            class="flex-1 min-w-0 bg-transparent text-sm text-[#3d2a52] placeholder:text-[#9b8aab] outline-none py-1.5"
            @keydown.enter="fetchProducts(false)"
          >
          <button
            v-if="searchQuery"
            type="button"
            class="text-[#8e44ad]/70 shrink-0 p-1.5"
            aria-label="Effacer la recherche"
            @click="clearSearch"
          >
            <i class="ri-close-circle-fill text-lg"></i>
          </button>
          <button
            v-else
            type="button"
            class="text-[#8e44ad] shrink-0 p-1.5"
            aria-label="Rechercher par image"
            @click="openImagePicker"
          >
            <i class="ri-camera-line text-xl"></i>
          </button>
          <input
            ref="imageInput"
            type="file"
            accept="image/jpeg,image/png,image/webp,image/gif"
            class="hidden"
            @change="onImageSelected"
          >
          <button
            type="button"
            class="shrink-0 w-9 h-9 rounded-full bg-gradient-to-br from-[#9b59b6] to-[#4b2c82] text-white flex items-center justify-center active:scale-95 transition-transform shadow-md shadow-[#4b2c82]/20"
            aria-label="Rechercher"
            @click="fetchProducts(false)"
          >
            <i class="ri-search-line text-lg"></i>
          </button>
        </div>
        <p v-if="searchType === 'sku'" class="text-[10px] text-[#8e44ad]/80 mt-1 pl-3">Mode référence SKU</p>
      </div>

      <!-- Recherche par image -->
      <div
        v-if="imagePreview || imageSearchActive || imageSearchError || imageSearching"
        class="px-3 pb-2.5"
      >
        <div class="rounded-xl border border-[#e8d4f0] bg-white/90 p-3 shadow-sm">
          <div v-if="imagePreview" class="flex gap-3 items-start">
            <img :src="imagePreview" alt="Aperçu" class="w-14 h-14 rounded-lg object-cover border border-[#f0e4f7] shrink-0">
            <div class="flex-1 min-w-0">
              <p class="text-xs font-bold text-[#4b2c82]">Recherche par photo</p>
              <p v-if="generatedSearchText" class="text-[10px] text-[#8e44ad]/80 mt-0.5 line-clamp-2">
                {{ generatedSearchText }}
              </p>
              <p v-if="imageSearchMeta" class="text-[10px] text-[#9b59b6] mt-1">
                {{ imageSearchMeta.total }} résultat(s) · {{ imageSearchMeta.scanned }} analysé(s)
              </p>
            </div>
            <button
              type="button"
              class="text-[#8e44ad] p-1 shrink-0"
              aria-label="Annuler la recherche par image"
              @click="clearImageSearch"
            >
              <i class="ri-close-line text-xl"></i>
            </button>
          </div>
          <div v-if="imageSearching" class="flex items-center gap-2 mt-2 text-xs text-[#8e44ad]">
            <i class="ri-loader-4-line animate-spin text-base"></i>
            Analyse IA en cours…
          </div>
          <div v-if="imageSearchError" class="mt-2 text-xs text-red-700 bg-red-50 rounded-lg px-2 py-1.5 border border-red-100">
            {{ imageSearchError }}
          </div>
        </div>
      </div>
    </header>

    <main class="px-2">
      <div v-if="error" class="mx-1 mb-2 p-3 rounded-xl bg-red-50 text-[#c0392b] text-xs border border-red-100">
        {{ error }}
      </div>

      <div v-if="loading && !imageSearchActive && products.length === 0" class="flex flex-col items-center justify-center py-16">
        <div class="w-10 h-10 border-[3px] border-[#9b59b6] border-t-transparent rounded-full animate-spin"></div>
        <p class="text-xs text-[#8e44ad]/70 mt-3">Chargement…</p>
      </div>

      <div v-else-if="imageSearching && displayProducts.length === 0" class="flex flex-col items-center justify-center py-16">
        <div class="w-10 h-10 border-[3px] border-[#9b59b6] border-t-transparent rounded-full animate-spin"></div>
        <p class="text-xs text-[#8e44ad]/70 mt-3">Analyse IA et recherche…</p>
      </div>

      <div v-else-if="!loading && !imageSearching && displayProducts.length === 0" class="flex flex-col items-center py-16 text-center px-6">
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#f3e5f9] to-[#e8d4f0] flex items-center justify-center">
          <i class="ri-shopping-bag-3-line text-3xl text-[#9b59b6]"></i>
        </div>
        <p class="text-sm text-[#4b2c82] mt-3 font-semibold">
          {{ imageSearchActive ? 'Aucun produit correspondant' : 'Aucun produit trouvé' }}
        </p>
        <p class="text-xs text-[#8e44ad]/70 mt-1">
          {{ imageSearchActive ? 'Essayez une autre photo ou effacez la recherche IA.' : 'Essayez une autre recherche ou catégorie' }}
        </p>
      </div>

      <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 max-w-6xl mx-auto">
        <article
          v-for="product in displayProducts"
          :key="product.nid"
          @click="goToProduct(product.nid)"
          class="bg-white rounded-xl overflow-hidden active:scale-[0.98] transition-transform cursor-pointer border border-[#f0e4f7] shadow-sm shadow-[#4b2c82]/5"
        >
          <div class="aspect-square bg-[#faf5fc] relative overflow-hidden">
            <img
              v-if="getProductImageUrl(product)"
              :src="getProductImage(product)"
              :alt="product.title"
              class="w-full h-full object-cover"
              loading="lazy"
            >
            <div v-else class="w-full h-full flex items-center justify-center text-[#d4b8e8]">
              <i class="ri-image-line text-4xl"></i>
            </div>
            <span class="absolute top-1.5 left-1.5 bg-gradient-to-r from-[#9b59b6] to-[#4b2c82] text-white text-[9px] font-bold px-1.5 py-0.5 rounded-md shadow-sm">
              En stock
            </span>
            <span
              v-if="imageSearchActive && product._ai_score != null"
              class="absolute top-1.5 right-1.5 bg-[#4b2c82] text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-sm"
            >
              {{ product._ai_score }}%
            </span>
          </div>
          <div class="p-2 pb-2.5">
            <h3 class="text-[13px] text-[#3d2a52] leading-snug line-clamp-2 min-h-[2.25rem] font-medium">
              {{ product.title }}
            </h3>
            <div class="mt-1.5 flex items-baseline gap-0.5">
              <span class="text-[11px] text-[#8e44ad] font-bold">Ar</span>
              <span class="text-[17px] eroso-price font-bold leading-none tracking-tight">
                {{ formatPrice(isReseller ? product.field_prix_revendeur : product.field_prix_vente) }}
              </span>
            </div>
            <p v-if="product.field_sku" class="text-[10px] text-[#9b8aab] mt-1 truncate">
              Réf. {{ product.field_sku }}
            </p>
            <p v-else class="text-[10px] text-[#9b8aab] mt-1">Disponible en boutique</p>
            <button
              type="button"
              class="mt-2 w-full rounded-lg bg-gradient-to-r from-[#9b59b6] to-[#4b2c82] py-2 text-[11px] font-bold text-white active:scale-[0.98] transition-transform"
              @click.stop="addToCart(product)"
            >
              <i class="ri-shopping-cart-2-line mr-1"></i>
              Ajouter au panier
            </button>
          </div>
        </article>
      </div>

      <div ref="loadMoreTrigger" class="flex justify-center py-6 min-h-[60px] items-center">
        <div v-if="loading && !imageSearchActive && products.length > 0" class="w-6 h-6 border-2 border-[#9b59b6] border-t-transparent rounded-full animate-spin"></div>
        <p v-else-if="!imageSearchActive && !hasMore && products.length > 0" class="text-[10px] text-[#9b8aab]">— Fin —</p>
      </div>
    </main>

    <div
      v-if="isCartOpen"
      class="fixed inset-0 z-[70] bg-[#4b2c82]/30 backdrop-blur-[2px]"
      @click.self="isCartOpen = false"
    >
      <section class="absolute bottom-0 inset-x-0 max-h-[88vh] overflow-y-auto rounded-t-2xl border-t border-[#e8d4f0] bg-[#fdf2f9] p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:left-auto sm:max-w-md">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="text-base font-bold text-[#4b2c82]">Mon panier</h2>
            <p class="text-xs text-[#8e44ad]">{{ cartItemCount }} article(s)</p>
          </div>
          <button type="button" class="p-1 text-[#8e44ad]" aria-label="Fermer le panier" @click="isCartOpen = false">
            <i class="ri-close-line text-2xl"></i>
          </button>
        </div>

        <div v-if="orderItems.length === 0" class="py-12 text-center text-[#9b8aab]">
          <i class="ri-shopping-cart-line text-4xl"></i>
          <p class="mt-2 text-sm">Votre panier est vide</p>
        </div>

        <div v-else class="mt-4 space-y-2">
          <div
            v-for="(item, index) in orderItems"
            :key="productNid(item.product)"
            class="flex items-center gap-2 rounded-xl border border-[#f0e4f7] bg-white p-2"
          >
            <img
              v-if="getProductImageUrl(item.product)"
              :src="getProductImage(item.product)"
              :alt="item.product.title"
              class="h-12 w-12 rounded-lg object-cover"
            >
            <div class="min-w-0 flex-1">
              <p class="truncate text-xs font-bold text-[#3d2a52]">{{ item.product.title }}</p>
              <p class="text-[11px] text-[#8e44ad]">{{ formatPrice(itemPrice(item.product)) }} Ar</p>
            </div>
            <div class="flex items-center gap-1">
              <button type="button" class="h-7 w-7 rounded-full bg-[#f3e5f9] text-[#5e35b1]" @click="decreaseCartQuantity(index)">
                <i class="ri-subtract-line"></i>
              </button>
              <span class="w-6 text-center text-xs font-bold text-[#4b2c82]">{{ item.quantity }}</span>
              <button type="button" class="h-7 w-7 rounded-full bg-[#5e35b1] text-white" @click="increaseCartQuantity(index)">
                <i class="ri-add-line"></i>
              </button>
            </div>
            <button type="button" class="p-1 text-red-500" aria-label="Supprimer l'article" @click="removeCartItem(index)">
              <i class="ri-delete-bin-line"></i>
            </button>
          </div>
        </div>

        <div v-if="orderItems.length" class="mt-4 border-t border-[#e8d4f0] pt-4">
          <label for="public-order-notes" class="text-xs font-semibold text-[#5a4a6a]">Notes (optionnel)</label>
          <textarea
            id="public-order-notes"
            v-model="orderNotes"
            rows="2"
            placeholder="Ajouter une note… infos sur vous"
            class="mt-1 w-full resize-none rounded-lg border border-[#e8d4f0] bg-white px-3 py-2 text-sm text-[#3d2a52] outline-none focus:border-[#9b59b6]"
          ></textarea>
          <div class="mt-3 flex items-center justify-between text-base font-bold text-[#4b2c82]">
            <span>Total</span>
            <span>{{ formatPrice(cartSubtotal) }} Ar</span>
          </div>
          <button
            type="button"
            class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#9b59b6] to-[#4b2c82] py-3 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="savingOrder"
            @click="sendOrder"
          >
            <i v-if="savingOrder" class="ri-loader-4-line animate-spin"></i>
            <i v-else class="ri-send-plane-line"></i>
            {{ savingOrder ? 'Envoi en cours…' : 'Envoyer la commande' }}
          </button>
        </div>
      </section>
    </div>

    <!-- Bottom tab bar -->
    <nav class="fixed bottom-0 inset-x-0 z-50 bg-white/95 backdrop-blur-md border-t border-[#e8d4f0] pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_20px_rgba(75,44,130,0.06)]">
      <div class="grid grid-cols-4 h-14 max-w-lg mx-auto">
        <button type="button" class="flex flex-col items-center justify-center gap-0.5 text-[#5e35b1]" @click="router.push('/home')">
          <i class="ri-home-5-fill text-[22px]"></i>
          <span class="text-[10px] font-semibold">Accueil</span>
        </button>
        <button
          type="button"
          class="flex flex-col items-center justify-center gap-0.5 text-[#9b8aab]"
          @click="goToProfile"
        >
          <i class="ri-user-line text-[22px]"></i>
          <span class="text-[10px]">Profil</span>
        </button>
        <button
          type="button"
          class="flex flex-col items-center justify-center gap-0.5 text-[#9b8aab]"
          @click="isCartOpen = true"
        >
          <i class="ri-shopping-cart-2-line text-[22px]"></i>
          <span class="text-[10px]">Panier</span>
          <span v-if="cartItemCount" class="absolute top-1 right-[calc(50%-75px)] min-w-[16px] h-4 px-1 bg-[#4b2c82] text-white text-[9px] font-bold rounded-full flex items-center justify-center">
            {{ cartItemCount > 99 ? '99+' : cartItemCount }}
          </span>
        </button>
        <button
          type="button"
          class="flex flex-col items-center justify-center gap-0.5 text-[#9b8aab] relative"
          @click="scrollToTop"
        >
          <i class="ri-arrow-up-circle-line text-[22px]"></i>
          <span class="text-[10px]">Haut</span>
          <span
            v-if="total > 0"
            class="absolute top-1 right-[calc(50%-18px)] min-w-[16px] h-4 px-1 bg-gradient-to-r from-[#9b59b6] to-[#4b2c82] text-white text-[9px] font-bold rounded-full flex items-center justify-center"
          >
            {{ total > 99 ? '99+' : total }}
          </span>
        </button>
      </div>
    </nav>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { getPublicProducts, getPublicCategories, searchPublicProductsByImage, saveOrderCommande, getApiErrorMessage } from '../services/api';
import { proxyImage } from '../services/image';
import { setSelectedAppId, getDashboardPathForApp, SPACE_BOUTIQUE } from '../config/appContext';
import { canAccessBackOffice, hasRole } from '../utils/userRoles';

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
const imageInput = ref(null);
const imageFile = ref(null);
const imagePreview = ref('');
const imageSearchResults = ref([]);
const imageSearchActive = ref(false);
const imageSearching = ref(false);
const imageSearchError = ref('');
const generatedSearchText = ref('');
const imageSearchMeta = ref(null);
const orderItems = ref([]);
const orderNotes = ref('');
const isCartOpen = ref(false);
const savingOrder = ref(false);
const toastMessage = ref('');
const showBoutiqueButton = computed(() => !localStorage.getItem('token') || canAccessBackOffice());
const isReseller = computed(() => hasRole('reseller'));

const displayProducts = computed(() => {
  let list = imageSearchActive.value ? [...imageSearchResults.value] : [...products.value];
  if (selectedCategory.value) {
    list = list.filter((p) => {
      const tid = p.field_category?.tid ?? p.field_category?.[0]?.tid;
      return String(tid) === String(selectedCategory.value);
    });
  }
  if (imageSearchActive.value) {
    list.sort((a, b) => Number(b._ai_score || 0) - Number(a._ai_score || 0));
  }
  return list;
});

const cartItemCount = computed(() => orderItems.value.reduce((count, item) => count + item.quantity, 0));
const cartSubtotal = computed(() => orderItems.value.reduce((total, item) => total + itemPrice(item.product) * item.quantity, 0));

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
  if (!imageSearchActive.value) {
    fetchProducts(false);
  }
}

function openImagePicker() {
  imageInput.value?.click();
}

function onImageSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
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
  generatedSearchText.value = '';
  imageSearchActive.value = false;
  imageSearchResults.value = [];
  searchQuery.value = '';
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
  error.value = '';
  try {
    const res = await searchPublicProductsByImage(imageFile.value);
    const data = res?.data;
    if (!data?.status) {
      throw new Error(data?.message || 'Recherche IA échouée.');
    }
    generatedSearchText.value = data.field_search_image || '';
    imageSearchResults.value = data.rows || [];
    imageSearchMeta.value = {
      total: data.total ?? (data.rows?.length || 0),
      scanned: data.scanned ?? 0,
    };
    imageSearchActive.value = true;
    total.value = imageSearchResults.value.length;
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
  generatedSearchText.value = '';
  imageSearchMeta.value = null;
  if (products.value.length === 0) {
    fetchProducts(false);
  }
}

function toggleSearchType() {
  if (imageSearchActive.value) {
    clearImageSearch();
  }
  searchType.value = searchType.value === 'title' ? 'sku' : 'title';
  if (searchQuery.value.trim().length >= 2) {
    fetchProducts(false);
  }
}

function clearSearch() {
  if (searchQuery.value) {
    searchQuery.value = '';
    if (imageSearchActive.value) {
      clearImageSearch();
    } else {
      fetchProducts(false);
    }
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

function goToProfile() {
  router.push(localStorage.getItem('token') ? '/profile' : '/login');
}

function goToBoutiqueApp() {
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

function productNid(product) {
  return product.nid ?? product.id;
}

function itemPrice(product) {
  return Number(product.field_prix_vente || product.field_price || 0);
}

function showCartToast(message) {
  toastMessage.value = message;
  window.setTimeout(() => {
    toastMessage.value = '';
  }, 2500);
}

function addToCart(product) {
  const existingItem = orderItems.value.find((item) => String(productNid(item.product)) === String(productNid(product)));
  if (existingItem) {
    existingItem.quantity += 1;
  } else {
    orderItems.value.push({ product, quantity: 1 });
  }
  sessionStorage.setItem('public_order_cart', JSON.stringify(orderItems.value));
  showCartToast(`${product.title} ajouté au panier`);
}

function increaseCartQuantity(index) {
  orderItems.value[index].quantity += 1;
}

function decreaseCartQuantity(index) {
  if (orderItems.value[index].quantity > 1) {
    orderItems.value[index].quantity -= 1;
  } else {
    removeCartItem(index);
  }
}

function removeCartItem(index) {
  orderItems.value.splice(index, 1);
}

async function sendOrder() {
  if (orderItems.value.length === 0 || savingOrder.value) return;
  if (!localStorage.getItem('token')) {
    sessionStorage.setItem('public_order_cart', JSON.stringify(orderItems.value));
    sessionStorage.setItem('public_order_notes', orderNotes.value);
    router.push('/login');
    return;
  }

  savingOrder.value = true;
  try {
    const response = await saveOrderCommande({
      items: orderItems.value.map((item) => ({
        product_nid: productNid(item.product),
        quantity: item.quantity,
        prix_unitaire: itemPrice(item.product),
      })),
      notes: orderNotes.value.trim(),
      field_status_commande: 'draft_client',
    });
    if (!response.data?.status) {
      throw new Error(response.data?.message || 'Impossible d’envoyer la commande.');
    }
    orderItems.value = [];
    orderNotes.value = '';
    sessionStorage.removeItem('public_order_cart');
    sessionStorage.removeItem('public_order_notes');
    isCartOpen.value = false;
    showCartToast('Commande envoyée avec succès');
  } catch (e) {
    showCartToast(e?.response?.data?.message || e?.message || 'Impossible d’envoyer la commande.');
  } finally {
    savingOrder.value = false;
  }
}

function setupIntersectionObserver() {
  if (observer) observer.disconnect();
  if (imageSearchActive.value) return;
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

watch(searchQuery, () => {
  if (imageSearchActive.value) return;
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => fetchProducts(false), 450);
});

onMounted(() => {
  try {
    const savedCart = JSON.parse(sessionStorage.getItem('public_order_cart') || '[]');
    if (Array.isArray(savedCart)) orderItems.value = savedCart;
    orderNotes.value = sessionStorage.getItem('public_order_notes') || '';
  } catch {
    sessionStorage.removeItem('public_order_cart');
    sessionStorage.removeItem('public_order_notes');
  }
  loadCategories();
  fetchProducts(false);
});

onUnmounted(() => {
  if (observer) observer.disconnect();
  if (searchTimeout) clearTimeout(searchTimeout);
});
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap');

.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.public-home {
  font-family: 'Nunito', system-ui, -apple-system, sans-serif;
  -webkit-tap-highlight-color: transparent;
}

.eroso-brand {
  background: linear-gradient(180deg, #b57edc 0%, #8e44ad 45%, #4b2c82 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.eroso-price {
  background: linear-gradient(135deg, #9b59b6 0%, #4b2c82 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
</style>
