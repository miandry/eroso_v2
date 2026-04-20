<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button type="button" class="p-1 -ml-1 text-gray-600 cursor-pointer" @click="goBack">
            <i class="ri-arrow-left-line ri-lg"></i>
          </button>
          <h1 class="text-lg font-semibold text-gray-900">Détail commande</h1>
        </div>
        <button
          type="button"
          class="w-8 h-8 flex items-center justify-center text-indigo-600 bg-indigo-50 rounded-lg cursor-pointer"
          :disabled="loading"
          @click="loadOrder"
        >
          <i class="ri-refresh-line" :class="loading ? 'animate-spin' : ''"></i>
        </button>
      </div>
    </nav>

    <main class="pt-16 pb-24 px-4 lg:ml-64">
      <div v-if="loadError" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        Impossible de charger cette commande.
      </div>

      <div v-if="loading && !order" class="flex flex-col items-center justify-center py-20 space-y-4">
        <div class="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-gray-500 font-medium">Chargement…</p>
      </div>

      <template v-else-if="order">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <p class="text-sm font-bold leading-snug">
                <span
                  :class="[
                    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold tracking-tight',
                    statusPillClass(order.status),
                  ]"
                >
                  {{ statusLabel(order.status) }}
                </span>
              </p>
              <p class="text-[10px] text-gray-400 mt-2">
                <span class="font-mono text-gray-500">#{{ order.nid }}</span>
                · {{ formatDate(order.created) }}
              </p>
              <p v-if="order.title" class="text-xs text-gray-500 mt-1">{{ order.title }}</p>
              <p v-if="order.clientLabel" class="text-xs text-gray-600 mt-3 flex items-center gap-1">
                <i class="ri-user-line text-gray-400"></i>
                {{ order.clientLabel }}
              </p>
              <p v-if="order.infoPreview" class="text-sm text-gray-600 mt-3 whitespace-pre-wrap bg-gray-50 rounded-xl p-3">
                {{ order.infoPreview }}
              </p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-base font-black text-indigo-600">{{ formatPrice(order.total) }} Ar</p>
            </div>
          </div>

          <ul
            v-if="order.cartLines && order.cartLines.length > 0"
            class="mt-4 space-y-0 border-t border-gray-100 pt-4"
          >
            <li
              v-for="(line, idx) in order.cartLines"
              :key="line.nid || idx"
              class="border-b border-gray-100 pb-3 mb-3 last:border-0 last:pb-0 last:mb-0"
            >
              <div class="flex items-start gap-3">
                <div class="shrink-0 w-14 h-14 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden flex items-center justify-center">
                  <img
                    v-if="getLineImage(line)"
                    :src="getLineImage(line)"
                    :alt="line.title"
                    class="w-full h-full object-cover"
                    loading="lazy"
                    @error="onImageError(line)"
                  />
                  <i v-else class="ri-image-2-line text-gray-400 text-xl"></i>
                </div>
                <div class="flex-1 min-w-0 grid grid-cols-[1fr_auto_auto] gap-x-2 gap-y-0.5 items-baseline text-xs text-gray-700">
                  <span class="min-w-0 font-medium leading-snug break-words">{{ line.title }}</span>
                  <span class="tabular-nums text-gray-500 text-right whitespace-nowrap">
                    {{ line.qty != null ? `×${line.qty}` : '' }}
                  </span>
                  <span class="font-semibold text-gray-900 tabular-nums text-right whitespace-nowrap">
                    <template v-if="line.lineTotal != null">{{ formatPrice(line.lineTotal) }} Ar</template>
                  </span>
                </div>
              </div>
            </li>
          </ul>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
          <div>
            <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Flux des statuts (commande)</h2>
          <p
            v-if="isLegacyOrderStatus(order.status)"
            class="text-[11px] text-indigo-900 bg-indigo-50 rounded-xl px-3 py-2 mb-4"
          >
            Cette commande a un statut de l’ancien flux ; le suivi courant se fait par <strong>statut article</strong> ci-dessus.
          </p>
          <p v-if="saveError" class="mb-3 text-sm text-red-700">{{ saveError }}</p>

          <p v-if="order.status === 'annuler'" class="text-sm text-red-800 bg-red-50 rounded-xl px-3 py-2 mb-4">
            Cette commande est annulée. Aucune autre évolution de statut.
          </p>
          <p v-else-if="unknownWorkflowStatus" class="text-xs text-amber-900 bg-amber-50 rounded-xl px-3 py-2 mb-4">
            Statut « {{ order.status }} » hors flux habituel. Utilisez le bouton « Annuler la commande » ou corrigez le statut dans Drupal.
          </p>

          <div v-if="showMainOrderWorkflow" class="grid grid-cols-2 gap-2">
            <button
              v-for="step in workflowSteps"
              :key="step.value"
              type="button"
              class="text-left rounded-lg px-2.5 py-2 text-[11px] font-bold transition-all border border-transparent min-w-0"
              :class="[
                statusPillClass(step.value),
                step.isCurrent ? 'ring-2 ring-indigo-500 ring-offset-2 cursor-default' : '',
                step.clickable
                  ? 'cursor-pointer shadow-sm hover:opacity-95 active:scale-[0.99]'
                  : 'opacity-55 cursor-not-allowed',
              ]"
              :disabled="saving || !step.clickable || step.isCurrent"
              @click="applyStatus(step.value)"
            >
              <span class="block leading-snug">{{ step.label }}</span>
              <span v-if="step.isCurrent" class="block text-[9px] font-semibold opacity-90 mt-0.5">Étape actuelle</span>
              <span
                v-else-if="step.isNext"
                class="block text-[9px] font-semibold opacity-90 mt-0.5 text-indigo-800"
              >Cliquez pour valider cette étape</span>
            </button>
          </div>

          <div
            v-if="order.status !== 'annuler'"
            class="mt-5 pt-4 border-t border-gray-100"
          >
            <button
              type="button"
              class="w-full rounded-xl border-2 border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800 hover:bg-red-100 transition-colors disabled:opacity-50"
              :disabled="saving"
              @click="applyStatus('annuler')"
            >
              Annuler la commande
            </button>
          </div>
          </div>

          <p v-if="saving || savingCart != null" class="mt-3 text-xs text-gray-500 flex items-center gap-2">
            <i class="ri-loader-4-line animate-spin"></i>
            {{ savingCart != null ? 'Mise à jour d’une ligne…' : 'Enregistrement…' }}
          </p>
        </div>
      </template>
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getDetail, saveItem } from '../../services/api';
import { proxyImage } from '../../services/image';
import {
  normalizeOrderRow,
  statusLabel,
  statusPillClass,
  STATUS_WORKFLOW,
  STATUS_ANNULER,
  ADMIN_ONLY_STATUSES,
  CART_STATUS_WORKFLOW,
  isKnownOrderStatus,
  isLegacyOrderStatus,
  isCartStatusTransitionAllowed,
  cartWorkflowIndex,
  cartStatusLabel,
  cartStatusPillClass,
  isStatusTransitionAllowed,
  canApplyStatusTransition,
  workflowIndex,
  formatOrderCommandeDate,
  formatOrderCommandePrice,
  extractProductImageUrl,
} from './orderCommandeShared';

