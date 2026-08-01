<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <main class="lg:ml-64">
      <div class="sticky top-0 z-40 bg-white border-b border-gray-200 px-4 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <button type="button" class="lg:hidden" @click="uiStore.toggleSidebar">
              <i class="ri-menu-line text-2xl text-gray-600"></i>
            </button>
            <div>
              <h1 class="text-xl font-bold text-gray-900">Livraisons</h1>
              <p class="text-xs text-gray-500">Commandes en envoi livreur</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="hidden sm:inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-sky-100 text-sky-800">
              {{ orders.length }} / {{ totalOrders }}
            </span>
            <button
              type="button"
              class="flex items-center space-x-2 bg-sky-600 text-white px-3 py-2 rounded-lg hover:bg-sky-700 transition-colors text-sm font-semibold"
              :disabled="loading"
              @click="fetchOrders(false)"
            >
              <i :class="['ri-refresh-line', loading ? 'animate-spin' : '']"></i>
              <span class="hidden sm:inline">Actualiser</span>
            </button>
          </div>
        </div>
      </div>

      <div class="p-4 space-y-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
          <div class="relative">
            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Rechercher titre, notes, client…"
              class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500"
            >
          </div>
          <router-link
            to="/commandes"
            class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-gray-500 hover:text-sky-700"
          >
            <i class="ri-arrow-left-line"></i>
            Toutes les ventes
          </router-link>
        </div>

        <div v-if="loading && orders.length === 0" class="flex flex-col items-center justify-center py-20 space-y-4">
          <div class="w-12 h-12 border-4 border-sky-600 border-t-transparent rounded-full animate-spin"></div>
          <p class="text-sm text-gray-500 font-medium">Chargement des livraisons…</p>
        </div>

        <div v-else-if="filteredOrders.length === 0" class="bg-white rounded-2xl border border-gray-100 p-10 text-center">
          <i class="ri-truck-line text-5xl text-gray-300 mb-3"></i>
          <p class="text-sm font-medium text-gray-700">Aucune commande en envoi livreur</p>
          <p class="text-xs text-gray-500 mt-1">Les ventes passées en « Envoi Livreur » apparaîtront ici.</p>
        </div>

        <div v-else class="space-y-3">
          <article
            v-for="order in filteredOrders"
            :key="order.nid"
            class="bg-white rounded-2xl border border-sky-100 shadow-sm overflow-hidden"
          >
            <div
              class="p-4 bg-sky-50/50 cursor-pointer"
              @click="openOrder(order)"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="text-sm font-bold text-gray-900 truncate">{{ order.title }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-sky-100 text-sky-800">
                      Envoi Livreur
                    </span>
                  </div>
                  <p class="text-xs text-gray-600 flex items-center gap-1">
                    <i class="ri-user-line text-gray-400"></i>
                    {{ order.uid?.name || 'N/A' }}
                  </p>
                  <p v-if="rawOrderNotes(order)" class="text-xs text-gray-500 mt-1 line-clamp-2">
                    <i class="ri-map-pin-line text-sky-500"></i>
                    {{ rawOrderNotes(order) }}
                  </p>
                  <p class="text-[10px] text-gray-400 mt-1">
                    {{ formatOrderLocalDate(order.field_date || order.created) }}
                    · #{{ order.nid }}
                  </p>
                  <div v-if="getOrderCarts(order).length" class="flex flex-wrap gap-1 mt-2">
                    <span
                      v-for="cart in getOrderCarts(order)"
                      :key="cart.nid || cart"
                      class="inline-flex items-center gap-1 pl-0.5 pr-2 py-0.5 bg-white text-sky-800 rounded text-xs font-medium border border-sky-100"
                    >
                      <span class="w-5 h-5 rounded overflow-hidden shrink-0 bg-gray-100">
                        <img
                          v-if="getCartImage(cart)"
                          :src="getCartImage(cart)"
                          :alt="cart.title || 'Article'"
                          class="w-full h-full object-cover"
                          loading="lazy"
                          @error="onCartImageError(cart)"
                        >
                      </span>
                      <span class="truncate max-w-[8rem]">{{ cart.title || 'Article' }}</span>
                      <span v-if="cart.field_quantite" class="font-bold">×{{ cart.field_quantite }}</span>
                    </span>
                  </div>
                </div>
                <div class="text-right shrink-0">
                  <p class="text-lg font-black text-sky-700">{{ formatOrderLocalPrice(order.field_total) }} Ar</p>
                  <p class="text-[10px] text-gray-500">{{ getOrderCartsCount(order) }} article(s)</p>
                </div>
              </div>
            </div>

            <div v-if="canChangeStatus" class="px-4 py-3 border-t border-sky-100 flex flex-wrap gap-2 bg-white">
              <button
                type="button"
                class="flex-1 min-w-[7rem] px-3 py-2.5 rounded-xl text-xs font-bold bg-green-600 text-white hover:bg-green-700 disabled:opacity-50"
                :disabled="savingNid === order.nid"
                @click.stop="markStatus(order, 'livre_p')"
              >
                <i v-if="savingNid === order.nid" class="ri-loader-4-line animate-spin mr-1"></i>
                Livré P
              </button>
              <button
                type="button"
                class="flex-1 min-w-[7rem] px-3 py-2.5 rounded-xl text-xs font-bold bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50"
                :disabled="savingNid === order.nid"
                @click.stop="markStatus(order, 'livre_np')"
              >
                Livré NP
              </button>
              <button
                type="button"
                class="px-3 py-2.5 rounded-xl text-xs font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 disabled:opacity-50"
                :disabled="savingNid === order.nid"
                @click.stop="markStatus(order, 'sortie')"
              >
                Retour sortie
              </button>
              <button
                v-if="canChangeStatus"
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
          <div class="w-6 h-6 border-2 border-sky-600 border-t-transparent rounded-full animate-spin"></div>
        </div>
      </div>
    </main>

    <!-- Détail -->
    <div
      v-if="selectedOrder"
      class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      @click.self="selectedOrder = null"
    >
      <div class="bg-white rounded-t-2xl sm:rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-4 py-3 flex items-center justify-between">
          <h2 class="text-base font-bold text-gray-900">{{ selectedOrder.title }}</h2>
          <button type="button" class="text-gray-400" @click="selectedOrder = null">
            <i class="ri-close-line text-2xl"></i>
          </button>
        </div>
        <div class="p-4 space-y-4">
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span class="text-[10px] font-bold text-gray-400 uppercase">Date</span>
              <p>{{ formatOrderLocalDate(selectedOrder.field_date || selectedOrder.created) }}</p>
            </div>
            <div>
              <span class="text-[10px] font-bold text-gray-400 uppercase">Total</span>
              <p class="font-black text-sky-700">{{ formatOrderLocalPrice(selectedOrder.field_total) }} Ar</p>
            </div>
          </div>
          <div v-if="rawOrderNotes(selectedOrder)">
            <span class="text-[10px] font-bold text-gray-400 uppercase">Notes / adresse</span>
            <p class="text-sm text-gray-700 mt-1 bg-gray-50 rounded-lg p-3 whitespace-pre-wrap">{{ rawOrderNotes(selectedOrder) }}</p>
          </div>
          <div v-if="getOrderCarts(selectedOrder).length">
            <span class="text-[10px] font-bold text-gray-400 uppercase block mb-2">Articles</span>
            <div class="space-y-2">
              <div
                v-for="cart in getOrderCarts(selectedOrder)"
                :key="cart.nid || cart"
                class="flex gap-3 bg-gray-50 rounded-xl p-3"
              >
                <div class="w-12 h-12 rounded-lg overflow-hidden bg-white border shrink-0">
                  <img
                    v-if="getCartImage(cart)"
                    :src="getCartImage(cart)"
                    class="w-full h-full object-cover"
                    alt=""
                    @error="onCartImageError(cart)"
                  >
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold truncate">{{ cart.title || 'Article' }}</p>
                  <p class="text-xs text-gray-500">Qté {{ cart.field_quantite || '?' }}</p>
                </div>
                <p v-if="cart.field_total" class="text-sm font-bold text-sky-700 shrink-0">
                  {{ formatOrderLocalPrice(cart.field_total) }}
                </p>
              </div>
            </div>
          </div>
          <div v-if="canChangeStatus" class="flex flex-col gap-2 pt-2">
            <button
              type="button"
              class="w-full py-3 rounded-xl font-bold bg-green-600 text-white hover:bg-green-700 disabled:opacity-50"
              :disabled="savingNid === selectedOrder.nid"
              @click="markStatus(selectedOrder, 'livre_p')"
            >
              Livré P
            </button>
            <button
              type="button"
              class="w-full py-3 rounded-xl font-bold bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50"
              :disabled="savingNid === selectedOrder.nid"
              @click="markStatus(selectedOrder, 'livre_np')"
            >
              Livré NP
            </button>
            <button
              type="button"
              class="w-full py-3 rounded-xl font-semibold bg-gray-100 text-gray-800 hover:bg-gray-200 disabled:opacity-50"
              :disabled="savingNid === selectedOrder.nid"
              @click="selectedOrder = null"
            >
              Fermer
            </button>
          </div>
        </div>
      </div>
    </div>

    <BottomNav />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useUIStore } from '../stores/useUIStore';
