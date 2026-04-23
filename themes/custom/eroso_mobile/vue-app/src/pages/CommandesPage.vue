<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <main class="lg:ml-64">
      <div class="sticky top-0 z-40 bg-white border-b border-gray-200 px-4 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <button @click="uiStore.toggleSidebar" class="lg:hidden">
              <i class="ri-menu-line text-2xl text-gray-600"></i>
            </button>
            <div>
              <h1 class="text-xl font-bold text-gray-900">Ventes locales</h1>
              <p class="text-xs text-gray-500">Historique des ventes locales</p>
            </div>
          </div>
          <button 
            @click="fetchOrders"
            :disabled="loading"
            class="flex items-center space-x-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
          >
            <i :class="['ri-refresh-line text-lg', loading ? 'animate-spin' : '']"></i>
            <span class="text-sm font-semibold">Actualiser</span>
          </button>
        </div>
      </div>

      <div class="p-4 space-y-4">
        <!-- Filter Tabs -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
          <div class="flex items-center space-x-2 overflow-x-auto">
            <button
              v-for="status in orderStatuses"
              :key="status.value"
              @click="selectedStatus = status.value"
              :class="[
                'px-4 py-2 rounded-lg text-sm font-semibold whitespace-nowrap transition-colors',
                selectedStatus === status.value
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ status.label }}
              <span class="ml-1 text-xs opacity-75">({{ getOrderCountByStatus(status.value) }})</span>
            </button>
          </div>

          <div class="mt-4 flex flex-wrap items-center gap-2">
            <button
              v-for="period in dateFilters"
              :key="period.value"
              @click="selectedDateFilter = period.value"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors',
                selectedDateFilter === period.value
                  ? 'bg-indigo-600 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ period.label }}
            </button>

            <input
              v-if="selectedDateFilter === 'custom'"
              v-model="selectedDate"
              type="date"
              class="px-3 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-gray-500 uppercase">Total</span>
              <i class="ri-shopping-bag-line text-blue-600 text-lg"></i>
            </div>
            <div class="text-2xl font-black text-gray-900">{{ orders.length }}</div>
            <div class="text-xs text-gray-500 mt-1">Ventes</div>
          </div>

          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-orange-500 uppercase">En cours</span>
              <i class="ri-time-line text-orange-600 text-lg"></i>
            </div>
            <div class="text-2xl font-black text-orange-600">{{ getOrderCountByStatus('en_cours') }}</div>
            <div class="text-xs text-gray-500 mt-1">À traiter</div>
          </div>

          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-green-500 uppercase">Payées</span>
              <i class="ri-checkbox-circle-line text-green-600 text-lg"></i>
            </div>
            <div class="text-2xl font-black text-green-600">{{ getOrderCountByStatus('payer') }}</div>
            <div class="text-xs text-gray-500 mt-1">Complétées</div>
          </div>

          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-blue-500 uppercase">Revenu</span>
              <i class="ri-money-dollar-circle-line text-blue-600 text-lg"></i>
            </div>
            <div class="text-xl font-black text-blue-600">{{ formatPrice(totalRevenue) }}</div>
            <div class="text-xs text-gray-500 mt-1">Ar</div>
          </div>
        </div>

        <!-- Loading State (initial) -->
        <div v-if="loading && orders.length === 0" class="flex flex-col items-center justify-center py-20 space-y-4">
          <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
          <p class="text-sm text-gray-500 font-medium">Chargement des ventes...</p>
        </div>

        <!-- Orders List -->
        <div v-else class="bg-white rounded-2xl shadow-sm border border-gray-100">
          <div class="p-4 border-b border-gray-100">
            <div class="flex items-center justify-between">
              <h3 class="text-base font-bold text-gray-900">Liste des ventes</h3>
              <div class="flex items-center space-x-2">
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Rechercher..."
                  class="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>
          </div>

          <div v-if="filteredOrders.length === 0" class="p-8 text-center">
            <i class="ri-shopping-bag-line text-4xl text-gray-300 mb-2"></i>
            <p class="text-sm text-gray-500">Aucune vente trouvée</p>
          </div>

          <div v-else class="divide-y divide-gray-100">
            <div
              v-for="order in filteredOrders"
              :key="order.nid"
              @click="viewOrderDetails(order)"
              :class="[
                'p-4 transition-colors cursor-pointer',
                getStatusBg(getStatus(order.field_status_local)),
              ]"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center space-x-2 mb-2">
                    <span class="text-sm font-bold text-gray-900">{{ order.title }}</span>
                    <span
                      v-if="getStatus(order.field_status_commande)"
                      :class="[
                        'px-2 py-1 rounded-full text-xs font-semibold',
                        getStatusClass(getStatus(order.field_status_commande))
                      ]"
                    >
                      {{ getStatusLabel(getStatus(order.field_status_commande)) }}
                    </span>
                  </div>
                  
                  <div class="flex items-center space-x-2 mb-1">
                    <i class="ri-user-line text-gray-400 text-sm"></i>
                    <span class="text-sm text-gray-700">{{ order.uid?.name || 'N/A' }}</span>
                  </div>
                  
                  <div v-if="order.field_info" class="flex items-center space-x-2 mb-1">
                    <i class="ri-file-text-line text-gray-400 text-sm"></i>
                    <span class="text-sm text-gray-600 line-clamp-1">{{ order.field_info }}</span>
                  </div>
                  
                  <div class="flex items-center space-x-2 mb-2">
                    <i class="ri-calendar-line text-gray-400 text-sm"></i>
                    <span class="text-xs text-gray-500">{{ formatDate(order.field_date || order.created) }}</span>
                  </div>

                  <!-- Product list -->
                  <div v-if="getCarts(order).length > 0" class="flex flex-wrap gap-1">
                    <span
                      v-for="cart in getCarts(order)"
                      :key="cart.nid || cart"
                      class="inline-flex items-center gap-1 pl-0.5 pr-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs font-medium"
                    >
                      <span class="w-5 h-5 rounded bg-white/70 border border-blue-100 overflow-hidden flex items-center justify-center shrink-0">
                        <img
                          v-if="getCartImage(cart)"
                          :src="getCartImage(cart)"
                          :alt="cart.title || 'Article'"
                          class="w-full h-full object-cover"
                          loading="lazy"
                          @error="onCartImageError(cart)"
                        />
                        <i v-else class="ri-image-2-line text-blue-300 text-[11px]"></i>
                      </span>
                      <span>{{ cart.title || 'Article' }}</span>
                      <span v-if="cart.field_quantite" class="font-bold">x{{ cart.field_quantite }}</span>
                    </span>
                  </div>
                </div>

                <div class="text-right ml-4">
                  <div class="text-lg font-bold text-blue-600">{{ formatPrice(order.field_total) }} Ar</div>
                  <div class="text-xs text-gray-500 mt-1">
                    {{ getCartsCount(order) }} article(s)
                  </div>
                  <span
                    v-if="getStatus(order.field_status_local)"
                    :class="[
                      'mt-2 inline-block px-2 py-1 rounded-full text-xs font-semibold',
                      getStatusClass(getStatus(order.field_status_local))
                    ]"
                  >
                    {{ getStatusLabel(getStatus(order.field_status_local)) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Infinite Scroll Sentinel -->
          <div ref="scrollSentinel" class="h-1"></div>

          <!-- Loading More -->
          <div v-if="loadingMore" class="flex items-center justify-center py-4">
            <div class="w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full animate-spin mr-2"></div>
            <span class="text-sm text-gray-500">Chargement...</span>
          </div>

          <!-- No More Data -->
          <div v-if="!hasMore && orders.length > 0 && !loadingMore" class="text-center py-4">
            <span class="text-xs text-gray-400">Toutes les ventes sont affichées</span>
          </div>
        </div>
      </div>
    </main>

    <!-- Order Detail Modal -->
    <div
      v-if="selectedOrder"
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
      @click.self="selectedOrder = null"
    >
      <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
          <h2 class="text-lg font-bold text-gray-900">{{ selectedOrder.title }}</h2>
          <button @click="selectedOrder = null" class="text-gray-400 hover:text-gray-600">
            <i class="ri-close-line text-2xl"></i>
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <span class="text-xs font-semibold text-gray-500 uppercase">Date</span>
              <p class="text-sm text-gray-900 mt-1">{{ formatDate(selectedOrder.field_date || selectedOrder.created) }}</p>
            </div>
            <div>
              <span class="text-xs font-semibold text-gray-500 uppercase">Auteur</span>
              <p class="text-sm text-gray-900 mt-1">{{ selectedOrder.uid?.name || 'N/A' }}</p>
            </div>
            <div>
              <span class="text-xs font-semibold text-gray-500 uppercase">Statut</span>
              <p class="mt-1">
                <span :class="['mt-2 inline-block px-2 py-1 rounded-full text-xs font-semibold', getStatusClass(getStatus(selectedOrder.field_status_local))]">
                  {{ getStatusLabel(getStatus(selectedOrder.field_status_local)) }}
                </span>
              </p>
            </div>
            <div>
              <span class="text-xs font-semibold text-gray-500 uppercase">Total</span>
              <p class="text-lg font-bold text-blue-600 mt-1">{{ formatPrice(selectedOrder.field_total) }} Ar</p>
            </div>
          </div>

          <div v-if="selectedOrder.field_info">
            <span class="text-xs font-semibold text-gray-500 uppercase">Notes</span>
            <p class="text-sm text-gray-700 mt-1 bg-gray-50 rounded-lg p-3">{{ selectedOrder.field_info }}</p>
          </div>

          <!-- Cart Items -->
          <div v-if="getCarts(selectedOrder).length > 0">
            <span class="text-xs font-semibold text-gray-500 uppercase mb-2 block">Articles</span>
            <div class="space-y-2">
              <div
                v-for="cart in getCarts(selectedOrder)"
                :key="cart.nid || cart"
                class="flex items-start gap-3 bg-gray-50 rounded-lg p-3"
              >
                <div class="shrink-0 w-14 h-14 rounded-lg bg-white border border-gray-200 overflow-hidden flex items-center justify-center">
                  <img
                    v-if="getCartImage(cart)"
                    :src="getCartImage(cart)"
                    :alt="cart.title || 'Article'"
                    class="w-full h-full object-cover"
                    loading="lazy"
                    @error="onCartImageError(cart)"
                  />
                  <i v-else class="ri-image-2-line text-gray-400 text-xl"></i>
                </div>

                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-gray-900 truncate">{{ cart.title || 'Article #' + (cart.nid || cart) }}</p>
                  <p v-if="cart.field_quantite" class="text-xs text-gray-500">Qté: {{ cart.field_quantite }}</p>

                  <!-- Admin inline price editor -->
                  <div
                    v-if="editingCartNid === (cart.nid || cart.id || cart.target_id)"
                    class="mt-2 space-y-1"
                  >
                    <div class="flex items-center gap-2">
                      <input
                        v-model="editingCartPrice"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="Prix unitaire"
                        class="w-28 px-2 py-1 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @keyup.enter="saveCartPrice(cart)"
                        @keyup.esc="cancelEditCartPrice"
                      />
                      <span class="text-xs text-gray-500">Ar/u</span>
                      <button
                        @click="saveCartPrice(cart)"
                        :disabled="savingCartPrice"
                        class="px-2 py-1 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-blue-400"
                      >
                        <i v-if="savingCartPrice" class="ri-loader-4-line animate-spin"></i>
                        <span v-else>Enregistrer</span>
                      </button>
                      <button
                        @click="cancelEditCartPrice"
                        :disabled="savingCartPrice"
                        class="px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                      >
                        Annuler
                      </button>
                    </div>
                    <p v-if="cartPriceError" class="text-xs text-red-600">{{ cartPriceError }}</p>
                  </div>
                </div>

                <div class="text-right shrink-0">
                  <p v-if="cart.field_total" class="text-sm font-bold text-blue-600">{{ formatPrice(cart.field_total) }} Ar</p>
                  <p v-if="cart.field_prix_unitaire" class="text-xs text-gray-500">{{ formatPrice(cart.field_prix_unitaire) }} Ar/u</p>
                  <button
                    v-if="canEditCartPrice && editingCartNid !== (cart.nid || cart.id || cart.target_id)"
                    @click="startEditCartPrice(cart)"
                    type="button"
                    class="mt-1 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800"
                    title="Modifier le prix unitaire"
                  >
                    <i class="ri-pencil-line"></i>
                    <span>Modifier prix</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Admin / content_editor: change status -->
          <div v-if="canChangeStatus" class="pt-2 border-t border-gray-100">
            <span class="text-xs font-semibold text-gray-500 uppercase mb-2 block">Changer le statut</span>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="opt in visibleStatusOptions"
                :key="opt.value"
                @click="changeStatus(opt.value)"
                :disabled="savingStatus || getStatus(selectedOrder.field_status_local) === opt.value"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                  getStatus(selectedOrder.field_status_local) === opt.value
                    ? getStatusClass(opt.value) + ' ring-2 ring-offset-1 ring-current cursor-default'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 disabled:opacity-50'
                ]"
              >
                <i v-if="savingStatus && getStatus(selectedOrder.field_status_local) !== opt.value" class="ri-loader-4-line animate-spin mr-1"></i>
                {{ opt.label }}
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
            <button
              v-if="isAdmin && getStatus(selectedOrder.field_status_local) !== 'annuler'"
              @click="cancelOrder"
              :disabled="cancelling"
              class="w-full px-4 py-3 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 disabled:bg-red-400 transition-colors flex items-center justify-center space-x-2"
            >
              <i v-if="cancelling" class="ri-loader-4-line animate-spin"></i>
              <span>{{ cancelling ? 'Annulation...' : 'Annuler' }}</span>
            </button>

            <button
              @click="selectedOrder = null"
              class="w-full px-4 py-3 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200 transition-colors"
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
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useUIStore } from '../stores/useUIStore';
import { getOrderLocalList, cancelOrderLocal, updateOrderLocalStatus, updateOrderLocalCartPrice, getDetail } from '../services/api';
import { useProductStore } from '../stores/useProductStore';
import { proxyImage } from '../services/image';
import { extractProductImageUrl } from './eroso_commande/orderCommandeShared';
import BottomNav from '../components/BottomNav.vue';