const BUNDLE = 'order_commande';
const BUNDLE_CART = 'cart_commande';

const route = useRoute();
const router = useRouter();

const order = ref(null);
const loading = ref(false);
const loadError = ref(false);
const saving = ref(false);
/** Nid de la ligne cart_commande en cours d’enregistrement, ou null. */
const savingCart = ref(null);
const saveError = ref('');

/**
 * Cache local des URLs d’image de product_commande, indexé par nid.
 * - undefined : produit pas encore demandé
 * - '' : produit demandé mais sans image (ou introuvable)
 * - string : URL d’image
 */
const productImages = reactive({});
/** Nids de product_commande en cours de récupération (évite les doubles fetch). */
const productImagesPending = new Set();

/** Rôle Drupal « administrator » (stocké au login, cf. Sidebar / LoginPage). */
const isAdmin = computed(() => {
  try {
    const rolesStr = localStorage.getItem('roles');
    if (!rolesStr) return false;
    const roles = JSON.parse(rolesStr);
    return Array.isArray(roles) && roles.includes('administrator');
  } catch {
    return false;
  }
});

const nid = computed(() => {
  const n = Number(route.params.nid);
  return Number.isFinite(n) ? n : null;
});

/** Statut commande absent des valeurs connues (ni annuler). */
const unknownWorkflowStatus = computed(() => {
  if (!order.value) return false;
  const s = order.value.status;
  if (s === 'annuler') return false;
  return !isKnownOrderStatus(s);
});

