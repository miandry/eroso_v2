<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button type="button" class="p-1 -ml-1 text-gray-600 cursor-pointer lg:hidden" @click="uiStore.toggleSidebar">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <div>
            <h1 class="text-lg font-semibold text-gray-900">Mouvements stock</h1>
            <p class="text-[11px] text-gray-500 -mt-0.5">Entrées et sorties</p>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <button
            type="button"
            class="w-8 h-8 flex items-center justify-center text-purple-600 bg-purple-50 rounded-lg"
            :disabled="loading"
            @click="loadMovements(false)"
          >
            <i class="ri-refresh-line" :class="{ 'animate-spin': loading }"></i>
          </button>
          <router-link
            to="/stock-insert"
            class="w-8 h-8 flex items-center justify-center text-white bg-purple-600 rounded-lg shadow-sm"
          >
            <i class="ri-add-line"></i>
          </router-link>
        </div>
      </div>
    </nav>

    <main class="pt-20 pb-24 px-4 lg:ml-64">
      <div class="mb-4 space-y-3">
        <div class="relative">
          <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Produit, titre, raison… (2 caractères min.)"
            class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
          >
        </div>

        <div class="flex flex-wrap items-end gap-2">
          <label class="flex flex-col gap-0.5 text-[10px] font-bold text-gray-500 uppercase tracking-wide shrink-0">
            Du
            <input
              v-model="dateFrom"
              type="date"
              class="px-2 py-1.5 border border-gray-200 rounded-lg text-xs bg-white text-gray-800 min-w-0"
            >
          </label>
          <label class="flex flex-col gap-0.5 text-[10px] font-bold text-gray-500 uppercase tracking-wide shrink-0">
            Au
            <input
              v-model="dateTo"
              type="date"
              class="px-2 py-1.5 border border-gray-200 rounded-lg text-xs bg-white text-gray-800 min-w-0"
            >
          </label>
          <button
            v-if="dateFrom || dateTo"
            type="button"
            class="self-end px-2 py-1 text-[10px] font-semibold text-purple-600 hover:text-purple-800"
            @click="clearDates"
          >
            Effacer dates
          </button>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="f in typeFilters"
            :key="f.value || 'all'"
            type="button"
            :class="[
              'px-3 py-1.5 rounded-full text-xs font-semibold transition-colors',
              typeFilter === f.value
                ? 'bg-purple-600 text-white'
                : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50',
            ]"
            @click="typeFilter = f.value"
          >
            {{ f.label }}
          </button>
        </div>
      </div>

      <div v-if="listError" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        Impossible de charger les mouvements. Réessayez.
      </div>

      <div v-if="!loading && filteredMovements.length > 0" class="mb-4 grid grid-cols-3 gap-2">
        <div class="bg-white rounded-xl border border-gray-100 p-3 text-center">
          <div class="text-lg font-black text-gray-900">{{ filteredMovements.length }}</div>
          <div class="text-[10px] text-gray-500 uppercase font-semibold">Lignes</div>
        </div>
        <div class="bg-green-50 rounded-xl border border-green-100 p-3 text-center">
          <div class="text-lg font-black text-green-700">+{{ summaryIn }}</div>
          <div class="text-[10px] text-green-600 uppercase font-semibold">Entrées</div>
        </div>
        <div class="bg-red-50 rounded-xl border border-red-100 p-3 text-center">
          <div class="text-lg font-black text-red-700">−{{ summaryOut }}</div>
          <div class="text-[10px] text-red-600 uppercase font-semibold">Sorties</div>
        </div>
      </div>

      <div v-if="loading && movements.length === 0" class="flex flex-col items-center justify-center py-20 space-y-4">
        <div class="w-12 h-12 border-4 border-purple-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-gray-500 font-medium">Chargement…</p>
      </div>

      <div v-else-if="!loading && filteredMovements.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
        <i class="ri-stack-line text-5xl text-gray-300 mb-3"></i>
        <p class="text-sm font-medium text-gray-700">Aucun mouvement</p>
        <p class="text-xs text-gray-500 mt-1">Les entrées et sorties enregistrées apparaîtront ici.</p>
        <router-link
          to="/stock-insert"
          class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white text-sm font-semibold rounded-xl"
        >
          <i class="ri-add-line"></i>
          Nouveau mouvement
        </router-link>
      </div>

      <div v-else class="space-y-3">
        <article
          v-for="item in filteredMovements"
          :key="item.nid"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4"
        >
          <div class="flex items-start gap-3">
            <component
              :is="productNid(item) ? 'router-link' : 'div'"
              :to="productNid(item) ? `/product/${productNid(item)}` : undefined"
              class="shrink-0 block"
            >
              <div class="w-20 h-20 rounded-xl overflow-hidden bg-gray-100 border border-gray-100 relative">
                <img
                  v-if="getProductImage(item)"
                  :src="getProductImage(item)"
                  :alt="getDisplayTitle(item)"
                  class="w-full h-full object-cover"
                  loading="lazy"
                  @error="onProductImageError(item)"
                >
                <div v-else class="w-full h-full flex items-center justify-center text-gray-300">
                  <i class="ri-image-2-line text-2xl"></i>
                </div>
                <span
                  :class="[
                    'absolute bottom-0 left-0 right-0 text-center text-[9px] font-bold py-0.5',
                    isIn(item) ? 'bg-green-600/90 text-white' : 'bg-red-600/90 text-white',
                  ]"
                >
                  {{ isIn(item) ? 'Entrée' : 'Sortie' }}
                </span>
              </div>
            </component>

            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <h2 class="text-sm font-bold text-gray-900 leading-snug line-clamp-2">
                    {{ getDisplayTitle(item) }}
                  </h2>
                  <p v-if="productSku(item)" class="text-[10px] text-gray-500 mt-0.5 uppercase tracking-wide font-semibold">
                    Réf. {{ productSku(item) }}
                  </p>
                  <p
                    v-if="getProductStockDispo(item) != null"
                    class="mt-1.5 inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md"
                    :class="Number(getProductStockDispo(item)) > 0 ? 'bg-blue-50 text-blue-700' : 'bg-orange-50 text-orange-700'"
                  >
                    <i class="ri-stack-line"></i>
                    Stock dispo : {{ getProductStockDispo(item) }}
                  </p>
                </div>
                <div class="text-right shrink-0">
                  <p class="text-[10px] text-gray-400 uppercase font-semibold">Qté mouvement</p>
                  <p :class="['text-xl font-black leading-tight', isIn(item) ? 'text-green-600' : 'text-red-600']">
                    {{ isIn(item) ? '+' : '−' }}{{ readQty(item) }}
                  </p>
                  <p v-if="lineValue(item) > 0" class="text-[10px] text-gray-500 mt-0.5">
                    {{ formatPrice(lineValue(item)) }} Ar
                  </p>
                </div>
              </div>

              <p class="text-[10px] text-gray-400 mt-2">
                <span class="font-mono text-gray-500">#{{ item.nid }}</span>
                · {{ formatDate(getMovementDateRaw(item)) }}
                <span v-if="readScalar(item.field_raison)"> · {{ readScalar(item.field_raison) }}</span>
              </p>
              <p v-if="readScalar(item.field_description)" class="text-xs text-gray-600 mt-1.5 line-clamp-2">
                {{ readScalar(item.field_description) }}
              </p>
            </div>
          </div>
        </article>
      </div>

      <div ref="loadMoreTrigger" class="flex justify-center py-8 min-h-[80px] items-center">
        <div v-if="loading && movements.length > 0" class="w-8 h-8 border-3 border-purple-600 border-t-transparent rounded-full animate-spin"></div>
        <p v-else-if="!hasMore && movements.length > 0" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">
          Fin de la liste
        </p>
      </div>
    </main>

    <router-link
      to="/stock-insert"
      class="fixed right-6 bottom-24 w-14 h-14 bg-purple-600 text-white rounded-full shadow-lg flex items-center justify-center z-40 active:scale-90 transition-transform lg:ml-64"
    >
      <i class="ri-add-line ri-2x"></i>
    </router-link>

    <BottomNav :showOnDesktop="true" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { getLists, getDetail } from '../services/api';