const uiStore = useUIStore();
const productStore = useProductStore();

const orders = ref([]);
const selectedStatus = ref('all');
const selectedDateFilter = ref('7days');
const selectedDate = ref('');
const searchQuery = ref('');
const loading = ref(false);
const loadingMore = ref(false);
const selectedOrder = ref(null);
const cancelling = ref(false);
const savingStatus = ref(false);

// Admin inline price editing for a cart line.
const editingCartNid = ref(null);
const editingCartPrice = ref('');
const savingCartPrice = ref(false);
const cartPriceError = ref('');
const currentPage = ref(0);
const hasMore = ref(true);
const totalOrders = ref(0);
const PAGE_SIZE = 15;
const scrollSentinel = ref(null);
let observer = null;

const getUserRoles = () => {
  try {
    const rolesStr = localStorage.getItem('roles');
    if (!rolesStr) return [];
    const roles = JSON.parse(rolesStr);
    return Array.isArray(roles) ? roles : [];
  } catch {
    return [];
  }
};

const isAdmin = computed(() => getUserRoles().includes('administrator'));
const isContentEditor = computed(() => getUserRoles().includes('content_editor'));

const canChangeStatus = computed(() => isAdmin.value || isContentEditor.value);

const STATUS_OPTIONS = [
  { value: 'sortie',       label: 'Sortie' },
  { value: 'en_cours',     label: 'En cours' },
  { value: 'en_livraison', label: 'En livraison' },
  { value: 'payer',        label: 'Payé' },
  { value: 'no_payer',     label: 'Non payé' },
  { value: 'annuler',      label: 'Annulé' },
];

