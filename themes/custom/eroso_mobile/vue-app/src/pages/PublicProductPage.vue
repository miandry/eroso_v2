<template>
  <div class="min-h-screen bg-[#f5f5f5] font-sans pb-[calc(3.5rem+env(safe-area-inset-bottom))]">
    <!-- Top bar -->
    <header class="sticky top-0 z-40 bg-[#f5f5f5] pt-[env(safe-area-inset-top)]">
      <div class="flex items-center gap-2 px-2 py-2">
        <button
          type="button"
          @click="router.push('/home')"
          class="w-9 h-9 flex items-center justify-center text-gray-800 active:opacity-60"
          aria-label="Retour"
        >
          <i class="ri-arrow-left-s-line text-2xl"></i>
        </button>
        <div class="flex-1 min-w-0 bg-white rounded-full border border-gray-200 px-3 py-1.5 flex items-center gap-2">
          <i class="ri-search-line text-gray-400 text-sm"></i>
          <span class="text-xs text-gray-400 truncate">{{ product?.title || 'Produit' }}</span>
        </div>
        <button type="button" class="w-9 h-9 flex items-center justify-center text-gray-600" aria-label="Partager">
          <i class="ri-share-forward-line text-xl"></i>
        </button>
      </div>
    </header>

    <main v-if="loading" class="flex flex-col items-center justify-center py-24">
      <div class="w-10 h-10 border-[3px] border-[#ff5000] border-t-transparent rounded-full animate-spin"></div>
      <p class="text-xs text-gray-400 mt-3">Chargement…</p>
    </main>

    <main v-else-if="error" class="text-center py-24 px-6">
      <i class="ri-error-warning-line text-4xl text-gray-300"></i>
      <p class="text-sm text-gray-600 mt-3">{{ error }}</p>
      <button
        type="button"
        @click="router.push('/home')"
        class="mt-5 px-6 py-2.5 bg-[#ff5000] text-white text-sm font-bold rounded-full"
      >
        Retour à l'accueil
      </button>
    </main>

    <article v-else-if="product">
      <!-- Image gallery -->
      <div class="bg-white">
        <div class="aspect-square bg-gray-100">
          <img
            v-if="productImage"
            :src="productImage"
            :alt="product.title"
            class="w-full h-full object-cover"
          >
          <div v-else class="w-full h-full flex items-center justify-center text-gray-300">
            <i class="ri-image-line text-6xl"></i>
          </div>
        </div>
      </div>

      <!-- Price block -->
      <div class="bg-white mt-2 px-3 py-3">
        <div class="flex items-baseline gap-1">
          <span class="text-sm text-[#ff5000] font-bold">Ar</span>
          <span class="text-3xl text-[#ff5000] font-bold tracking-tight">
            {{ formatPrice(product.field_prix_vente) }}
          </span>
        </div>
        <h1 class="text-[15px] text-gray-900 leading-relaxed mt-2 font-medium">
          {{ product.title }}
        </h1>
        <div class="flex flex-wrap gap-2 mt-2">
          <span class="text-[10px] bg-[#fff0eb] text-[#ff5000] px-2 py-0.5 rounded font-bold">
            En stock
          </span>
          <span
            v-if="categoryName"
            class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded"
          >
            {{ categoryName }}
          </span>
          <span v-if="product.field_sku" class="text-[10px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded">
            Réf. {{ product.field_sku }}
          </span>
        </div>
      </div>

      <!-- Description -->
      <div v-if="product.field_description" class="bg-white mt-2 px-3 py-3">
        <h2 class="text-sm font-bold text-gray-900 mb-2">Description</h2>
        <div
          class="text-[13px] text-gray-600 leading-relaxed prose prose-sm max-w-none"
          v-html="product.field_description"
        ></div>
      </div>

      <div class="h-4"></div>
    </article>

    <!-- Bottom action bar -->
    <nav
      v-if="product && !loading"
      class="fixed bottom-0 inset-x-0 z-50 bg-white border-t border-gray-200 pb-[env(safe-area-inset-bottom)]"
    >
      <div class="flex items-center h-14 px-2 gap-2 max-w-lg mx-auto">
        <button
          type="button"
          @click="router.push('/home')"
          class="flex flex-col items-center justify-center w-12 text-gray-600 shrink-0"
        >
          <i class="ri-home-5-line text-xl"></i>
          <span class="text-[9px]">Accueil</span>
        </button>
        <button
          type="button"
          class="flex-1 h-10 bg-[#ff5000] text-white text-sm font-bold rounded-full active:opacity-90"
          @click="router.push('/home')"
        >
          Voir le catalogue
        </button>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getPublicProduct } from '../services/api';
import { proxyImage } from '../services/image';

const route = useRoute();
const router = useRouter();

const product = ref(null);
const loading = ref(true);
const error = ref('');

const categoryName = computed(() => {
  const p = product.value;
  if (!p) return null;
  if (p.field_category?.title) return p.field_category.title;
  if (Array.isArray(p.field_category) && p.field_category[0]?.title) {
    return p.field_category[0].title;
  }
  return null;
});

const productImage = computed(() => {
  const p = product.value;
  if (!p) return '';
  let url = '';
  if (p.field_media_image?.image?.url) {
    url = p.field_media_image.image.url;
  } else if (p.field_images?.[0]?.image?.url) {
    url = p.field_images[0].image.url;
  }
  if (!url) return '';
  return proxyImage(url, { w: 800, h: 800, fit: 'cover' });
});

function formatPrice(price) {
  if (!price) return '0';
  return Number(price).toLocaleString('fr-MG');
}

async function loadProduct(nid) {
  loading.value = true;
  error.value = '';
  product.value = null;

  try {
    const res = await getPublicProduct(nid);
    product.value = res?.data || null;
    if (!product.value?.nid) {
      error.value = 'Produit introuvable ou non disponible.';
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Produit introuvable ou non disponible.';
  } finally {
    loading.value = false;
  }
}

watch(
  () => route.params.id,
  (id) => {
    if (id) loadProduct(id);
  },
  { immediate: true }
);
</script>
