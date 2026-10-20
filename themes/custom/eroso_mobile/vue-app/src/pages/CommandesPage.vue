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
          <button @click="fetchOrders(false)" :disabled="loading"
            class="flex items-center space-x-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i :class="['ri-refresh-line text-lg', loading ? 'animate-spin' : '']"></i>
            <span class="text-sm font-semibold">Actualiser</span>
          </button>
        </div>
      </div>

      <div class="p-4 space-y-4">
        <!-- Filter Tabs -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
          <div class="flex items-center space-x-2 overflow-x-auto">
            <button v-for="status in orderStatuses" :key="status.value" @click="selectStatus(status.value)" :class="[
              'px-4 py-2 rounded-lg text-sm font-semibold whitespace-nowrap transition-colors',
              selectedStatus === status.value
                ? 'bg-blue-600 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            ]">
              {{ status.label }}
              <span class="ml-1 text-xs opacity-75">({{ getOrderCountByStatus(status.value) }})</span>
            </button>
          </div>

          <div class="mt-4 flex flex-col gap-3">
            <div class="flex flex-wrap items-end gap-2">
              <label class="flex flex-col gap-0.5 text-[10px] font-bold text-gray-500 uppercase tracking-wide shrink-0">
                Du
                <input v-model="dateFrom" type="date"
                  class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs bg-white text-gray-800 min-w-0">
              </label>
              <label class="flex flex-col gap-0.5 text-[10px] font-bold text-gray-500 uppercase tracking-wide shrink-0">
                Au
                <input v-model="dateTo" type="date"
                  class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs bg-white text-gray-800 min-w-0">
              </label>
              <button v-if="dateFrom || dateTo" type="button"
                class="self-end px-2 py-1.5 text-xs font-semibold text-blue-600 hover:text-blue-800"
                @click="clearDates">
                Effacer dates
              </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <button v-for="period in datePresets" :key="period.value" type="button"
                @click="applyDatePreset(period.value)" :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors',
                  activeDatePreset === period.value
                    ? 'bg-indigo-600 text-white'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                ]">
                {{ period.label }}
              </button>
            </div>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-gray-500 uppercase">Total</span>
              <i class="ri-shopping-bag-line text-blue-600 text-lg"></i>
            </div>
            <div class="text-2xl font-black text-gray-900">{{ summary.total }}</div>
            <div class="text-xs text-gray-500 mt-1">Ventes</div>
          </div>

          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-sky-500 uppercase">Envoi livreur</span>
              <i class="ri-truck-line text-sky-600 text-lg"></i>
            </div>
            <div class="text-2xl font-black text-sky-600">{{ getOrderCountByStatus('envoi_livreur') }}</div>
            <div class="text-xs text-gray-500 mt-1">En cours</div>
          </div>

          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-green-500 uppercase">Livré P</span>
              <i class="ri-checkbox-circle-line text-green-600 text-lg"></i>
            </div>
            <div class="text-2xl font-black text-green-600">{{ getOrderCountByStatus('livre_p') }}</div>
            <div class="text-xs text-gray-500 mt-1">Payées</div>
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
                <input v-model="searchQuery" type="text" placeholder="Nom ou description produit (min. 2 car.)…"
                  class="w-full sm:w-64 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
            </div>
          </div>

          <div v-if="filteredOrders.length === 0" class="p-8 text-center">
            <i class="ri-shopping-bag-line text-4xl text-gray-300 mb-2"></i>
            <p class="text-sm text-gray-500">Aucune vente trouvée</p>
          </div>

          <div v-else class="divide-y divide-gray-100">
            <div v-for="order in filteredOrders" :key="order.nid" @click="viewOrderDetails(order)" :class="[
              'p-4 transition-colors cursor-pointer',
              getStatusBg(getStatus(order.field_status_local)),
            ]">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center space-x-2 mb-2">
                    <span class="text-sm font-bold text-gray-900">{{ order.title }}</span>
                    <span v-if="getStatus(order.field_status_commande)" :class="[
                      'px-2 py-1 rounded-full text-xs font-semibold',
                      getStatusClass(getStatus(order.field_status_commande))
                    ]">
                      {{ getStatusLabel(getStatus(order.field_status_commande)) }}
                    </span>
                  </div>

                  <div class="flex items-center space-x-2 mb-1">
                    <i class="ri-user-line text-gray-400 text-sm"></i>
                    <span class="text-sm text-gray-700">{{ order.uid?.name || 'N/A' }}</span>
                  </div>

                  <div v-if="rawNotesText(order)" class="flex items-center space-x-2 mb-1">
                    <i class="ri-file-text-line text-gray-400 text-sm"></i>
                    <span class="text-sm text-gray-600 line-clamp-1">{{ rawNotesText(order) }}</span>
                  </div>

                  <div class="flex items-center space-x-2 mb-2">
                    <i class="ri-calendar-line text-gray-400 text-sm"></i>
                    <span class="text-xs text-gray-500">{{ formatDate(order.field_date || order.created) }}</span>
                  </div>

                  <!-- Product list -->
                  <div v-if="getCarts(order).length > 0" class="flex flex-wrap gap-1">
                    <span v-for="cart in getCarts(order)" :key="cart.nid || cart"
                      class="inline-flex items-center gap-1 pl-0.5 pr-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs font-medium">
                      <span
                        class="w-5 h-5 rounded bg-white/70 border border-blue-100 overflow-hidden flex items-center justify-center shrink-0">
                        <img v-if="getCartImage(cart)" :src="getCartImage(cart)" :alt="cart.title || 'Article'"
                          class="w-full h-full object-cover" loading="lazy" @error="onCartImageError(cart)" />
                        <i v-else class="ri-image-2-line text-blue-300 text-[11px]"></i>
                      </span>
                      <span>{{ getCartDisplayTitle(cart) }}</span>
                      <span v-if="cart.field_quantite" class="font-bold">x{{ cart.field_quantite }}</span>
                    </span>
                  </div>
                </div>

                <div class="text-right ml-4">
                  <div class="text-lg font-bold text-blue-600">{{ formatPrice(order.field_total) }} Ar</div>
                  <div class="text-xs text-gray-500 mt-1">
                    {{ getCartsCount(order) }} article(s)
                  </div>
                  <span v-if="getStatus(order.field_status_local)" :class="[
                    'mt-2 inline-block px-2 py-1 rounded-full text-xs font-semibold',
                    getStatusClass(getStatus(order.field_status_local))
                  ]">
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
    <div v-if="selectedOrder" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
      @click.self="selectedOrder = null">
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
              <p class="text-sm text-gray-900 mt-1">{{ formatDate(selectedOrder.field_date || selectedOrder.created) }}
              </p>
            </div>
            <div>
              <span class="text-xs font-semibold text-gray-500 uppercase">Auteur</span>
              <p class="text-sm text-gray-900 mt-1">{{ selectedOrder.uid?.name || 'N/A' }}</p>
            </div>
            <div>
              <span class="text-xs font-semibold text-gray-500 uppercase">Statut</span>
              <p class="mt-1">
                <span
                  :class="['mt-2 inline-block px-2 py-1 rounded-full text-xs font-semibold', getStatusClass(getStatus(selectedOrder.field_status_local))]">
                  {{ getStatusLabel(getStatus(selectedOrder.field_status_local)) }}
                </span>
              </p>
            </div>
            <div>
              <span class="text-xs font-semibold text-gray-500 uppercase">Total</span>
              <p class="text-lg font-bold text-blue-600 mt-1">{{ formatPrice(selectedOrder.field_total) }} Ar</p>
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between gap-2 mb-1">
              <span class="text-xs font-semibold text-gray-500 uppercase">Notes</span>
              <button v-if="canEditNotes && !editingNotes" type="button"
                class="text-xs font-semibold text-blue-600 hover:text-blue-800" @click="startEditNotes">
                Modifier
              </button>
            </div>
            <template v-if="!editingNotes">
              <p v-if="rawNotesText(selectedOrder)"
                class="text-sm text-gray-700 mt-1 bg-gray-50 rounded-lg p-3 whitespace-pre-wrap">
                {{ rawNotesText(selectedOrder) }}
              </p>
              <p v-else class="text-xs text-gray-400 mt-1 italic">Aucune note.</p>
            </template>
            <div v-else class="mt-1 space-y-2">
              <textarea v-model="notesDraft" rows="4"
                class="w-full text-sm text-gray-800 border border-gray-200 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Notes…" :disabled="savingNotes" />
              <div class="flex flex-wrap gap-2">
                <button type="button"
                  class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
                  :disabled="savingNotes" @click="saveOrderNotes">
                  <i v-if="savingNotes" class="ri-loader-4-line animate-spin mr-1"></i>
                  Enregistrer
                </button>
                <button type="button"
                  class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-800 hover:bg-gray-200"
                  :disabled="savingNotes" @click="cancelEditNotes">
                  Annuler
                </button>
              </div>
              <p v-if="notesError" class="text-xs text-red-600">{{ notesError }}</p>
            </div>
          </div>

          <!-- Cart Items -->
          <div v-if="getCarts(selectedOrder).length > 0">
            <span class="text-xs font-semibold text-gray-500 uppercase mb-2 block">Articles</span>
            <div class="space-y-2">
              <div v-for="cart in getCarts(selectedOrder)" :key="cart.nid || cart"
                class="flex items-start gap-3 bg-gray-50 rounded-lg p-3">
                <div
                  class="shrink-0 w-14 h-14 rounded-lg bg-white border border-gray-200 overflow-hidden flex items-center justify-center">
                  <img v-if="getCartImage(cart)" :src="getCartImage(cart)" :alt="cart.title || 'Article'"
                    class="w-full h-full object-cover" loading="lazy" @error="onCartImageError(cart)" />
                  <i v-else class="ri-image-2-line text-gray-400 text-xl"></i>
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <p class="text-sm font-semibold text-gray-900 truncate">{{ getCartDisplayTitle(cart) }}</p>
                    <button v-if="canEditCartPrice" type="button" @click="openCartEditModal(cart)"
                      class="shrink-0 p-1 text-blue-600 hover:text-blue-800" title="Modifier l’article"
                      aria-label="Modifier l’article">
                      <i class="ri-pencil-line"></i>
                    </button>
                  </div>
                  <p class="text-xs text-gray-500">Qté: {{ cart.field_quantite ?? 0 }}</p>
                </div>

                <div class="text-right shrink-0">
                  <div class="flex items-center justify-end gap-1 text-xs text-gray-500">
                    <span>Achat: {{ formatPrice(getCartPurchasePrice(cart)) }} Ar</span>
                  </div>
                  <p v-if="cart.field_total != null" class="text-sm font-bold text-blue-600">{{ formatPrice(cart.field_total) }}
                    Ar</p>
                  <div v-if="cart.field_prix_unitaire != null && cart.field_prix_unitaire !== ''"
                    class="flex items-center justify-end gap-1 text-xs text-gray-500">
                    <span>{{ formatPrice(cart.field_prix_unitaire) }} Ar/u</span>
                  </div>
                  <button v-if="canDeleteCartLine" type="button"
                    class="mt-1 ml-2 inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-800 disabled:opacity-50"
                    title="Supprimer cet article"
                    :disabled="deletingCartNid === (cart.nid || cart.id || cart.target_id)"
                    @click="deleteCartLine(cart)">
                    <i v-if="deletingCartNid === (cart.nid || cart.id || cart.target_id)"
                      class="ri-loader-4-line animate-spin"></i>
                    <i v-else class="ri-delete-bin-line"></i>
                    <span>Supprimer</span>
                  </button>
                </div>
              </div>
              <p v-if="cartDeleteError" class="text-xs text-red-600 mt-1">{{ cartDeleteError }}</p>
            </div>
          </div>

          <!-- Admin / content_editor: change status -->
          <div v-if="canChangeStatus" class="pt-2 border-t border-gray-100">
            <span class="text-xs font-semibold text-gray-500 uppercase mb-2 block">Changer le statut</span>
            <div class="flex flex-wrap gap-2">
              <button v-for="opt in visibleStatusOptions" :key="opt.value" @click="changeStatus(opt.value)"
                :disabled="savingStatus || getStatus(selectedOrder.field_status_local) === opt.value" :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                  getStatus(selectedOrder.field_status_local) === opt.value
                    ? getStatusClass(opt.value) + ' ring-2 ring-offset-1 ring-current cursor-default'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 disabled:opacity-50'
                ]">
                <i v-if="savingStatus && getStatus(selectedOrder.field_status_local) !== opt.value"
                  class="ri-loader-4-line animate-spin mr-1"></i>
                {{ opt.label }}
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
            <button v-if="isAdmin && getStatus(selectedOrder.field_status_local) !== 'annuler'" @click="cancelOrder"
              :disabled="cancelling"
              class="w-full px-4 py-3 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 disabled:bg-red-400 transition-colors flex items-center justify-center space-x-2">
              <i v-if="cancelling" class="ri-loader-4-line animate-spin"></i>
              <span>{{ cancelling ? 'Annulation...' : 'Annuler' }}</span>
            </button>

            <button @click="selectedOrder = null"
              class="w-full px-4 py-3 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200 transition-colors">
              Fermer
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit cart item modal -->
    <div v-if="editingCart" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
      @click.self="closeCartEditModal">
      <form @submit.prevent="saveCartDetails" class="w-full max-w-md rounded-xl bg-white shadow-xl"
        @keyup.esc="closeCartEditModal">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
          <div class="min-w-0">
            <h2 class="text-base font-bold text-gray-900">Modifier l’article</h2>
            <p class="truncate text-xs text-gray-500">{{ getCartDisplayTitle(editingCart) }}</p>
          </div>
          <button type="button" @click="closeCartEditModal" :disabled="savingCartDetails"
            class="p-1 text-gray-400 hover:text-gray-700 disabled:opacity-50" aria-label="Fermer">
            <i class="ri-close-line text-xl"></i>
          </button>
        </div>

        <div class="space-y-4 p-5">
          <label class="block text-sm font-medium text-gray-700">
            Quantité
            <input v-model="cartEditDraft.quantity" type="number" min="1" step="1" required
              class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
              :disabled="savingCartDetails" />
          </label>
          <label class="block text-sm font-medium text-gray-700">
            Prix de vente unitaire (Ar)
            <input v-model="cartEditDraft.unitPrice" type="number" min="0" step="0.01" required
              class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
              :disabled="savingCartDetails" />
          </label>
          <label class="block text-sm font-medium text-gray-700">
            Prix d’achat unitaire (Ar)
            <input v-model="cartEditDraft.purchasePrice" type="number" min="0" step="0.01" required
              class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
              :disabled="savingCartDetails" />
          </label>
          <p v-if="cartEditError" class="text-sm text-red-600">{{ cartEditError }}</p>
        </div>

        <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-4">
          <button type="button" @click="closeCartEditModal" :disabled="savingCartDetails"
            class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 disabled:opacity-50">
            Annuler
          </button>
          <button type="submit" :disabled="savingCartDetails"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
            <i v-if="savingCartDetails" class="ri-loader-4-line animate-spin"></i>
            {{ savingCartDetails ? 'Enregistrement...' : 'Enregistrer' }}
          </button>
        </div>
      </form>
    </div>

    <BottomNav />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useUIStore } from '../stores/useUIStore';