import { useProductStore } from '../stores/useProductStore';
import {
  getOrderLocalList,
  updateOrderLocalStatus,
  cancelOrderLocal,
} from '../services/api';
import {
  rawOrderNotes,
  getOrderCarts,
  getOrderCartsCount,
  formatOrderLocalPrice,
  formatOrderLocalDate,
  useOrderLocalCartImages,
  ORDER_LOCAL_LIVRAISON_STATUS,
} from './orderLocalShared';
import BottomNav from '../components/BottomNav.vue';

const STATUS_LIVRAISON = ORDER_LOCAL_LIVRAISON_STATUS;
const PAGE_SIZE = 15;

const uiStore = useUIStore();
const productStore = useProductStore();
const { getCartImage, onCartImageError, resolveCartImagesFor } = useOrderLocalCartImages();

const orders = ref([]);
const totalOrders = ref(0);
const searchQuery = ref('');
const loading = ref(false);
const loadingMore = ref(false);
const currentPage = ref(0);
const hasMore = ref(true);
const selectedOrder = ref(null);
const savingNid = ref(null);
const scrollSentinel = ref(null);
let observer = null;

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
const canChangeStatus = computed(() => isAdmin.value || isContentEditor.value);

const filteredOrders = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return orders.value;
  return orders.value.filter((order) => {
    const notes = rawOrderNotes(order).toLowerCase();
    return (
      (order.title && order.title.toLowerCase().includes(q)) ||
      (notes && notes.includes(q)) ||
      (order.uid?.name && order.uid.name.toLowerCase().includes(q))
    );
  });
});

