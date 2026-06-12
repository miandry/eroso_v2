import { reactive } from 'vue';
import { getDetail } from '../services/api';
import { proxyImage } from '../services/image';
import { extractProductImageUrl } from './eroso_commande/orderCommandeShared';

export const ORDER_LOCAL_STATUS_LABELS = {
  sortie: 'Sortie',
  en_cours: 'En cours',
  en_livraison: 'En livraison',
  payer: 'Payé',
  no_payer: 'Non payé',
  annuler: 'Annulé',
};

export const ORDER_LOCAL_STATUS_CLASS = {
  sortie: 'bg-blue-100 text-blue-700',
  en_cours: 'bg-orange-100 text-orange-700',
  en_livraison: 'bg-sky-100 text-sky-700',
  payer: 'bg-green-100 text-green-700',
  no_payer: 'bg-yellow-100 text-yellow-700',
  annuler: 'bg-red-100 text-red-700',
};

export function getOrderLocalStatus(val) {
  if (Array.isArray(val)) return val[0] || '';
  return val || '';
}

export function rawOrderNotes(order) {
  if (!order) return '';
  const v = order.field_info;
  if (v == null || v === '') return '';
  if (typeof v === 'string') return v;
  if (Array.isArray(v) && v[0] && typeof v[0] === 'object' && v[0].value != null) {
    return String(v[0].value);
  }
  if (typeof v === 'object' && v.value != null) return String(v.value);
  return String(v);
}

export function getOrderCarts(order) {
  if (!order?.field_carts) return [];
  if (Array.isArray(order.field_carts)) return order.field_carts;
  if (typeof order.field_carts === 'object') return [order.field_carts];
  return [];
}

export function getOrderCartsCount(order) {
  return getOrderCarts(order).length;
}

export function formatOrderLocalPrice(price) {
  if (!price) return '0';
  return new Intl.NumberFormat('fr-FR').format(price);
}

export function formatOrderLocalDate(value) {
  if (!value) return 'N/A';
  let date;
  if (/^\d+$/.test(String(value))) {
    date = new Date(Number(value) * 1000);
  } else {
    date = new Date(String(value).includes('T') ? value : `${value}T00:00:00`);
  }
  if (isNaN(date.getTime())) return 'N/A';
  return date.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
}

function readScalar(raw) {
  if (raw == null || raw === '') return null;
  if (typeof raw === 'number' || typeof raw === 'string') return raw;
  if (Array.isArray(raw) && raw.length > 0) {
    const first = raw[0];
    if (first && typeof first === 'object' && 'value' in first) return first.value;
    return first;
  }
  if (typeof raw === 'object' && 'value' in raw) return raw.value;
  return null;
}

function toNumberOrNull(val) {
  if (val == null || val === '') return null;
  const n = Number(val);
  return Number.isFinite(n) ? n : null;
}

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

function enrichCartFromData(cart, cartData) {
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
}

/** Résolution images / détails lignes panier pour order_local. */
export function useOrderLocalCartImages() {
  const productImages = reactive({});
  const productImagesPending = new Set();

  const getCartImage = (cart) => {
    if (!cart || typeof cart !== 'object') return '';
    let raw = cart.productImage || '';
    if (!raw) {
      const embedded = extractProductImageUrl(
        cart.field_product_id && typeof cart.field_product_id === 'object' && !Array.isArray(cart.field_product_id)
          ? cart.field_product_id
          : Array.isArray(cart.field_product_id)
            ? cart.field_product_id[0]
            : null,
      );
      if (embedded) raw = embedded;
    }
    if (!raw) {
      const pid = cart.productId ?? extractProductNidFromCart(cart);
      if (pid != null && productImages[pid]) raw = productImages[pid];
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

  const resolveCartImagesFor = async (order) => {
    if (!order) return;
    const carts = getOrderCarts(order);
    if (carts.length === 0) return;

    await Promise.all(
      carts.map(async (cart) => {
        if (!cart || typeof cart !== 'object') return;
        const nid = cart.nid ?? cart.id ?? cart.target_id;
        const directPid = extractProductNidFromCart(cart);
        if (directPid != null && cart.productId == null) cart.productId = directPid;

        const needsFetch =
          cart.productId == null ||
          cart.field_quantite == null ||
          cart.field_quantite === '' ||
          cart.field_prix_unitaire == null ||
          cart.field_prix_unitaire === '';
        if (!needsFetch || nid == null) return;

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
          // Silent
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
          productImages[pid] = extractProductImageUrl(product || {}) || '';
        } catch {
          productImages[pid] = '';
        } finally {
          productImagesPending.delete(pid);
        }
      }),
    );
  };

  return { getCartImage, onCartImageError, resolveCartImagesFor };
}
