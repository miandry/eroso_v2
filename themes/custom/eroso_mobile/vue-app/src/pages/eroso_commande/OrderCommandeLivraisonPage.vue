<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64 border-b border-orange-100">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button type="button" class="p-1 -ml-1 text-gray-600 cursor-pointer lg:hidden" @click="uiStore.toggleSidebar">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <div>
            <h1 class="text-lg font-semibold text-gray-900">En livraison</h1>
            <p class="text-[10px] text-orange-700 font-medium">Commandes sur commande</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="hidden sm:inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-900">
            {{ orders.length }} / {{ totalOrders }}
          </span>
          <button
            type="button"
            class="w-8 h-8 flex items-center justify-center text-orange-700 bg-orange-50 rounded-lg"
            :disabled="loading"
            @click="fetchOrders(false)"
          >
            <i class="ri-refresh-line" :class="loading ? 'animate-spin' : ''"></i>
          </button>
        </div>
      </div>
    </nav>

    <main class="pt-16 pb-24 px-4 lg:ml-64">
      <div class="mb-4 bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <div class="relative">
          <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Titre, notes, client, produit… (2 car. min.)"
            class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500"
          >
        </div>
        <router-link
          to="/sur-commande/orders"
          class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-gray-500 hover:text-orange-700"
        >
          <i class="ri-arrow-left-line"></i>
          Toutes les commandes
        </router-link>
      </div>

      <div v-if="listError" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        Impossible de charger les livraisons.
      </div>

      <div v-if="loading && orders.length === 0" class="flex flex-col items-center justify-center py-20 space-y-4">
        <div class="w-12 h-12 border-4 border-orange-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-gray-500 font-medium">Chargement…</p>
      </div>

      <div v-else-if="orders.length === 0" class="bg-white rounded-2xl border border-gray-100 p-10 text-center">
        <i class="ri-truck-line text-5xl text-gray-300 mb-3"></i>
        <p class="text-sm font-medium text-gray-700">Aucune commande en livraison</p>
        <p class="text-xs text-gray-500 mt-1">Les commandes au statut « En livraison » apparaîtront ici.</p>
      </div>

      <div v-else class="space-y-3">
        <article
          v-for="order in orders"
          :key="order.nid"
          class="bg-white rounded-2xl border border-orange-100 shadow-sm overflow-hidden"
        >
          <div
            class="p-4 bg-orange-50/40 cursor-pointer"
            role="button"
            tabindex="0"
            @click="openOrder(order.nid)"
            @keydown.enter.prevent="openOrder(order.nid)"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                  <span class="text-sm font-bold text-gray-900 truncate">{{ order.title }}</span>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-orange-100 text-orange-950 ring-1 ring-orange-200/80">
                    En livraison
                  </span>
                </div>
                <p v-if="order.clientLabel" class="text-xs text-gray-600 flex items-center gap-1">
                  <i class="ri-user-line text-gray-400"></i>
                  {{ order.clientLabel }}
                </p>
                <p v-if="order.infoPreview" class="text-xs text-gray-500 mt-1 line-clamp-2">
                  <i class="ri-sticky-note-line text-orange-500"></i>
                  {{ order.infoPreview }}
                </p>
                <p class="text-[10px] text-gray-400 mt-1">
                  {{ formatDate(order.created) }}
                  · <span class="font-mono text-gray-500">#{{ order.nid }}</span>
                </p>
                <ul
                  v-if="order.cartLines && order.cartLines.length"
                  class="mt-2 space-y-1"
                >
                  <li
                    v-for="(line, idx) in order.cartLines.slice(0, 4)"
                    :key="line.nid || idx"
                    class="text-[11px] text-gray-700 flex items-center gap-2"
                  >
                    <span class="truncate flex-1">{{ line.productTitle || line.title }}</span>
                    <span v-if="line.qty != null" class="tabular-nums text-gray-500 shrink-0">×{{ line.qty }}</span>
                    <span
                      v-if="line.cartStatus"
                      :class="['shrink-0 px-1.5 py-0.5 rounded text-[9px] font-bold', cartStatusPillClass(line.cartStatus)]"
                    >
                      {{ cartStatusLabel(line.cartStatus) }}
                    </span>
                  </li>
                  <li v-if="order.cartLines.length > 4" class="text-[10px] text-gray-400">
                    + {{ order.cartLines.length - 4 }} autre(s) ligne(s)
                  </li>
                </ul>
              </div>
              <div class="text-right shrink-0">
                <p class="text-lg font-black text-orange-700">{{ formatPrice(order.total) }} Ar</p>
                <p class="text-[10px] text-gray-500">{{ order.cartLines?.length || 0 }} ligne(s)</p>
              </div>
            </div>
          </div>

          <div v-if="showActions" class="px-4 py-3 border-t border-orange-100 flex flex-wrap gap-2 bg-white">
            <button
              v-if="isAdmin"
              type="button"
              class="flex-1 min-w-[7rem] px-3 py-2.5 rounded-xl text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50"
              :disabled="savingNid === order.nid"
              @click.stop="markStatus(order, 'payer_recue')"
            >
              <i v-if="savingNid === order.nid" class="ri-loader-4-line animate-spin mr-1"></i>
              Payé reçu
            </button>
            <button
              type="button"
              class="px-3 py-2.5 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
              @click.stop="openOrder(order.nid)"
            >
              Détail
            </button>
            <button
              type="button"
              class="px-3 py-2.5 rounded-xl text-xs font-bold bg-red-50 text-red-700 hover:bg-red-100 disabled:opacity-50"
              :disabled="savingNid === order.nid"
              @click.stop="markStatus(order, 'annuler')"
            >
              Annuler
            </button>
          </div>
        </article>
      </div>

      <div ref="scrollSentinel" class="h-1"></div>
      <div v-if="loadingMore" class="flex justify-center py-4">
        <div class="w-6 h-6 border-2 border-orange-600 border-t-transparent rounded-full animate-spin"></div>
      </div>
      <p v-if="!hasMore && orders.length > 0" class="text-center text-[10px] font-bold text-gray-400 uppercase tracking-widest py-4">
        Fin de la liste
      </p>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useUIStore } from '../../stores/useUIStore';