const showMainOrderWorkflow = computed(() => {
  if (!order.value || order.value.status === 'annuler') return false;
  return workflowIndex(order.value.status) >= 0;
});

/** Après réception à Mada (ou ancien flux), afficher le suivi par ligne. */
const showCartLineWorkflow = computed(() => {
  if (!order.value || order.value.status === 'annuler') return false;
  const s = order.value.status;
  if (s === 'recue_a_mada') return true;
  return isLegacyOrderStatus(s);
});

const workflowSteps = computed(() => {
  if (!order.value || order.value.status === 'annuler') return [];
  const cur = order.value.status;
  const curIdx = workflowIndex(cur);

  return STATUS_WORKFLOW.map((value, idx) => {
    const isCurrent = value === cur;
    const isPast = curIdx >= 0 && idx < curIdx;
    const isNext = curIdx >= 0 && idx === curIdx + 1;
    const clickable =
      !saving.value && canApplyStatusTransition(cur, value, isAdmin.value);
    return {
      value,
      label: statusLabel(value),
      stepNo: idx + 1,
      isCurrent,
      isPast,
      isNext,
      clickable,
    };
  });
});

function formatPrice(v) {
  return formatOrderCommandePrice(v);
}
function formatDate(v) {
  return formatOrderCommandeDate(v);
}

/** URL d’image (proxy CDN) à afficher pour une ligne panier. */
function getLineImage(line) {
  if (!line) return '';
  let raw = line.productImage || '';
  if (!raw && line.productId != null) {
    const cached = productImages[line.productId];
    if (cached) raw = cached;
  }
  if (!raw) return '';
  if (raw.startsWith('data:')) return raw;
  return proxyImage(raw, { w: 120, h: 120, fit: 'cover' });
}

/** Fallback silencieux : image cassée -> on vide le cache et on repart sur l’icône. */
function onImageError(line) {
  if (line && line.productId != null) {
    productImages[line.productId] = '';
  }
  if (line) {
    line.productImage = '';
  }
}

/**
 * Normalise le champ `field_product_id` du JSON cart_commande en nid numérique.
 */
function extractProductNidFromCart(cartData) {
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
}

/**
 * Pour chaque ligne panier, résout en deux étapes :
 *  1. Si `productId` manque, charge le cart_commande pour en extraire le
 *     nid du product_commande (et compléter qty / prix / total si absents).
 *  2. Si l’image manque, charge le product_commande pour lire
 *     `field_media_image` / `field_images`.
 *
 * Les lignes sont mutées en place (reactive) pour rafraîchir l’affichage.
 */
async function resolveProductImages(lines) {
  if (!Array.isArray(lines) || lines.length === 0) return;

  // --- Étape 1 : résoudre productId via cart_commande. ---
  await Promise.all(
    lines.map(async (line) => {
      if (!line || line.productId != null) return;
      if (line.nid == null) return;
      try {
        const res = await getDetail('node', 'cart_commande', line.nid);
        const data = res.data?.rows ?? res.data ?? null;
        const cart = Array.isArray(data) ? data[0] : data;
        const pid = extractProductNidFromCart(cart);
        if (pid != null) {
          line.productId = pid;
        }
        if (cart && typeof cart === 'object') {
          if (!line.productTitle) {
            const raw = cart.field_product_id;
            let pTitle = '';
            if (raw && typeof raw === 'object' && !Array.isArray(raw) && raw.title) {
              pTitle = String(raw.title);
            } else if (Array.isArray(raw) && raw[0] && raw[0].title) {
              pTitle = String(raw[0].title);
            }
            if (pTitle) line.productTitle = pTitle;
          }
          if (line.qty == null && cart.field_quantite != null && cart.field_quantite !== '') {
            const n = Number(cart.field_quantite);
            if (!Number.isNaN(n)) line.qty = n;
          }
          if (line.lineTotal == null && cart.field_total != null && cart.field_total !== '') {
            const n = Number(cart.field_total);
            if (!Number.isNaN(n)) line.lineTotal = n;
          }
        }
      } catch (e) {
        // Ligne toujours affichable sans image/détails.
      }
    }),
  );

  // --- Étape 2 : récupérer l’image de chaque produit unique. ---
  const toFetch = [];
  for (const line of lines) {
    if (!line || line.productImage) continue;
    const pid = line.productId;
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
        const res = await getDetail('node', 'product_commande', pid);
        const data = res.data?.rows ?? res.data ?? null;
        const product = Array.isArray(data) ? data[0] : data;
        const url = extractProductImageUrl(product || {});
        productImages[pid] = url || '';
      } catch (e) {
        productImages[pid] = '';
      } finally {
        productImagesPending.delete(pid);
      }
    }),
  );
}

