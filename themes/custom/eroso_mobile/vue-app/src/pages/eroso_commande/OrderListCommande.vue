<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button type="button" class="p-1 -ml-1 text-gray-600 cursor-pointer lg:hidden" @click="uiStore.toggleSidebar">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <h1 class="text-lg font-semibold text-gray-900">Commandes sur commande</h1>
        </div>
        <button
          type="button"
          class="w-8 h-8 flex items-center justify-center text-indigo-600 bg-indigo-50 rounded-lg cursor-pointer"
          @click="loadOrders(false)"
        >
          <i class="ri-refresh-line"></i>
        </button>
      </div>
    </nav>

    <main class="pt-16 pb-24 px-4 lg:ml-64">
      <div class="mb-4">
        <div class="relative">
          <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Titre, notes, nom ou réf. produit… (2 caractères min.)"
            class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
        </div>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
          <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
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
              class="self-end px-2 py-1 text-[10px] font-semibold text-indigo-600 hover:text-indigo-800"
              @click="clearDates"
            >
              Effacer dates
            </button>
          </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <button
            v-for="f in statusFilters"
            :key="f.value || 'all'"
            type="button"
            :class="[
              'px-3 py-1.5 rounded-full text-xs font-semibold transition-colors',
              statusFilter === f.value
                ? 'bg-indigo-600 text-white'
                : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50',
            ]"
            @click="statusFilter = f.value"
          >
            {{ f.label }}
          </button>
        </div>
      </div>

      <div v-if="listError" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        Impossible de charger les commandes. Réessayez.
      </div>

      <div v-if="loading && orders.length === 0" class="flex flex-col items-center justify-center py-20 space-y-4">
        <div class="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-gray-500 font-medium">Chargement…</p>
      </div>

      <div v-else-if="!loading && orders.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
        <i class="ri-file-list-3-line text-5xl text-gray-300 mb-3"></i>
        <p class="text-sm font-medium text-gray-700">Aucune commande</p>
        <p class="text-xs text-gray-500 mt-1">Les ventes enregistrées depuis la caisse sur commande apparaîtront ici.</p>
      </div>

      <div v-else class="space-y-3">
        <article
          v-for="order in orders"
          :key="order.nid"
          role="button"
          tabindex="0"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 cursor-pointer hover:border-indigo-200 hover:shadow transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2"
          @click="openOrder(order.nid)"
          @keydown.enter.prevent="openOrder(order.nid)"
          @keydown.space.prevent="openOrder(order.nid)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <h2 class="text-sm font-bold leading-snug">
                <span
                  :class="[
                    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold tracking-tight',
                    statusPillClass(order.status),
                  ]"
                >
                  {{ statusLabel(order.status) }}
                </span>
              </h2>
              <p class="text-[10px] text-gray-400 mt-1">
                <span class="font-mono text-gray-500">#{{ order.nid }}</span>
                · {{ formatDate(order.created) }}
              </p>
              <p v-if="order.clientLabel" class="text-xs text-gray-600 mt-2 flex items-center gap-1">
                <i class="ri-user-line text-gray-400"></i>
                {{ order.clientLabel }}
              </p>
              <p v-if="order.infoPreview" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ order.infoPreview }}</p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-base font-black text-indigo-600">{{ formatPrice(order.total) }} Ar</p>
            </div>
          </div>

          <ul
            v-if="order.cartLines && order.cartLines.length > 0"
            class="mt-3 space-y-2 border-t border-gray-100 pt-3"
          >
            <li
              v-for="(line, idx) in order.cartLines"
              :key="line.nid || idx"
              class="grid grid-cols-[1fr_auto_auto] gap-x-2 gap-y-0.5 items-baseline text-xs text-gray-700"
            >
              <span class="min-w-0 font-medium leading-snug break-words">{{ line.title }}</span>
              <span class="tabular-nums text-gray-500 text-right whitespace-nowrap">
                {{ line.qty != null ? `×${line.qty}` : '' }}
              </span>
              <span class="font-semibold text-gray-900 tabular-nums text-right whitespace-nowrap">
                <template v-if="line.lineTotal != null">{{ formatPrice(line.lineTotal) }} Ar</template>
              </span>
            </li>
          </ul>
        </article>
      </div>

      <div class="flex justify-center py-8">
        <button
          v-if="hasMore && !loading"
          type="button"
          class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-800 hover:bg-gray-50"
          @click="loadMore"
        >
          Charger plus
        </button>
        <div v-if="loading && orders.length > 0" class="w-8 h-8 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        <p v-if="!hasMore && orders.length > 0" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Fin de la liste</p>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useUIStore } from '../../stores/useUIStore';