import { getOrderLocalList, getOrderLocalStats, cancelOrderLocal, updateOrderLocalStatus, updateOrderLocalCartPrice, updateOrderLocalCartPurchasePrice, updateOrderLocalCartQuantity, deleteOrderLocalCartLine, getDetail, saveItem } from '../services/api';
import { useProductStore } from '../stores/useProductStore';
import { proxyImage } from '../services/image';
import { extractProductImageUrl } from './eroso_commande/orderCommandeShared';
import {
  ORDER_LOCAL_STATUS_OPTIONS,
  ORDER_LOCAL_EDITOR_STATUSES,
  ORDER_LOCAL_FILTER_TABS,
  getOrderLocalStatusLabel,
  getOrderLocalStatusClass,
  getOrderLocalStatusBg,
} from './orderLocalShared';
import BottomNav from '../components/BottomNav.vue';

const uiStore = useUIStore();
const productStore = useProductStore();

const orders = ref([]);
const summary = ref({ total: 0, revenue: 0, by_status: {} });
const selectedStatus = ref('all');

const toYmd = (date) => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

/** Y-m-d — filtre created côté API (timezone site). Vide = toutes dates. */
const dateFrom = ref('');
const dateTo = ref('');
const activeDatePreset = ref('all');
const searchQuery = ref('');
const loading = ref(false);
const loadingMore = ref(false);
const selectedOrder = ref(null);
const cancelling = ref(false);
const savingStatus = ref(false);

