<template>
  <div class="public-orders min-h-screen bg-[#fdf2f9] font-sans pb-8">
    <header class="sticky top-0 z-40 bg-[#fdf2f9]/95 backdrop-blur-md pt-[env(safe-area-inset-top)] border-b border-[#e8d4f0]/60">
      <div class="flex items-center gap-3 px-4 py-3">
        <button type="button" class="w-9 h-9 rounded-full bg-white border border-[#e8d4f0] text-[#4b2c82] flex items-center justify-center" aria-label="Retour au profil" @click="router.push('/profile')">
          <i class="ri-arrow-left-s-line text-2xl"></i>
        </button>
        <h1 class="text-base font-bold text-[#4b2c82]">Mes commandes</h1>
        <button type="button" class="ml-auto w-9 h-9 rounded-full bg-white border border-[#e8d4f0] text-[#5e35b1] flex items-center justify-center" aria-label="Actualiser les commandes" @click="loadOrders(false)">
          <i class="ri-refresh-line text-lg"></i>
        </button>
      </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-5">
      <div class="flex flex-wrap gap-2 mb-5">
        <button
          v-for="filter in statusFilters"
          :key="filter.value || 'all'"
          type="button"
          :class="[
            'px-3 py-1.5 rounded-full text-xs font-semibold transition-colors',
            statusFilter === filter.value
              ? 'bg-[#5e35b1] text-white'
              : 'bg-white text-[#5a4a6a] border border-[#e8d4f0] hover:bg-[#faf5fc]',
          ]"
          @click="statusFilter = filter.value"
        >
          {{ filter.label }}
        </button>
      </div>

      <div v-if="listError" class="mb-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-xs text-red-700">
        Impossible de charger vos commandes. Réessayez.
      </div>

      <div v-if="loading && orders.length === 0" class="flex flex-col items-center justify-center py-20">
        <div class="w-10 h-10 border-[3px] border-[#9b59b6] border-t-transparent rounded-full animate-spin"></div>
        <p class="text-xs text-[#8e44ad]/70 mt-3">Chargement…</p>
      </div>

      <div v-else-if="!loading && orders.length === 0" class="py-20 text-center">
        <i class="ri-file-list-3-line text-5xl text-[#d4b8e8]"></i>
        <p class="mt-3 text-sm font-semibold text-[#4b2c82]">Aucune commande</p>
        <p class="mt-1 text-xs text-[#8e44ad]/70">Vos commandes apparaîtront ici.</p>
      </div>

      <div v-else class="space-y-3">
        <article v-for="order in orders" :key="order.nid" class="bg-white rounded-2xl border border-[#f0e4f7] shadow-sm p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold', statusPillClass(order.status)]">
                {{ displayStatusLabel(order.status) }}
              </span>
              <p class="text-[10px] text-[#9b8aab] mt-2">
                <span class="font-mono text-[#8e44ad]">#{{ order.nid }}</span>
                · {{ formatDate(order.created) }}
              </p>
              <p v-if="order.infoPreview" class="text-xs text-[#5a4a6a] mt-2 line-clamp-2">{{ order.infoPreview }}</p>
            </div>
            <p class="text-base font-black text-[#5e35b1] shrink-0">{{ formatPrice(order.total) }} Ar</p>
          </div>

          <ul v-if="order.cartLines.length" class="mt-3 space-y-2 border-t border-[#f0e4f7] pt-3">
            <li v-for="(line, index) in order.cartLines" :key="line.nid || index" class="grid grid-cols-[1fr_auto_auto] gap-x-2 items-baseline text-xs text-[#5a4a6a]">
              <span class="min-w-0 font-medium break-words">{{ line.title }}</span>
              <span class="text-[#9b8aab]">{{ line.qty != null ? `×${line.qty}` : '' }}</span>
              <span class="font-semibold text-[#3d2a52] whitespace-nowrap">{{ line.lineTotal != null ? `${formatPrice(line.lineTotal)} Ar` : '' }}</span>
            </li>
          </ul>
        </article>
      </div>

      <div class="flex justify-center py-8">
        <button v-if="hasMore && !loading" type="button" class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-white border border-[#e8d4f0] text-[#4b2c82] hover:bg-[#faf5fc]" @click="loadOrders(true)">
          Charger plus
        </button>
        <div v-if="loading && orders.length > 0" class="w-7 h-7 border-2 border-[#9b59b6] border-t-transparent rounded-full animate-spin"></div>
        <p v-if="!hasMore && orders.length > 0" class="text-[10px] font-bold text-[#9b8aab] uppercase tracking-widest">Fin de la liste</p>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { getOrderCommandeList } from '../services/api';
import {
  normalizeOrderRow,
  statusLabel,
  statusPillClass,
  formatOrderCommandeDate,
  formatOrderCommandePrice,
} from './eroso_commande/orderCommandeShared';

const ITEMS_PER_PAGE = 20;
const router = useRouter();
const statusFilters = [
  { value: '', label: 'Toutes' },
  { value: 'draft_client', label: 'En attente' },
  { value: 'en_livraison', label: 'En livraison' },
  { value: 'payer_recue', label: 'Payé reçu' },
  { value: 'annuler', label: 'Annuler' },
];

const orders = ref([]);
const loading = ref(false);
const listError = ref(null);
const hasMore = ref(true);
const currentPage = ref(0);
const statusFilter = ref('');

function buildListParams() {
  let params = `sort[val]=created&sort[op]=DESC&offset=${ITEMS_PER_PAGE}&pager=${currentPage.value}&mine=1`;
  if (statusFilter.value) {
    params += `&filters[field_status_commande][val]=${encodeURIComponent(statusFilter.value)}`;
  }
  return params;
}

async function loadOrders(append = false) {
  if (loading.value) return;
  if (!append) {
    currentPage.value = 0;
    hasMore.value = true;
  }
  if (!hasMore.value && append) return;

  loading.value = true;
  listError.value = null;
  try {
    const response = await getOrderCommandeList(buildListParams());
    const raw = response.data?.rows ?? response.data ?? [];
    const list = Array.isArray(raw) ? raw : Object.values(raw || {});
    const rows = list.map(normalizeOrderRow).filter((row) => row.nid != null && row.nid !== '');
    orders.value = append ? [...orders.value, ...rows] : rows;
    if (rows.length < ITEMS_PER_PAGE) {
      hasMore.value = false;
    } else {
      currentPage.value += 1;
    }
  } catch (e) {
    listError.value = e;
    if (!append) orders.value = [];
    hasMore.value = false;
  } finally {
    loading.value = false;
  }
}

const formatPrice = formatOrderCommandePrice;
const formatDate = formatOrderCommandeDate;

function displayStatusLabel(status) {
  if (status === 'draft_client') return 'En attente';
  return statusLabel(status);
}

onMounted(() => {
  if (!localStorage.getItem('token')) {
    router.push('/login');
    return;
  }
  loadOrders(false);
});
watch(statusFilter, () => loadOrders(false));
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap');

.public-orders {
  font-family: 'Nunito', system-ui, -apple-system, sans-serif;
  -webkit-tap-highlight-color: transparent;
}
</style>