import { useUIStore } from '../stores/useUIStore';
import { useProductStore } from '../stores/useProductStore';
import { extractProductImageUrl } from './eroso_commande/orderCommandeShared';
import { proxyImage } from '../services/image';
import BottomNav from '../components/BottomNav.vue';

const uiStore = useUIStore();
const productStore = useProductStore();

const ITEMS_PER_PAGE = 20;

const typeFilters = [
  { value: '', label: 'Tous' },
  { value: 'in', label: 'Entrées' },
  { value: 'out', label: 'Sorties' },
];

const movements = ref([]);
const loading = ref(false);
const listError = ref(null);
const hasMore = ref(true);
const currentPage = ref(0);
const searchQuery = ref('');
const typeFilter = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const loadMoreTrigger = ref(null);
let observer = null;

/** Cache produit : nid → { title, sku, stock, imageUrl } */
const productCache = reactive({});
const productFetchPending = new Set();

const readScalar = (raw) => {
  if (raw == null || raw === '') return '';
  if (typeof raw === 'number' || typeof raw === 'string') return raw;
  if (Array.isArray(raw) && raw.length > 0) {
    const first = raw[0];
    if (first && typeof first === 'object' && 'value' in first) return first.value;
    return first;
  }
  if (typeof raw === 'object' && 'value' in raw) return raw.value;
  return raw;
};