const editingCart = ref(null);
const cartEditDraft = reactive({ quantity: '', unitPrice: '', purchasePrice: '' });
const savingCartDetails = ref(false);
const cartEditError = ref('');
const deletingCartNid = ref(null);
const cartDeleteError = ref('');

// Notes (field_info) in detail modal
const editingNotes = ref(false);
const notesDraft = ref('');
const savingNotes = ref(false);
const notesError = ref('');

const currentPage = ref(0);
const hasMore = ref(true);
const totalOrders = ref(0);
const PAGE_SIZE = 15;
const scrollSentinel = ref(null);
let observer = null;
let requestSequence = 0;

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

const canEditNotes = computed(() => {
  if (!canChangeStatus.value || !selectedOrder.value) return false;
  return getStatus(selectedOrder.value.field_status_local) !== 'annuler';
});

const STATUS_OPTIONS = ORDER_LOCAL_STATUS_OPTIONS;

const CONTENT_EDITOR_STATUS_OPTIONS = ORDER_LOCAL_EDITOR_STATUSES;

const visibleStatusOptions = computed(() =>
  isAdmin.value
    ? STATUS_OPTIONS
    : STATUS_OPTIONS.filter(opt => CONTENT_EDITOR_STATUS_OPTIONS.includes(opt.value))
);

