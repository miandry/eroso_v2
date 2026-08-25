<template>
  <div class="public-product min-h-screen bg-[#fdf2f9] font-sans pb-[calc(3.5rem+env(safe-area-inset-bottom))] lg:pb-10">
    <header class="sticky top-0 z-40 bg-[#fdf2f9]/95 backdrop-blur-md pt-[env(safe-area-inset-top)] border-b border-[#e8d4f0]/60">
      <div class="max-w-6xl mx-auto flex items-center gap-3 px-4 py-3 lg:px-8 lg:py-4">
        <button
          type="button"
          @click="router.push('/home')"
          class="w-9 h-9 lg:w-10 lg:h-10 flex items-center justify-center rounded-full bg-white border border-[#e8d4f0] text-[#4b2c82] hover:bg-[#faf5fc] transition-colors shrink-0"
          aria-label="Retour au catalogue"
        >
          <i class="ri-arrow-left-s-line text-2xl"></i>
        </button>
        <div class="flex-1 min-w-0">
          <p class="text-[10px] lg:text-xs font-semibold text-[#8e44ad] uppercase tracking-wider hidden sm:block">
            e-roso · Catalogue
          </p>
          <p class="text-sm lg:text-base text-[#3d2a52] font-semibold truncate">
            {{ product?.title || 'Produit' }}
          </p>
        </div>
        <a
          v-if="product"
          :href="messengerOrderUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-[#0084ff] to-[#0064e0] text-white text-sm font-bold shadow-md shadow-[#0064e0]/20 hover:opacity-95 transition-opacity no-underline shrink-0"
        >
          <i class="ri-messenger-line text-lg"></i>
          <span class="hidden md:inline">Commander</span>
        </a>
      </div>
    </header>

    <main v-if="loading" class="max-w-6xl mx-auto flex flex-col items-center justify-center py-24 lg:py-32 px-4">
      <div class="w-10 h-10 border-[3px] border-[#9b59b6] border-t-transparent rounded-full animate-spin"></div>
      <p class="text-xs lg:text-sm text-[#8e44ad]/70 mt-3">Chargement…</p>
    </main>

    <main v-else-if="error" class="max-w-6xl mx-auto text-center py-24 lg:py-32 px-6">
      <i class="ri-error-warning-line text-4xl lg:text-5xl text-[#d4b8e8]"></i>
      <p class="text-sm lg:text-base text-[#4b2c82] mt-3">{{ error }}</p>
      <button
        type="button"
        @click="router.push('/home')"
        class="mt-5 px-6 py-2.5 lg:px-8 lg:py-3 bg-gradient-to-r from-[#9b59b6] to-[#4b2c82] text-white text-sm lg:text-base font-bold rounded-full shadow-md shadow-[#4b2c82]/20"
      >
        Retour à l'accueil
      </button>
    </main>

    <article v-else-if="product" class="max-w-6xl mx-auto px-4 py-4 lg:px-8 lg:py-8">
      <div class="lg:grid lg:grid-cols-2 lg:gap-10 xl:gap-14 lg:items-start">
        <!-- Image -->
        <div class="lg:sticky lg:top-[5.5rem] lg:self-start">
          <div class="rounded-2xl lg:rounded-3xl overflow-hidden bg-white border border-[#f0e4f7] shadow-sm shadow-[#4b2c82]/5">
            <div class="aspect-square lg:aspect-[4/5] xl:aspect-square bg-[#faf5fc] max-h-[70vh] lg:max-h-none">
              <img
                v-if="productImage"
                :src="productImage"
                :alt="product.title"
                class="w-full h-full object-cover"
              >
              <div v-else class="w-full h-full flex items-center justify-center text-[#d4b8e8]">
                <i class="ri-image-line text-6xl lg:text-7xl"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Details -->
        <div class="mt-4 lg:mt-0 flex flex-col gap-4 lg:gap-6">
          <div class="bg-white rounded-2xl lg:rounded-3xl px-4 py-4 lg:px-6 lg:py-6 border border-[#f0e4f7] shadow-sm shadow-[#4b2c82]/5">
            <div class="flex flex-wrap items-center gap-2 mb-3">
              <span class="text-xs lg:text-sm bg-gradient-to-r from-[#f3e5f9] to-[#e8d4f0] text-[#5e35b1] px-2.5 py-1 rounded-lg font-bold border border-[#e8d4f0]">
                En stock
              </span>
              <span
                v-if="categoryName"
                class="text-xs lg:text-sm bg-[#faf5fc] text-[#8e44ad] px-2.5 py-1 rounded-lg border border-[#f0e4f7]"
              >
                {{ categoryName }}
              </span>
              <span
                v-if="product.field_sku"
                class="text-xs lg:text-sm bg-[#faf5fc] text-[#9b8aab] px-2.5 py-1 rounded-lg border border-[#f0e4f7] font-mono"
              >
                Réf. {{ product.field_sku }}
              </span>
            </div>

            <h1 class="text-lg sm:text-xl lg:text-2xl xl:text-3xl text-[#3d2a52] leading-snug font-bold">
              {{ product.title }}
            </h1>

            <div class="flex items-baseline gap-1.5 mt-4 lg:mt-5">
              <span class="text-base lg:text-lg text-[#8e44ad] font-bold">Ar</span>
              <span class="text-3xl lg:text-4xl xl:text-5xl eroso-price font-black tracking-tight">
                {{ formatPrice(product.field_prix_vente) }}
              </span>
            </div>

            <!-- Desktop / tablet CTA -->
            <div class="hidden sm:flex flex-col sm:flex-row gap-3 mt-6 lg:mt-8">
              <a
                :href="messengerOrderUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="flex-1 h-12 lg:h-14 bg-gradient-to-r from-[#0084ff] to-[#0064e0] text-white text-sm lg:text-base font-bold rounded-full shadow-lg shadow-[#0064e0]/25 flex items-center justify-center gap-2 hover:opacity-95 transition-opacity no-underline"
              >
                <i class="ri-messenger-line text-xl"></i>
                Commander sur Messenger
              </a>
              <button
                type="button"
                @click="router.push('/home')"
                class="sm:w-auto px-6 h-12 lg:h-14 rounded-full border-2 border-[#e8d4f0] text-[#5e35b1] text-sm lg:text-base font-bold bg-white hover:bg-[#faf5fc] transition-colors"
              >
                Catalogue
              </button>
            </div>
          </div>

          <div
            v-if="product.field_description"
            class="bg-white rounded-2xl lg:rounded-3xl px-4 py-4 lg:px-6 lg:py-6 border border-[#f0e4f7] shadow-sm shadow-[#4b2c82]/5"
          >
            <h2 class="text-sm lg:text-base font-bold text-[#4b2c82] mb-3">Description</h2>
            <div
              class="text-[13px] lg:text-[15px] text-[#5a4a6a] leading-relaxed lg:leading-loose prose prose-sm lg:prose-base max-w-none product-description"
              v-html="product.field_description"
            ></div>
          </div>
        </div>
      </div>
    </article>

    <!-- Mobile bottom bar -->
    <nav
      v-if="product && !loading"
      class="sm:hidden fixed bottom-0 inset-x-0 z-50 bg-white/95 backdrop-blur-md border-t border-[#e8d4f0] pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_20px_rgba(75,44,130,0.06)]"
    >
      <div class="flex items-center h-14 px-3 gap-2 max-w-lg mx-auto">
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
          Commander
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
  const isDesktop = typeof window !== 'undefined' && window.innerWidth >= 1024;
  return proxyImage(url, { w: isDesktop ? 1200 : 800, h: isDesktop ? 1200 : 800, fit: 'cover' });
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

.product-description :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 0.75rem;
}

.product-description :deep(p) {
  margin-bottom: 0.75rem;
}

@media (min-width: 1024px) {
  .product-description :deep(p) {
    margin-bottom: 1rem;
  }
}
</style>