const readQty = (item) => {
  const q = Number(readScalar(item?.field_quantite));
  return Number.isFinite(q) ? q : 0;
};

const movementType = (item) => String(readScalar(item?.field_type) || '').toLowerCase();
const isIn = (item) => movementType(item) === 'in';

const productRef = (item) => {
  const raw = item?.field_product_id ?? item?.product;
  if (!raw) return null;
  if (typeof raw === 'object' && !Array.isArray(raw)) return raw;
  if (Array.isArray(raw) && raw[0]) return raw[0];
  return null;
};

const productNid = (item) => {
  const raw = item?.field_product_id ?? item?.product;
  if (raw == null || raw === '') return null;
  if (typeof raw === 'number' || (typeof raw === 'string' && /^\d+$/.test(String(raw)))) {
    return Number(raw);
  }
  const ref = productRef(item);
  if (!ref) return null;
  const nid = ref.nid ?? ref.id ?? ref.target_id;
  return nid != null && nid !== '' ? Number(nid) : null;
};

const productTitle = (item) => {
  if (item?.product_title) return String(item.product_title);
  const nid = productNid(item);
  if (nid != null && productCache[nid]?.title) return productCache[nid].title;
  const ref = productRef(item);
  if (ref?.title) return String(ref.title);
  if (item?.title) return String(item.title);
  return 'Produit';
};

const productSku = (item) => {
  const nid = productNid(item);
  if (nid && productCache[nid]?.sku) return productCache[nid].sku;
  const ref = productRef(item);
  const sku = ref?.field_sku ?? item?.product_sku;
  const v = readScalar(sku);
  return v ? String(v) : '';
};

const cacheProductFromNode = (nid, product) => {
  if (!nid || !product || typeof product !== 'object') return;
  const prev = productCache[nid] || {};
  productCache[nid] = {
    title: product.title || prev.title || '',
    sku: readScalar(product.field_sku) || prev.sku || '',
    stock: readScalar(product.field_quantite_disponible) ?? prev.stock ?? null,
    imageUrl: extractProductImageUrl(product) || prev.imageUrl || '',
  };
};

const seedProductFromItem = (item) => {
  const nid = productNid(item);
  if (nid == null) return;
  const ref = productRef(item);
  if (!ref || typeof ref !== 'object') return;
  cacheProductFromNode(nid, ref);
};

const getDisplayTitle = (item) => {
  const nid = productNid(item);
  if (nid && productCache[nid]?.title) return productCache[nid].title;
  return productTitle(item);
};

const getProductStockDispo = (item) => {
  const nid = productNid(item);
  if (nid != null && productCache[nid]?.stock != null && productCache[nid].stock !== '') {
    return productCache[nid].stock;
  }
  const ref = productRef(item);
  const fromRef = readScalar(ref?.field_quantite_disponible);
  if (fromRef !== '' && fromRef != null) return fromRef;
  if (nid != null) {
    const fromStore = productStore.getProductById(nid);
    if (fromStore && fromStore.field_quantite_disponible != null) {
      return fromStore.field_quantite_disponible;
    }
  }
  return null;
};

const getProductImage = (item) => {
  const nid = productNid(item);
  let url = '';
  if (nid != null && productCache[nid]?.imageUrl) {
    url = productCache[nid].imageUrl;
  }
  if (!url) {
    const ref = productRef(item);
    if (ref) url = extractProductImageUrl(ref);
  }
  if (!url) return '';
  if (url.startsWith('data:')) return url;
  return proxyImage(url, { w: 160, h: 160, fit: 'cover' });
};

const onProductImageError = (item) => {
  const nid = productNid(item);
  if (nid != null && productCache[nid]) {
    productCache[nid].imageUrl = '';
  }
};

const resolveProductsFor = async (items) => {
  if (!Array.isArray(items) || items.length === 0) return;
  for (const item of items) seedProductFromItem(item);

  const toFetch = [];
  for (const item of items) {
    const nid = productNid(item);
    if (nid == null) continue;
    const cached = productCache[nid];
    if (cached?.title && cached?.imageUrl && cached?.stock != null && cached?.stock !== '') continue;
    const fromStore = productStore.getProductById(nid);
    if (fromStore) {
      cacheProductFromNode(nid, fromStore);
      continue;
    }
    if (productFetchPending.has(nid)) continue;
    toFetch.push(nid);
  }

  await Promise.all(
    toFetch.map(async (nid) => {
      productFetchPending.add(nid);
      try {
        const res = await getDetail('node', 'product', nid);
        const data = res.data?.rows ?? res.data ?? null;
        const product = Array.isArray(data) ? data[0] : data;
        if (product && typeof product === 'object') {
          cacheProductFromNode(nid, product);
        }
      } catch {
        // Silent — card still shows title from movement if available.
      } finally {
        productFetchPending.delete(nid);
      }
    }),
  );
};