function goBack() {
  router.push('/sur-commande/orders');
}

function cartLineSteps(line) {
  const raw = line?.cartStatus;
  const cur =
    raw && CART_STATUS_WORKFLOW.includes(String(raw))
      ? String(raw)
      : 'process';
  const curIdx = cartWorkflowIndex(cur);
  const busy = savingCart.value != null;
  return CART_STATUS_WORKFLOW.map((value, idx) => ({
    value,
    label: cartStatusLabel(value),
    isCurrent: value === cur,
    isPast: curIdx >= 0 && idx < curIdx,
    isNext: curIdx >= 0 && idx === curIdx + 1,
    clickable:
      Boolean(line?.nid) && !busy && isCartStatusTransitionAllowed(cur, value),
  }));
}

async function applyCartLineStatus(line, nextStatus) {
  if (!order.value || !line?.nid || savingCart.value != null || saving.value) return;
  const cur =
    line.cartStatus && CART_STATUS_WORKFLOW.includes(line.cartStatus)
      ? line.cartStatus
      : 'process';
  if (!isCartStatusTransitionAllowed(cur, nextStatus)) return;

  savingCart.value = line.nid;
  saveError.value = '';
  try {
    const res = await saveItem({
      entity_type: 'node',
      bundle: BUNDLE_CART,
      nid: line.nid,
      field_status_cart_commande: nextStatus,
    });
    if (res.data?.status === true) {
      await loadOrder();
    } else {
      saveError.value = res.data?.message || 'Échec de la mise à jour de la ligne.';
    }
  } catch (e) {
    saveError.value =
      e.response?.data?.message || e.message || 'Erreur réseau.';
  } finally {
    savingCart.value = null;
  }
}

async function loadOrder() {
  if (nid.value == null) {
    order.value = null;
    loadError.value = true;
    return;
  }
  loading.value = true;
  loadError.value = false;
  try {
    const res = await getDetail('node', BUNDLE, nid.value);
    const raw = res.data;
    if (!raw || (raw.nid == null && raw.id == null)) {
      order.value = null;
      loadError.value = true;
      return;
    }
    order.value = normalizeOrderRow(raw);
    if (order.value && Array.isArray(order.value.cartLines)) {
      resolveProductImages(order.value.cartLines);
    }
  } catch (e) {
    console.error('order_commande detail:', e);
    order.value = null;
    loadError.value = true;
  } finally {
    loading.value = false;
  }
}

async function applyStatus(nextStatus) {
  if (!order.value || nid.value == null || saving.value) return;
  if (nextStatus === order.value.status) return;

  if (!canApplyStatusTransition(order.value.status, nextStatus, isAdmin.value)) {
    if (ADMIN_ONLY_STATUSES.has(nextStatus) && !isAdmin.value) {
      saveError.value =
        'Seuls les administrateurs peuvent appliquer ce statut.';
    } else {
      saveError.value = 'Cette transition n’est pas autorisée dans le flux.';
    }
    return;
  }

  if (nextStatus === STATUS_ANNULER) {
    const ok = window.confirm(
      'Passer cette commande au statut « Annuler » ?',
    );
    if (!ok) return;
  }

  saving.value = true;
  saveError.value = '';
  try {
    const res = await saveItem({
      entity_type: 'node',
      bundle: BUNDLE,
      nid: nid.value,
      field_status_commande: nextStatus,
    });
    if (res.data?.status === true) {
      order.value = { ...order.value, status: nextStatus };
      await loadOrder();
    } else {
      saveError.value = res.data?.message || 'Échec de la mise à jour.';
    }
  } catch (e) {
    saveError.value =
      e.response?.data?.message || e.message || 'Erreur réseau.';
  } finally {
    saving.value = false;
  }
}

watch(
  nid,
  () => {
    loadOrder();
  },
  { immediate: true },
);
</script>