const orderStatuses = ORDER_LOCAL_FILTER_TABS;

const datePresets = [
  { label: 'Toutes dates', value: 'all' },
  { label: "Aujourd'hui", value: 'today' },
  { label: '7 derniers jours', value: '7days' },
];

const applyDatePreset = (preset) => {
  activeDatePreset.value = preset;
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  if (preset === 'all') {
    dateFrom.value = '';
    dateTo.value = '';
    return;
  }
  if (preset === 'today') {
    const ymd = toYmd(today);
    dateFrom.value = ymd;
    dateTo.value = ymd;
    return;
  }
  if (preset === '7days') {
    const start = new Date(today);
    start.setDate(start.getDate() - 6);
    dateFrom.value = toYmd(start);
    dateTo.value = toYmd(today);
  }
};

const clearDates = () => {
  activeDatePreset.value = 'all';
  dateFrom.value = '';
  dateTo.value = '';
};

const buildListParams = ({ includeStatus = true, offset = PAGE_SIZE, page = currentPage.value } = {}) => {
  let params = `offset=${offset}&pager=${page}&sort[val]=created&sort[op]=DESC`;
  if (includeStatus && selectedStatus.value !== 'all') {
    params += `&filters[field_status_local][val]=${encodeURIComponent(selectedStatus.value)}`;
  }
  if (dateFrom.value) {
    params += `&date_from=${encodeURIComponent(dateFrom.value)}`;
  }
  if (dateTo.value) {
    params += `&date_to=${encodeURIComponent(dateTo.value)}`;
  }
  const q = searchQuery.value.trim();
  if (q.length >= 2) {
    params += `&search=${encodeURIComponent(q)}`;
  }
  return params;
};