const CONTENT_EDITOR_STATUS_OPTIONS = ['sortie', 'en_livraison', 'annuler'];

const visibleStatusOptions = computed(() =>
  isAdmin.value
    ? STATUS_OPTIONS
    : STATUS_OPTIONS.filter(opt => CONTENT_EDITOR_STATUS_OPTIONS.includes(opt.value))
);

const orderStatuses = [
  { label: 'Tous',         value: 'all' },
  { label: 'Sortie',       value: 'sortie' },
  { label: 'En livraison', value: 'en_livraison' },
  { label: 'Payées',       value: 'payer' },
  { label: 'Annulées',     value: 'annuler' },
];

const dateFilters = [
  { label: 'Toutes dates', value: 'all' },
  { label: "Aujourd'hui", value: 'today' },
  { label: 'Par 7 jours', value: '7days' },
  { label: 'Par date', value: 'custom' },
];

const getStatus = (val) => {
  if (Array.isArray(val)) return val[0] || '';
  return val || '';
};

const getOrderDate = (order) => {
  const raw = order.field_date || order.created;
  if (!raw) return null;

  if (/^\d+$/.test(String(raw))) {
    const date = new Date(Number(raw) * 1000);
    return isNaN(date.getTime()) ? null : date;
  }

  const date = new Date(String(raw).includes('T') ? raw : `${raw}T00:00:00`);
  return isNaN(date.getTime()) ? null : date;
};