function buildListParams() {
  let params = `offset=${PAGE_SIZE}&pager=${currentPage.value}&sort[val]=created&sort[op]=DESC`;
  params += `&filters[field_status_local][val]=${encodeURIComponent(STATUS_LIVRAISON)}&filters[field_status_local][op]==`;
  return params;
}

function openOrder(order) {
  selectedOrder.value = order;
  resolveCartImagesFor(order);
}

function removeOrder(nid) {
  orders.value = orders.value.filter((o) => String(o.nid) !== String(nid));
  totalOrders.value = Math.max(0, totalOrders.value - 1);
  if (selectedOrder.value && String(selectedOrder.value.nid) === String(nid)) {
    selectedOrder.value = null;
  }
}

async function markStatus(order, newStatus) {
  if (!order || savingNid.value) return;
  const nid = order.nid;
  if (nid == null) return;

  if (newStatus === 'annuler') {
    const ok = window.confirm("Confirmer l'annulation ? Le stock sera remis à disposition.");
    if (!ok) return;
  }

  savingNid.value = nid;
  try {
    const token = localStorage.getItem('token') || '';
    if (newStatus === 'annuler') {
      const res = await cancelOrderLocal({ nid, token });
      if (!res?.data?.status) throw new Error(res?.data?.message || 'Erreur annulation.');
    } else {
      const res = await updateOrderLocalStatus({ nid, status: newStatus, token });
      if (!res?.data?.status) throw new Error(res?.data?.message || 'Erreur mise à jour.');
    }
    removeOrder(nid);
    await productStore.fetchProducts(false, {});
  } catch (e) {
    alert(e?.message || 'Erreur lors de la mise à jour.');
  } finally {
    savingNid.value = null;
  }
}

async function fetchOrders(append = false) {
  if (!append) {
    loading.value = true;
    currentPage.value = 0;
    hasMore.value = true;
  } else {
    loadingMore.value = true;
  }

  try {
    const response = await getOrderLocalList(buildListParams());
    const rows = response.data?.rows || [];
    totalOrders.value = response.data?.total ?? rows.length;

    if (append) {
      orders.value = [...orders.value, ...rows];
    } else {
      orders.value = rows;
    }

    hasMore.value = orders.value.length < totalOrders.value;
    const targets = append ? rows : orders.value;
    targets.forEach((order) => resolveCartImagesFor(order));
  } catch (e) {
    console.error('livraison list:', e);
    if (!append) orders.value = [];
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

function loadMore() {
  if (loadingMore.value || !hasMore.value) return;
  currentPage.value++;
  fetchOrders(true);
}

function setupObserver() {
  if (observer) observer.disconnect();
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0]?.isIntersecting && hasMore.value && !loadingMore.value && !loading.value) {
        loadMore();
      }
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
});

watch(
  () => filteredOrders.value,
  (list) => {
    list.forEach((order) => resolveCartImagesFor(order));
  },
);
</script>