const selectStatus = (status) => {
  if (selectedStatus.value === status) return;
  selectedStatus.value = status;
  fetchOrders(false);
};

const getStatus = (val) => {
  if (Array.isArray(val)) return val[0] || '';
  return val || '';
};

/** field_info texte, que le JSON fournisse une string ou { value: … } / tableau. */
const rawNotesText = (order) => {
  if (!order) return '';
  const v = order.field_info;
  if (v == null || v === '') return '';
  if (typeof v === 'string') return v;
  if (Array.isArray(v) && v[0] && typeof v[0] === 'object' && v[0].value != null) {
    return String(v[0].value);
  }
  if (typeof v === 'object' && v.value != null) return String(v.value);
  return String(v);
};

const stripHtml = (html) => {
  if (!html) return '';
  return String(html).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
};

const filteredOrders = computed(() => {
  return orders.value;
});

const totalRevenue = computed(() => {
  return summary.value.revenue;
});

const getOrderCountByStatus = (status) => {
  if (status === 'all') return summary.value.total;
  return summary.value.by_status?.[status] || 0;
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
const productMeta = reactive({});
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

const getCartPurchasePrice = (cart) => toNumberOrNull(readScalar(cart?.field_price)) ?? 0;

const getCartSaleUnitPrice = (cart) => {
  const quantity = toNumberOrNull(readScalar(cart?.field_quantite));
  const lineTotal = toNumberOrNull(readScalar(cart?.field_total));
  if (quantity > 0 && lineTotal != null) return lineTotal / quantity;
  return toNumberOrNull(readScalar(cart?.field_prix_unitaire)) ?? 0;
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
  if (cart.field_price == null || cart.field_price === '') {
    const purchasePrice = toNumberOrNull(readScalar(cartData.field_price));
    if (purchasePrice != null) cart.field_price = purchasePrice;
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
      const needsPurchasePrice = cart.field_price == null || cart.field_price === '';
      if (!needsPid && !needsQty && !needsUnit && !needsTotal && !needsPurchasePrice) return;
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
        productMeta[pid] = {
          title: (product?.title || '').trim(),
          description: stripHtml(product?.field_description || ''),
        };
      } catch {
        productImages[pid] = '';
      } finally {
        productImagesPending.delete(pid);
      }
    }),
  );
};