const isSameDay = (a, b) =>
  a.getFullYear() === b.getFullYear() &&
  a.getMonth() === b.getMonth() &&
  a.getDate() === b.getDate();

const dateFilteredOrders = computed(() => {
  if (selectedDateFilter.value === 'all') return orders.value;

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  if (selectedDateFilter.value === 'today') {
    return orders.value.filter((order) => {
      const d = getOrderDate(order);
      if (!d) return false;
      d.setHours(0, 0, 0, 0);
      return isSameDay(d, today);
    });
  }

  if (selectedDateFilter.value === '7days') {
    const sevenDaysAgo = new Date(today);
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 6);
    return orders.value.filter((order) => {
      const d = getOrderDate(order);
      if (!d) return false;
      d.setHours(0, 0, 0, 0);
      return d >= sevenDaysAgo && d <= today;
    });
  }

  if (selectedDateFilter.value === 'custom') {
    if (!selectedDate.value) return [];
    const picked = new Date(`${selectedDate.value}T00:00:00`);
    if (isNaN(picked.getTime())) return [];
    return orders.value.filter((order) => {
      const d = getOrderDate(order);
      if (!d) return false;
      d.setHours(0, 0, 0, 0);
      return isSameDay(d, picked);
    });
  }

  return orders.value;
});