// Données via mz_eroso_v2 GET …/mz_eroso/order_commande/list (voir OrderCommandeApiController).
import { getOrderCommandeList } from '../../services/api';
import {
  normalizeOrderRow,
  statusLabel,
  statusPillClass,
  formatOrderCommandeDate,
  formatOrderCommandePrice,
  STATUS_ORDER,
  STATUS_LABELS,
} from './orderCommandeShared';

const ITEMS_PER_PAGE = 20;

const uiStore = useUIStore();
const router = useRouter();

const statusFilters = [
  { value: '', label: 'Tous' },
  ...STATUS_ORDER.map((value) => ({
    value,
    label: STATUS_LABELS[value] || value,
  })),
];

const orders = ref([]);
const loading = ref(false);
const listError = ref(null);
const hasMore = ref(true);
const currentPage = ref(0);
const searchQuery = ref('');
const statusFilter = ref('');
/** Y-m-d pour filtre created côté API (timezone site). */
const dateFrom = ref('');
const dateTo = ref('');
let searchTimer = null;

function openOrder(nid) {
  if (nid == null || nid === '') return;
  router.push(`/sur-commande/order/${nid}`);
}

function buildListParams() {
  let params = `sort[val]=created&sort[op]=DESC&offset=${ITEMS_PER_PAGE}&pager=${currentPage.value}`;
  const q = searchQuery.value.trim();
  if (q.length >= 2) {
    params += `&search=${encodeURIComponent(q)}`;
  }
  const st = statusFilter.value;
  if (st) {
    params += `&filters[field_status_commande][val]=${encodeURIComponent(st)}`;
  }
  if (dateFrom.value) {
    params += `&date_from=${encodeURIComponent(dateFrom.value)}`;
  }
  if (dateTo.value) {
    params += `&date_to=${encodeURIComponent(dateTo.value)}`;
  }
  return params;
}

function clearDates() {
  dateFrom.value = '';
  dateTo.value = '';
}

async function loadOrders(append = false) {
  if (!append) {
    currentPage.value = 0;
    hasMore.value = true;
  }
  if (!hasMore.value && append) return;

  loading.value = true;
  listError.value = null;
  try {
    const response = await getOrderCommandeList(buildListParams());
    let raw = response.data?.rows;
    if (raw == null) {
      raw = response.data;
    }
    const list = Array.isArray(raw)
      ? raw
      : raw && typeof raw === 'object'
        ? Object.values(raw)
        : [];
    const rows = list.map(normalizeOrderRow).filter((r) => r.nid != null && r.nid !== '');

    if (append) {
      orders.value = [...orders.value, ...rows];
    } else {
      orders.value = rows;
    }

    if (rows.length < ITEMS_PER_PAGE) {
      hasMore.value = false;
    } else {
      currentPage.value += 1;
    }
  } catch (e) {
    listError.value = e;
    if (!append) orders.value = [];
    hasMore.value = false;
    console.error('order_commande list:', e);
  } finally {
    loading.value = false;
  }
}

function loadMore() {
  loadOrders(true);
}

const formatPrice = formatOrderCommandePrice;
const formatDate = formatOrderCommandeDate;

onMounted(() => {
  loadOrders(false);
});

watch(searchQuery, () => {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    loadOrders(false);
  }, 400);
});

watch(statusFilter, () => {
  loadOrders(false);
});

watch([dateFrom, dateTo], () => {
  loadOrders(false);
});
</script>