const getStatusClass = (status) => getOrderLocalStatusClass(status);

const getStatusLabel = (status) => getOrderLocalStatusLabel(status);

const getStatusBg = (status) => getOrderLocalStatusBg(status);

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

const startEditNotes = () => {
  if (!canEditNotes.value || !selectedOrder.value) return;
  notesError.value = '';
  notesDraft.value = rawNotesText(selectedOrder.value);
  editingNotes.value = true;
};

const cancelEditNotes = () => {
  editingNotes.value = false;
  notesDraft.value = '';
  notesError.value = '';
};

const saveOrderNotes = async () => {
  if (!selectedOrder.value || savingNotes.value) return;
  const orderNid = selectedOrder.value.nid;
  if (orderNid == null) return;

  savingNotes.value = true;
  notesError.value = '';
  const value = String(notesDraft.value ?? '');
  try {
    const res = await saveItem({
      entity_type: 'node',
      bundle: 'order_local',
      nid: orderNid,
      field_info: value,
    });
    if (res.data?.status === true) {
      selectedOrder.value = { ...selectedOrder.value, field_info: value };
      const idx = orders.value.findIndex((o) => String(o.nid) === String(orderNid));
      if (idx !== -1) {
        orders.value[idx] = { ...orders.value[idx], field_info: value };
      }
      editingNotes.value = false;
    } else {
      notesError.value = res.data?.message || 'Échec de l’enregistrement des notes.';
    }
  } catch (e) {
    console.error('Save order notes:', e);
    notesError.value =
      e.response?.data?.message || e.message || 'Erreur réseau.';
  } finally {
    savingNotes.value = false;
  }
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

// Reset the article editor whenever the detail modal is closed or switched.
watch(
  () => selectedOrder.value?.nid,
  () => {
    editingCart.value = null;
    cartEditError.value = '';
    deletingCartNid.value = null;
    cartDeleteError.value = '';
    editingNotes.value = false;
    notesDraft.value = '';
    notesError.value = '';
  },
);

// Whether an admin is allowed to edit the price on the currently opened order.
const canEditCartPrice = computed(() => {
  if (!isAdmin.value || !selectedOrder.value) return false;
  const status = getStatus(selectedOrder.value.field_status_local);
  return status !== 'annuler';
});

const canDeleteCartLine = computed(() => {
  if (!canEditCartPrice.value || !selectedOrder.value) return false;
  return getCarts(selectedOrder.value).length > 1;
});

const openCartEditModal = (cart) => {
  if (!canEditCartPrice.value || !cart) return;
  editingCart.value = cart;
  cartEditDraft.quantity = String(toNumberOrNull(readScalar(cart.field_quantite)) ?? 1);
  cartEditDraft.unitPrice = String(getCartSaleUnitPrice(cart));
  cartEditDraft.purchasePrice = String(getCartPurchasePrice(cart));
  cartEditError.value = '';
};

const closeCartEditModal = () => {
  if (savingCartDetails.value) return;
  editingCart.value = null;
  cartEditError.value = '';
};

const saveCartDetails = async () => {
  const cart = editingCart.value;
  if (!canEditCartPrice.value || !cart || savingCartDetails.value) return;
  const cartNid = cart.nid ?? cart.id ?? cart.target_id;
  const orderNid = selectedOrder.value?.nid;
  if (cartNid == null || orderNid == null) return;

  const quantity = Number(String(cartEditDraft.quantity).trim());
  const unitPrice = Number(String(cartEditDraft.unitPrice).trim().replace(',', '.'));
  const purchasePrice = Number(String(cartEditDraft.purchasePrice).trim().replace(',', '.'));
  if (!Number.isInteger(quantity) || quantity < 1) {
    cartEditError.value = 'La quantité doit être un entier positif.';
    return;
  }
  if (!Number.isFinite(unitPrice) || unitPrice < 0 || !Number.isFinite(purchasePrice) || purchasePrice < 0) {
    cartEditError.value = 'Les prix doivent être des nombres positifs ou nuls.';
    return;
  }

  savingCartDetails.value = true;
  cartEditError.value = '';
  try {
    const currentQuantity = toNumberOrNull(readScalar(cart.field_quantite)) ?? 1;
    const currentUnitPrice = getCartSaleUnitPrice(cart);
    const currentPurchasePrice = getCartPurchasePrice(cart);
    let data = {};

    if (quantity !== currentQuantity) {
      const res = await updateOrderLocalCartQuantity({
        order_nid: orderNid,
        cart_nid: cartNid,
        quantite: quantity,
        prix_unitaire: unitPrice,
        field_price: purchasePrice,
        token: localStorage.getItem('token') || '',
      });
      data = res?.data;
      if (!data?.status) {
        throw new Error(data?.message || 'Échec de la mise à jour de l’article.');
      }
    } else {
      if (unitPrice !== currentUnitPrice) {
        const res = await updateOrderLocalCartPrice({
          order_nid: orderNid,
          cart_nid: cartNid,
          prix_unitaire: unitPrice,
          token: localStorage.getItem('token') || '',
        });
        if (!res?.data?.status) {
          throw new Error(res?.data?.message || 'Échec de la mise à jour du prix de vente.');
        }
        data = { ...data, ...res.data };
      }

      if (purchasePrice !== currentPurchasePrice) {
        const res = await updateOrderLocalCartPurchasePrice({
          order_nid: orderNid,
          cart_nid: cartNid,
          field_price: purchasePrice,
          token: localStorage.getItem('token') || '',
        });
        if (!res?.data?.status) {
          throw new Error(res?.data?.message || 'Échec de la mise à jour du prix d’achat.');
        }
        data = { ...data, ...res.data };
      }
    }

    cart.field_quantite = Number(data.quantite ?? quantity);
    cart.field_prix_unitaire = Number(data.prix_unitaire ?? unitPrice);
    cart.field_price = Number(data.field_price ?? purchasePrice);
    if (data.field_total != null) cart.field_total = Number(data.field_total);
    if (data.order_total != null) selectedOrder.value.field_total = Number(data.order_total);

    const orderIndex = orders.value.findIndex((order) => String(order.nid) === String(orderNid));
    if (orderIndex !== -1) {
      orders.value[orderIndex] = {
        ...orders.value[orderIndex],
        field_total: selectedOrder.value.field_total,
      };
    }

    editingCart.value = null;
    await Promise.all([
      fetchOrders(false),
      productStore.fetchProducts(false, {}),
    ]);
  } catch (e) {
    console.error('Update cart details error:', e);
    cartEditError.value = e?.response?.data?.message || e?.message || 'Erreur réseau.';
  } finally {
    savingCartDetails.value = false;
  }
};

const deleteCartLine = async (cart) => {
  if (!canDeleteCartLine.value || !cart || !selectedOrder.value) return;
  const cartNid = cart.nid ?? cart.id ?? cart.target_id;
  const orderNid = selectedOrder.value.nid;
  if (cartNid == null || orderNid == null) return;

  const label = cart.title || `Article #${cartNid}`;
  const ok = window.confirm(`Supprimer « ${label} » de cette vente ? Le stock sera recrédité.`);
  if (!ok) return;

  deletingCartNid.value = cartNid;
  cartDeleteError.value = '';
  try {
    const res = await deleteOrderLocalCartLine({
      order_nid: orderNid,
      cart_nid: cartNid,
      token: localStorage.getItem('token') || '',
    });
    const data = res?.data;
    if (!data?.status) {
      throw new Error(data?.message || 'Échec de la suppression.');
    }

    const remaining = getCarts(selectedOrder.value).filter((c) => {
      const id = c.nid ?? c.id ?? c.target_id;
      return String(id) !== String(cartNid);
    });
    selectedOrder.value.field_carts = remaining;
    if (data.order_total != null) {
      selectedOrder.value.field_total = Number(data.order_total);
    }

    const idx = orders.value.findIndex((o) => String(o.nid) === String(orderNid));
    if (idx !== -1) {
      orders.value[idx] = {
        ...orders.value[idx],
        field_carts: remaining,
        field_total: selectedOrder.value.field_total,
      };
    }

    await productStore.fetchProducts(false, {});
    showSuccess.value = true;
    setTimeout(() => { showSuccess.value = false; }, 1500);
  } catch (e) {
    console.error('Delete cart line error:', e);
    cartDeleteError.value =
      e?.response?.data?.message || e?.message || 'Erreur lors de la suppression.';
  } finally {
    deletingCartNid.value = null;
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
  const requestId = ++requestSequence;
  if (!append) {
    loading.value = true;
    currentPage.value = 0;
    hasMore.value = true;
  } else {
    loadingMore.value = true;
  }

  try {
    const params = buildListParams();
    const response = await getOrderLocalList(params);
    if (requestId !== requestSequence) return;
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

    if (!append) {
      const statsResponse = await getOrderLocalStats(buildListParams({ page: 0 }));
      if (requestId === requestSequence) {
        summary.value = {
          total: Number(statsResponse.data?.total || 0),
          revenue: Number(statsResponse.data?.revenue || 0),
          by_status: statsResponse.data?.by_status || {},
        };
      }
    }
  } catch (error) {
    console.error('Error fetching order_local list:', error);
  } finally {
    if (requestId === requestSequence) {
      loading.value = false;
      loadingMore.value = false;
    }
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

/**
 * Affiche le titre d'un article sans le suffixe de quantité « x1 », « X2 », etc.
 * Ex: "Mocassins femme rouges vernis style Chanel x1" → "Mocassins femme rouges vernis style Chanel"
 */
const getCartDisplayTitle = (cart) => {
  if (!cart) return 'Article';
  const title = cart.title || '';
  if (!title) return 'Article';
  return title.replace(/\s*x\d+$/i, '').trim() || 'Article';
};

onMounted(async () => {
  await fetchOrders();
  await nextTick();
  setupObserver();
});

watch([dateFrom, dateTo], () => {
  fetchOrders(false);
});

let searchDebounce = null;
watch(searchQuery, () => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    fetchOrders(false);
  }, 400);
});

onUnmounted(() => {
  if (observer) observer.disconnect();
  if (searchDebounce) clearTimeout(searchDebounce);
});
</script>