const filteredOrders = computed(() => {
  let filtered = dateFilteredOrders.value;

  if (selectedStatus.value !== 'all') {
    filtered = filtered.filter(order => getStatus(order.field_status_local) === selectedStatus.value);
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(order =>
      (order.title && order.title.toLowerCase().includes(query)) ||
      (order.field_info && order.field_info.toLowerCase().includes(query)) ||
      (order.uid?.name && order.uid.name.toLowerCase().includes(query))
    );
  }

  return filtered;
});

const totalRevenue = computed(() => {
  return filteredOrders.value.reduce((sum, order) => {
    return sum + parseFloat(order.field_total || 0);
  }, 0);
});

const getOrderCountByStatus = (status) => {
  if (status === 'all') return dateFilteredOrders.value.length;
  return dateFilteredOrders.value.filter(order => getStatus(order.field_status_local) === status).length;
};

const getCarts = (order) => {
  if (Array.isArray(order.field_carts)) return order.field_carts;
  if (order.field_carts && typeof order.field_carts === 'object') return [order.field_carts];
  return [];
};

const getCartsCount = (order) => {
  return getCarts(order).length;
};

/**
 * Image cache keyed by product nid (the "product" bundle).
 *   undefined : not yet requested
 *   ''        : requested but no image
 *   string    : resolved URL
 */
const productImages = reactive({});
const productImagesPending = new Set();

const extractProductNidFromCart = (cartData) => {
  if (!cartData || typeof cartData !== 'object') return null;
  const raw = cartData.field_product_id;
  if (raw == null || raw === '') return null;
  if (typeof raw === 'number') return raw;
  if (typeof raw === 'string' && /^\d+$/.test(raw)) return Number(raw);
  let obj = raw;
  if (Array.isArray(raw) && raw.length > 0) obj = raw[0];
  if (obj && typeof obj === 'object') {
    const cand = obj.nid ?? obj.id ?? obj.target_id ?? null;
    if (cand != null && cand !== '' && !Number.isNaN(Number(cand))) {
      return Number(cand);
    }
  }
  return null;
};

const getCartImage = (cart) => {
  if (!cart || typeof cart !== 'object') return '';
  let raw = cart.productImage || '';
  if (!raw) {
    // Already-expanded cart payload may carry the product object directly.
    const embedded = extractProductImageUrl(
      (cart.field_product_id && typeof cart.field_product_id === 'object' && !Array.isArray(cart.field_product_id))
        ? cart.field_product_id
        : (Array.isArray(cart.field_product_id) ? cart.field_product_id[0] : null)
    );
    if (embedded) raw = embedded;
  }
  if (!raw) {
    const pid = cart.productId ?? extractProductNidFromCart(cart);
    if (pid != null) {
      const cached = productImages[pid];
      if (cached) raw = cached;
    }
  }
  if (!raw) return '';
  if (raw.startsWith('data:')) return raw;
  return proxyImage(raw, { w: 120, h: 120, fit: 'cover' });
};

const onCartImageError = (cart) => {
  if (!cart) return;
  const pid = cart.productId ?? extractProductNidFromCart(cart);
  if (pid != null) productImages[pid] = '';
  cart.productImage = '';
};

/**
 * Pull a scalar value out of a Drupal field payload that may arrive as a
 * plain scalar, `{value: ...}`, or an array of those.
 */
const readScalar = (raw) => {
  if (raw == null || raw === '') return null;
  if (typeof raw === 'number' || typeof raw === 'string') return raw;
  if (Array.isArray(raw) && raw.length > 0) {
    const first = raw[0];
    if (first && typeof first === 'object' && 'value' in first) return first.value;
    return first;
  }
  if (typeof raw === 'object' && 'value' in raw) return raw.value;
  return null;
};

const toNumberOrNull = (val) => {
  if (val == null || val === '') return null;
  const n = Number(val);
  return Number.isFinite(n) ? n : null;
};

/**
 * Copy quantity / unit price / line total from a fetched cart payload onto
 * the cart object rendered by the UI. Only fills missing values so we never
 * overwrite data already sent by the list endpoint.
 */
