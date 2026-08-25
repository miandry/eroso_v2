<template>
  <div class="public-product min-h-screen bg-[#fdf2f9] font-sans pb-[calc(3.5rem+env(safe-area-inset-bottom))]">
    <header class="sticky top-0 z-40 bg-[#fdf2f9]/95 backdrop-blur-md pt-[env(safe-area-inset-top)] border-b border-[#e8d4f0]/60">
      <div class="flex items-center gap-2 px-2 py-2">
        <button
          type="button"
          @click="router.push('/home')"
          class="w-9 h-9 flex items-center justify-center text-[#4b2c82] active:opacity-60"
          aria-label="Retour"
        >
          <i class="ri-arrow-left-s-line text-2xl"></i>
        </button>
        <div class="flex-1 min-w-0 bg-white rounded-full border border-[#e8d4f0] px-3 py-1.5 flex items-center gap-2 shadow-sm">
          <i class="ri-search-line text-[#9b59b6] text-sm"></i>
          <span class="text-xs text-[#9b8aab] truncate">{{ product?.title || 'Produit' }}</span>
        </div>
        <button type="button" class="w-9 h-9 flex items-center justify-center text-[#8e44ad]" aria-label="Partager">
          <i class="ri-share-forward-line text-xl"></i>
        </button>
      </div>
    </header>

    <main v-if="loading" class="flex flex-col items-center justify-center py-24">
      <div class="w-10 h-10 border-[3px] border-[#9b59b6] border-t-transparent rounded-full animate-spin"></div>
      <p class="text-xs text-[#8e44ad]/70 mt-3">Chargement…</p>
    </main>

    <main v-else-if="error" class="text-center py-24 px-6">
      <i class="ri-error-warning-line text-4xl text-[#d4b8e8]"></i>
      <p class="text-sm text-[#4b2c82] mt-3">{{ error }}</p>
      <button
        type="button"
        @click="router.push('/home')"
        class="mt-5 px-6 py-2.5 bg-gradient-to-r from-[#9b59b6] to-[#4b2c82] text-white text-sm font-bold rounded-full shadow-md shadow-[#4b2c82]/20"
      >
        Retour à l'accueil
      </button>
    </main>

    <article v-else-if="product">
      <div class="bg-white border-b border-[#f0e4f7]">
        <div class="aspect-square bg-[#faf5fc]">
          <img
            v-if="productImage"
            :src="productImage"
            :alt="product.title"
            class="w-full h-full object-cover"
          >
          <div v-else class="w-full h-full flex items-center justify-center text-[#d4b8e8]">
            <i class="ri-image-line text-6xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white mt-2 px-3 py-3 border-y border-[#f0e4f7]">
        <div class="flex items-baseline gap-1">
          <span class="text-sm text-[#8e44ad] font-bold">Ar</span>
          <span class="text-3xl eroso-price font-bold tracking-tight">
            {{ formatPrice(product.field_prix_vente) }}
          </span>
        </div>
        <h1 class="text-[15px] text-[#3d2a52] leading-relaxed mt-2 font-semibold">
          {{ product.title }}
        </h1>
        <div class="flex flex-wrap gap-2 mt-2">
          <span class="text-[10px] bg-gradient-to-r from-[#f3e5f9] to-[#e8d4f0] text-[#5e35b1] px-2 py-0.5 rounded-md font-bold border border-[#e8d4f0]">
            En stock
          </span>
          <span
            v-if="categoryName"
            class="text-[10px] bg-[#faf5fc] text-[#8e44ad] px-2 py-0.5 rounded-md border border-[#f0e4f7]"
          >
            {{ categoryName }}
          </span>
          <span v-if="product.field_sku" class="text-[10px] bg-[#faf5fc] text-[#9b8aab] px-2 py-0.5 rounded-md border border-[#f0e4f7]">
            Réf. {{ product.field_sku }}
          </span>
        </div>
      </div>

      <div v-if="product.field_description" class="bg-white mt-2 px-3 py-3 border-y border-[#f0e4f7]">
        <h2 class="text-sm font-bold text-[#4b2c82] mb-2">Description</h2>
        <div
          class="text-[13px] text-[#5a4a6a] leading-relaxed prose prose-sm max-w-none"
          v-html="product.field_description"
        ></div>
      </div>

      <div class="h-4"></div>
    </article>

    <nav
      v-if="product && !loading"
      class="fixed bottom-0 inset-x-0 z-50 bg-white/95 backdrop-blur-md border-t border-[#e8d4f0] pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_20px_rgba(75,44,130,0.06)]"
    >
      <div class="flex items-center h-14 px-2 gap-2 max-w-lg mx-auto">
        <button
          type="button"
          @click="router.push('/home')"
          class="flex flex-col items-center justify-center w-12 text-[#8e44ad] shrink-0"
        >
          <i class="ri-home-5-line text-xl"></i>
          <span class="text-[9px] font-semibold">Accueil</span>
        </button>
        <a
          :href="messengerOrderUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="flex-1 h-10 bg-gradient-to-r from-[#0084ff] to-[#0064e0] text-white text-sm font-bold rounded-full active:opacity-90 shadow-md shadow-[#0064e0]/25 flex items-center justify-center gap-2 no-underline"
        >
          <i class="ri-messenger-line text-lg"></i>
          Commander sur Messenger
        </a>
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

const MESSENGER_THREAD_URL = 'https://www.messenger.com/e2ee/t/25561890373424978';

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

function stripHtml(html) {
  if (!html) return '';
  return String(html).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function buildMessengerOrderMessage(p) {
  if (!p) return 'Bonjour, je veux acheter un produit.';
  const lines = [
    'Bonjour, je veux acheter ce produit :',
    '',
    `Titre : ${p.title || 'N/A'}`,
    `Référence (SKU) : ${p.field_sku || 'N/A'}`,
    `Prix : ${formatPrice(p.field_prix_vente)} Ar`,
  ];
  if (categoryName.value) {
    lines.push(`Catégorie : ${categoryName.value}`);
  }
  const desc = stripHtml(p.field_description);
  if (desc) {
    const short = desc.length > 200 ? `${desc.slice(0, 197)}…` : desc;
    lines.push(`Description : ${short}`);
  }
  const origin = typeof window !== 'undefined' ? window.location.origin : '';
  const path = p.nid ? `/home/${p.nid}` : '/home';
  if (origin) {
    lines.push('', `Lien : ${origin}${path}`);
  }
  return lines.join('\n');
}

const messengerOrderUrl = computed(() => {
  const text = buildMessengerOrderMessage(product.value);
  return `${MESSENGER_THREAD_URL}?text=${encodeURIComponent(text)}`;
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

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap');

.public-product {
  font-family: 'Nunito', system-ui, -apple-system, sans-serif;
}

.eroso-price {
  background: linear-gradient(135deg, #9b59b6 0%, #4b2c82 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
</style>