const getMovementDateRaw = (item) =>
  readScalar(item?.field_date_entree) || item?.created || item?.changed;

const parseMovementDate = (item) => {
  const raw = getMovementDateRaw(item);
  if (!raw) return null;
  if (/^\d+$/.test(String(raw))) {
    const d = new Date(Number(raw) * 1000);
    return isNaN(d.getTime()) ? null : d;
  }
  const s = String(raw);
  const d = new Date(s.includes('T') ? s : `${s}T00:00:00`);
  return isNaN(d.getTime()) ? null : d;
};

const toYmd = (date) => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

const lineValue = (item) => {
  const total = Number(readScalar(item?.field_total_price));
  if (Number.isFinite(total) && total > 0) return total;
  const qty = readQty(item);
  const price = Number(readScalar(item?.field_prix_de_vente) || readScalar(item?.field_price));
  if (Number.isFinite(price) && qty > 0) return qty * price;
  return 0;
};

const formatPrice = (price) => {
  if (!price) return '0';
  return Number(price).toLocaleString('fr-FR');
};

const formatDate = (value) => {
  if (!value) return '—';
  let date;
  if (/^\d+$/.test(String(value))) {
    date = new Date(Number(value) * 1000);
  } else {
    const s = String(value);
    date = new Date(s.includes('T') ? s : `${s}T00:00:00`);
  }
  if (isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
};

const matchesDateRange = (item) => {
  const d = parseMovementDate(item);
  if (!d) return !dateFrom.value && !dateTo.value;
  const ymd = toYmd(d);
  if (dateFrom.value && ymd < dateFrom.value) return false;
  if (dateTo.value && ymd > dateTo.value) return false;
  return true;
};

const matchesSearch = (item) => {
  const q = searchQuery.value.trim().toLowerCase();
  if (q.length < 2) return true;
  const parts = [
    item?.title,
    productTitle(item),
    productSku(item),
    readScalar(item?.field_raison),
    readScalar(item?.field_description),
  ];
  return parts.some((p) => String(p || '').toLowerCase().includes(q));
};

const filteredMovements = computed(() =>
  movements.value.filter((item) => matchesDateRange(item) && matchesSearch(item)),
);

const summaryIn = computed(() =>
  filteredMovements.value.filter(isIn).reduce((sum, item) => sum + readQty(item), 0),
);

const summaryOut = computed(() =>
  filteredMovements.value.filter((item) => !isIn(item)).reduce((sum, item) => sum + readQty(item), 0),
);

function buildListParams() {
  let params = `sort[val]=created&sort[op]=DESC&offset=${ITEMS_PER_PAGE}&pager=${currentPage.value}`;
  if (typeFilter.value) {
    params += `&filters[field_type][val]=${encodeURIComponent(typeFilter.value)}&filters[field_type][op]==`;
  }
  return params;
}

function clearDates() {
  dateFrom.value = '';
  dateTo.value = '';
}

async function loadMovements(append = false) {
  if (!append) {
    currentPage.value = 0;
    hasMore.value = true;
  }
  if (!hasMore.value && append) return;

  loading.value = true;
  listError.value = null;
  try {
    const response = await getLists('node', 'stock', buildListParams());
    const raw = response.data?.rows ?? response.data;
    const list = Array.isArray(raw) ? raw : raw && typeof raw === 'object' ? Object.values(raw) : [];

    if (append) {
      movements.value = [...movements.value, ...list];
    } else {
      movements.value = list;
    }

    resolveProductsFor(list);

    if (list.length < ITEMS_PER_PAGE) {
      hasMore.value = false;
    } else {
      currentPage.value += 1;
    }
  } catch (e) {
    listError.value = e;
    if (!append) movements.value = [];
    hasMore.value = false;
    console.error('stock list:', e);
  } finally {
    loading.value = false;
  }
}

function setupIntersectionObserver() {
  if (observer) observer.disconnect();
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0]?.isIntersecting && !loading.value && hasMore.value) {
        loadMovements(true);
      }
    },
    { root: null, rootMargin: '200px', threshold: 0 },
  );
  nextTick(() => {
    if (loadMoreTrigger.value) observer.observe(loadMoreTrigger.value);
  });
}

onMounted(() => {
  loadMovements(false);
  setupIntersectionObserver();
});

onUnmounted(() => {
  if (observer) observer.disconnect();
});

watch(typeFilter, () => {
  loadMovements(false);
});

watch(
  () => filteredMovements.value,
  (list) => {
    if (list.length > 0) resolveProductsFor(list);
  },
  { flush: 'post' },
);
</script>