const enrichCartFromData = (cart, cartData) => {
  if (!cart || !cartData || typeof cartData !== 'object') return;

  if (cart.field_quantite == null || cart.field_quantite === '') {
    const qty = toNumberOrNull(readScalar(cartData.field_quantite));
    if (qty != null) cart.field_quantite = qty;
  }
  if (cart.field_prix_unitaire == null || cart.field_prix_unitaire === '') {
    const unit = toNumberOrNull(readScalar(cartData.field_prix_unitaire));
    if (unit != null) cart.field_prix_unitaire = unit;
  }
  if (cart.field_total == null || cart.field_total === '') {
    const total = toNumberOrNull(readScalar(cartData.field_total));
    if (total != null) cart.field_total = total;
  }
  // Fallback: if we have qty + unit but no total, compute it client-side.
  if (
    (cart.field_total == null || cart.field_total === '') &&
    cart.field_quantite != null &&
    cart.field_prix_unitaire != null
  ) {
    cart.field_total = Number(cart.field_quantite) * Number(cart.field_prix_unitaire);
  }

  if (!cart.title && cartData.title) {
    cart.title = String(cartData.title);
  }
};

/**
 * Two-stage resolution (same pattern as OrderDetailCommande.vue):
 *  1. Load each cart node to read field_product_id + qty/price if not already
 *     expanded in the list payload.
 *  2. Load each referenced product node to extract its image URL.
 * Cart objects are mutated in place so the UI reacts.
 */
const resolveCartImagesFor = async (order) => {
  if (!order) return;
  const carts = getCarts(order);
  if (carts.length === 0) return;

  await Promise.all(
    carts.map(async (cart) => {
      if (!cart || typeof cart !== 'object') return;
      const nid = cart.nid ?? cart.id ?? cart.target_id;
      const directPid = extractProductNidFromCart(cart);
      if (directPid != null && cart.productId == null) {
        cart.productId = directPid;
      }

      const needsPid = cart.productId == null;
      const needsQty = cart.field_quantite == null || cart.field_quantite === '';
      const needsUnit = cart.field_prix_unitaire == null || cart.field_prix_unitaire === '';
      const needsTotal = cart.field_total == null || cart.field_total === '';
      if (!needsPid && !needsQty && !needsUnit && !needsTotal) return;
      if (nid == null) return;

      try {
        const res = await getDetail('node', 'cart', nid);
        const data = res.data?.rows ?? res.data ?? null;
        const cartData = Array.isArray(data) ? data[0] : data;
        if (cartData && typeof cartData === 'object') {
          const pid = extractProductNidFromCart(cartData);
          if (pid != null && cart.productId == null) cart.productId = pid;
          enrichCartFromData(cart, cartData);
        }
      } catch {
        // Silent — the item still renders with whatever info we already have.
      }
    }),
  );

  const toFetch = [];
  for (const cart of carts) {
    if (!cart || cart.productImage) continue;
    const pid = cart.productId;
    if (pid == null) continue;
    if (productImages[pid] !== undefined) continue;
    if (productImagesPending.has(pid)) continue;
    toFetch.push(pid);
  }
  if (toFetch.length === 0) return;

  await Promise.all(
    toFetch.map(async (pid) => {
      productImagesPending.add(pid);
      try {
        const res = await getDetail('node', 'product', pid);
        const data = res.data?.rows ?? res.data ?? null;
        const product = Array.isArray(data) ? data[0] : data;
        const url = extractProductImageUrl(product || {});
        productImages[pid] = url || '';
      } catch {
        productImages[pid] = '';
      } finally {
        productImagesPending.delete(pid);
      }
    }),
  );
};

const getStatusClass = (status) => {
  const classes = {
    sortie:        'bg-blue-100 text-blue-700',
    en_cours:      'bg-orange-100 text-orange-700',
    en_livraison:  'bg-sky-100 text-sky-700',
    payer:         'bg-green-100 text-green-700',
    no_payer:      'bg-yellow-100 text-yellow-700',
    annuler:       'bg-red-100 text-red-700',
  };
  return classes[status] || 'bg-gray-100 text-gray-700';
};

const getStatusLabel = (status) => {
  const labels = {
    sortie:        'Sortie',
    en_cours:      'En cours',
    en_livraison:  'En livraison',
    payer:         'Payé',
    no_payer:      'Non payé',
    annuler:       'Annulé',
  };
  return labels[status] || status || 'N/A';
};