import { getOrderCommandeList, saveItem } from '../../services/api';
import {
  normalizeOrderRow,
  formatOrderCommandeDate,
  formatOrderCommandePrice,
  cartStatusLabel,
  cartStatusPillClass,
  canApplyStatusTransition,
  STATUS_ANNULER,
} from './orderCommandeShared';

const STATUS_LIVRAISON = 'en_livraison';
const BUNDLE = 'order_commande';
const PAGE_SIZE = 15;

const uiStore = useUIStore();
const router = useRouter();

const orders = ref([]);
const totalOrders = ref(0);
const searchQuery = ref('');
const loading = ref(false);
const loadingMore = ref(false);
const listError = ref(false);
const currentPage = ref(0);
const hasMore = ref(true);
const savingNid = ref(null);
const scrollSentinel = ref(null);
let observer = null;
let searchTimer = null;

const getUserRoles = () => {
  try {
    const roles = JSON.parse(localStorage.getItem('roles') || '[]');
    return Array.isArray(roles) ? roles : [];
  } catch {
    return [];
  }
};

const isAdmin = computed(() => getUserRoles().includes('administrator'));
const isContentEditor = computed(() => getUserRoles().includes('content_editor'));
const showActions = computed(() => isAdmin.value || isContentEditor.value);

const formatPrice = formatOrderCommandePrice;
const formatDate = formatOrderCommandeDate;

function buildListParams() {
  let params = `offset=${PAGE_SIZE}&pager=${currentPage.value}&sort[val]=created&sort[op]=DESC`;
  params += `&filters[field_status_commande][val]=${encodeURIComponent(STATUS_LIVRAISON)}`;
  const q = searchQuery.value.trim();
  if (q.length >= 2) {
    params += `&search=${encodeURIComponent(q)}`;
  }
  return params;
}

function openOrder(nid) {
  if (nid == null) return;
  router.push(`/sur-commande/order/${nid}`);
}

function removeOrder(nid) {
  orders.value = orders.value.filter((o) => String(o.nid) !== String(nid));
  totalOrders.value = Math.max(0, totalOrders.value - 1);
}

async function markStatus(order, nextStatus) {
  if (!order || savingNid.value) return;
  const nid = order.nid;
  if (nid == null) return;

  if (!canApplyStatusTransition(order.status, nextStatus, isAdmin.value)) {
    if (nextStatus === 'payer_recue' && !isAdmin.value) {
      alert('Seuls les administrateurs peuvent confirmer le paiement reçu.');
    } else {
      alert('Cette transition n’est pas autorisée.');
    }
    return;
  }

  if (nextStatus === STATUS_ANNULER) {
    const ok = window.confirm('Passer cette commande au statut « Annuler » ?');
    if (!ok) return;
  }

  savingNid.value = nid;
  try {
    const res = await saveItem({
      entity_type: 'node',
      bundle: BUNDLE,
      nid,
      field_status_commande: nextStatus,
    });
    if (res.data?.status !== true) {
      throw new Error(res.data?.message || 'Échec de la mise à jour.');
    }
    removeOrder(nid);
  } catch (e) {
    alert(e?.response?.data?.message || e?.message || 'Erreur lors de la mise à jour.');
  } finally {
    savingNid.value = null;
  }
}

async function fetchOrders(append = false) {
  if (!append) {
    loading.value = true;
    currentPage.value = 0;
    hasMore.value = true;
    listError.value = false;
  } else {
    loadingMore.value = true;
  }

  try {
    const response = await getOrderCommandeList(buildListParams());
    let raw = response.data?.rows;
    if (raw == null) raw = response.data;
    const list = Array.isArray(raw) ? raw : raw && typeof raw === 'object' ? Object.values(raw) : [];
    const rows = list.map(normalizeOrderRow).filter((r) => r.nid != null);

    totalOrders.value = response.data?.total ?? rows.length;

    if (append) {
      orders.value = [...orders.value, ...rows];
    } else {
      orders.value = rows;
    }

    hasMore.value = orders.value.length < totalOrders.value;
    if (append && rows.length === 0) {
      hasMore.value = false;
    }
  } catch (e) {
    console.error('order_commande livraison list:', e);
    listError.value = true;
    if (!append) orders.value = [];
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

function loadMore() {
  if (loadingMore.value || !hasMore.value || loading.value) return;
  currentPage.value += 1;
  fetchOrders(true);
}

function setupObserver() {
  if (observer) observer.disconnect();
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0]?.isIntersecting) loadMore();
    },
    { rootMargin: '200px' },
  );
  nextTick(() => {
    if (scrollSentinel.value) observer.observe(scrollSentinel.value);
  });
}

onMounted(async () => {
  await fetchOrders(false);
  setupObserver();
});

onUnmounted(() => {
  if (observer) observer.disconnect();
  if (searchTimer) clearTimeout(searchTimer);
});

watch(searchQuery, () => {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => fetchOrders(false), 400);
});
</script>