const getStatusBg = (status) => {
  const bgs = {
    sortie:        'bg-blue-50/60 hover:bg-blue-100/70',
    en_cours:      'bg-orange-50/60 hover:bg-orange-100/70',
    en_livraison:  'bg-sky-50/60 hover:bg-sky-100/70',
    payer:         'bg-green-50/60 hover:bg-green-100/70',
    no_payer:      'bg-yellow-50/60 hover:bg-yellow-100/70',
    annuler:       'bg-red-50/60 hover:bg-red-100/70',
  };
  return bgs[status] || 'bg-white hover:bg-gray-100';
};

const formatPrice = (price) => {
  if (!price) return '0';
  return new Intl.NumberFormat('fr-FR').format(price);
};

const formatDate = (value) => {
  if (!value) return 'N/A';
  let date;
  // Drupal created field is a Unix timestamp (seconds)
  if (/^\d+$/.test(String(value))) {
    date = new Date(Number(value) * 1000);
  } else {
    // field_date is Y-m-d, append time to avoid timezone shift
    date = new Date(value.includes('T') ? value : value + 'T00:00:00');
  }
  if (isNaN(date.getTime())) return 'N/A';
  return date.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric'
  });
};

const viewOrderDetails = (order) => {
  selectedOrder.value = order;
  resolveCartImagesFor(order);
};

watch(
  () => filteredOrders.value,
  (list) => {
    if (!Array.isArray(list) || list.length === 0) return;
    for (const order of list) {
      resolveCartImagesFor(order);
    }
  },
  { immediate: false },
);

// Reset the inline price editor whenever the detail modal is closed or
// switched to a different order.
watch(
  () => selectedOrder.value?.nid,
  () => {
    editingCartNid.value = null;
    editingCartPrice.value = '';
    cartPriceError.value = '';
  },
);

// Whether an admin is allowed to edit the price on the currently opened order.
const canEditCartPrice = computed(() => {
  if (!isAdmin.value || !selectedOrder.value) return false;
  const status = getStatus(selectedOrder.value.field_status_local);
  return status !== 'annuler';
});

const startEditCartPrice = (cart) => {
  if (!canEditCartPrice.value || !cart) return;
  const nid = cart.nid ?? cart.id ?? cart.target_id;
  if (nid == null) return;
  editingCartNid.value = nid;
  const current = Number(cart.field_prix_unitaire ?? 0);
  editingCartPrice.value = Number.isFinite(current) && current > 0 ? String(current) : '';
  cartPriceError.value = '';
};

const cancelEditCartPrice = () => {
  editingCartNid.value = null;
  editingCartPrice.value = '';
  cartPriceError.value = '';
};

const saveCartPrice = async (cart) => {
  if (!canEditCartPrice.value || !cart || savingCartPrice.value) return;
  const cartNid = cart.nid ?? cart.id ?? cart.target_id;
  const orderNid = selectedOrder.value?.nid;
  if (cartNid == null || orderNid == null) return;

  const raw = String(editingCartPrice.value ?? '').trim().replace(',', '.');
  const price = Number(raw);
  if (!Number.isFinite(price) || price < 0) {
    cartPriceError.value = 'Prix invalide';
    return;
  }

  savingCartPrice.value = true;
  cartPriceError.value = '';
  try {
    const res = await updateOrderLocalCartPrice({
      order_nid: orderNid,
      cart_nid: cartNid,
      prix_unitaire: price,
      token: localStorage.getItem('token') || '',
    });
    const data = res?.data;
    if (!data?.status) {
      throw new Error(data?.message || 'Échec de la mise à jour du prix.');
    }

    // Mutate the cart + order objects in place for instant UI refresh.
    cart.field_prix_unitaire = Number(data.prix_unitaire ?? price);
    if (data.field_total != null) {
      cart.field_total = Number(data.field_total);
    } else if (cart.field_quantite != null) {
      cart.field_total = Number(cart.field_quantite) * cart.field_prix_unitaire;
    }
    if (data.order_total != null && selectedOrder.value) {
      selectedOrder.value.field_total = Number(data.order_total);
    }

    // Sync the order in the list too.
    const idx = orders.value.findIndex(o => String(o.nid) === String(orderNid));
    if (idx !== -1 && data.order_total != null) {
      orders.value[idx] = {
        ...orders.value[idx],
        field_total: Number(data.order_total),
      };
    }

    cancelEditCartPrice();
  } catch (e) {
    console.error('Update cart price error:', e);
    cartPriceError.value =
      e?.response?.data?.message || e?.message || 'Erreur réseau.';
  } finally {
    savingCartPrice.value = false;
  }
};

const cancelOrder = async () => {
  if (!selectedOrder.value || cancelling.value) return;

  const ok = window.confirm("Confirmer l'annulation de cette vente ? Le stock sera remis à disposition.");
  if (!ok) return;

  const currentStatus = getStatus(selectedOrder.value.field_status_local);
  if (currentStatus === 'annuler') return;

  cancelling.value = true;
  try {
    const payload = {
      nid: selectedOrder.value.nid,
      token: localStorage.getItem('token') || '',
    };

    const res = await cancelOrderLocal(payload);
    const ok = res?.data?.status === true;
    if (!ok) throw new Error(res?.data?.message || 'Erreur lors de l’annulation.');

    // Update UI immediately
    selectedOrder.value.field_status_commande = 'annuler';
    selectedOrder.value.field_status_local = 'annuler';
    const idx = orders.value.findIndex(o => o.nid == selectedOrder.value.nid);
    if (idx !== -1) {
      orders.value[idx] = {
        ...orders.value[idx],
        field_status_commande: 'annuler',
        field_status_local: 'annuler',
      };
    }

    // Refresh products so rollback stock becomes visible.
    await productStore.fetchProducts(false, {});
  } catch (e) {
    console.error('Cancel order error:', e);
    alert(e?.message || 'Erreur lors de l’annulation.');
  } finally {
    cancelling.value = false;
  }
};

const changeStatus = async (newStatus) => {
  if (!selectedOrder.value || savingStatus.value) return;
  if (getStatus(selectedOrder.value.field_status_local) === newStatus) return;

  // Cancelling via status button: confirm + use cancelOrderLocal to rollback stock.
  if (newStatus === 'annuler') {
    const ok = window.confirm("Confirmer l'annulation de cette vente ? Le stock sera remis à disposition.");
    if (!ok) return;
  }

  savingStatus.value = true;
  try {
    const token = localStorage.getItem('token') || '';

    if (newStatus === 'annuler') {
      // cancelOrderLocal handles both status update and stock rollback.
      const res = await cancelOrderLocal({ nid: selectedOrder.value.nid, token });
      if (!res?.data?.status) throw new Error(res?.data?.message || 'Erreur lors de l\'annulation.');
    } else {
      const res = await updateOrderLocalStatus({ nid: selectedOrder.value.nid, status: newStatus, token });
      if (!res?.data?.status) throw new Error(res?.data?.message || 'Erreur de mise à jour.');
    }

    // Update local state.
    selectedOrder.value.field_status_local = newStatus;
    if (newStatus === 'annuler') {
      selectedOrder.value.field_status_commande = 'annuler';
    }
    const idx = orders.value.findIndex(o => o.nid == selectedOrder.value.nid);
    if (idx !== -1) {
      orders.value[idx] = {
        ...orders.value[idx],
        field_status_local: newStatus,
        ...(newStatus === 'annuler' ? { field_status_commande: 'annuler' } : {}),
      };
    }

    // Refresh product store so stock counts stay current.
    await productStore.fetchProducts(false, {});
  } catch (e) {
    alert(e?.message || 'Erreur lors de la mise à jour du statut.');
  } finally {
    savingStatus.value = false;
  }
};

const fetchOrders = async (append = false) => {
  if (!append) {
    loading.value = true;
    currentPage.value = 0;
    hasMore.value = true;
  } else {
    loadingMore.value = true;
  }

  try {
    const params = `offset=${PAGE_SIZE}&pager=${currentPage.value}&sort[val]=created&sort[op]=DESC`;
    const response = await getOrderLocalList(params);
    if (response.data && response.data.rows) {
      const rows = response.data.rows;
      totalOrders.value = response.data.total || 0;

      if (append) {
        orders.value = [...orders.value, ...rows];
      } else {
        orders.value = rows;
      }

      hasMore.value = orders.value.length < totalOrders.value;

      // Resolve product images for the freshly loaded orders.
      const targets = append ? rows : orders.value;
      targets.forEach((order) => resolveCartImagesFor(order));
    }
  } catch (error) {
    console.error('Error fetching order_local list:', error);
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
};

const loadMore = () => {
  if (loadingMore.value || !hasMore.value) return;
  currentPage.value++;
  fetchOrders(true);
};

const setupObserver = () => {
  if (observer) observer.disconnect();
  observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && hasMore.value && !loadingMore.value && !loading.value) {
      loadMore();
    }
  }, { rootMargin: '200px' });

  if (scrollSentinel.value) {
    observer.observe(scrollSentinel.value);
  }
};

onMounted(async () => {
  await fetchOrders();
  await nextTick();
  setupObserver();
});

onUnmounted(() => {
  if (observer) observer.disconnect();
});
</script>
