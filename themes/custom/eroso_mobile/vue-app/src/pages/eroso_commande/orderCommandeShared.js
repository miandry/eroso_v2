/** Libellés = field.storage.node.field_status_commande (clés machine). */
export const STATUS_LABELS = {
  draft: 'Draft',
  avance_payer: 'Avance payer',
  process_achat: 'Process achat',
  transport_vers_mada: 'Transport vers Mada',
  recue_a_mada: 'Reçue à Mada',
  en_livraison: 'En livraison',
  payer_recue: 'Payé reçu',
  annuler: 'Annuler',
};

/**
 * Flux commande complet jusqu’au paiement reçu.
 * `annuler` reste une sortie latérale accessible à tout moment.
 */
export const STATUS_WORKFLOW = [
  'draft',
  'avance_payer',
  'process_achat',
  'transport_vers_mada',
  'recue_a_mada',
  'en_livraison',
  'payer_recue',
];

export const STATUS_ANNULER = 'annuler';

/** Filtres liste : flux actuel + annuler. */
export const STATUS_ORDER = [...STATUS_WORKFLOW, STATUS_ANNULER];

const KNOWN_ORDER_STATUS_SET = new Set(STATUS_ORDER);

export function isKnownOrderStatus(status) {
  return status != null && status !== '' && KNOWN_ORDER_STATUS_SET.has(String(status));
}

export function isLegacyOrderStatus(status) {
  return false;
}

/** Suivi par ligne cart_commande (après réception à Mada). */
export const CART_STATUS_WORKFLOW = [
  'process',
  'transfer_vers_boutique',
  'en_livraison',
];

export const CART_STATUS_LABELS = {
  process: 'Process',
  transfer_vers_boutique: 'Transfer vers boutique',
  en_livraison: 'En livraison',
};

export function cartWorkflowIndex(status) {
  return CART_STATUS_WORKFLOW.indexOf(status);
}

export function isCartStatusTransitionAllowed(current, next) {
  if (!current || !next) return false;
  const i = cartWorkflowIndex(current);
  const j = cartWorkflowIndex(next);
  if (i < 0 || j < 0) return false;
  return j === i + 1;
}

export function cartStatusLabel(status) {
  const s = String(status || '').trim() || 'process';
  return CART_STATUS_LABELS[s] || s || '—';
}

export function cartStatusPillClass(status) {
  const s = String(status || '').trim() || 'process';
  const map = {
    process: 'bg-slate-100 text-slate-800 ring-1 ring-slate-200/80',
    transfer_vers_boutique: 'bg-fuchsia-100 text-fuchsia-950 ring-1 ring-fuchsia-200/80',
    en_livraison: 'bg-orange-100 text-orange-950 ring-1 ring-orange-200/80',
  };
  return map[s] || 'bg-gray-100 text-gray-800';
}

export function workflowIndex(status) {
  return STATUS_WORKFLOW.indexOf(status);
}

/**
 * Passage autorisé dans le flux : étape suivante +1, ou annuler si pas déjà annulé.
 * L’application réelle de « annuler » exige en plus le rôle admin
 * (voir canApplyStatusTransition).
 */
export function isStatusTransitionAllowed(current, next) {
  if (!current || !next) return false;
  if (next === STATUS_ANNULER) {
    return current !== STATUS_ANNULER;
  }
  const i = workflowIndex(current);
  const j = workflowIndex(next);
  if (i < 0 || j < 0) return false;
  return j === i + 1;
}

/**
 * Statuts nécessitant le rôle administrator pour être appliqués :
 *  - `draft`        : retour arrière au début du flux.
 *  - `payer_recue`  : confirmation du paiement final.
 */
export const ADMIN_ONLY_STATUSES = new Set(['draft', 'payer_recue']);

/**
 * Transition autorisée pour l’utilisateur courant.
 *
 * - `annuler` reste ouvert à tout utilisateur connecté (interdit seulement si
 *   la commande est déjà annulée, via isStatusTransitionAllowed()).
 * - `draft` (retour au brouillon) et `payer_recue` (paiement reçu) sont
 *   réservés aux administrateurs. Pour `draft`, l’admin peut revenir depuis
 *   n’importe quel statut (sauf s’il est déjà sur draft).
 */
export function canApplyStatusTransition(current, next, isAdministrator) {
  if (ADMIN_ONLY_STATUSES.has(next)) {
    if (!isAdministrator) return false;
    if (next === 'draft') {
      return Boolean(current) && current !== 'draft';
    }
    return isStatusTransitionAllowed(current, next);
  }
  return isStatusTransitionAllowed(current, next);
}

export function statusOptions() {
  return STATUS_ORDER.map((value) => ({
    value,
    label: STATUS_LABELS[value] || value,
  }));
}

export function scalarField(val) {
  if (val == null || val === '') return null;
  if (Array.isArray(val) && val.length > 0) {
    const first = val[0];
    if (first && typeof first === 'object' && 'value' in first) return first.value;
    if (first && typeof first === 'object' && 'target_id' in first) return first.target_id;
  }
  if (typeof val === 'object' && val.value !== undefined) return val.value;
  return val;
}

export function parseClientLabel(raw) {
  if (!raw) return '';
  if (typeof raw === 'string') return raw;
  if (raw.title) return raw.title;
  if (Array.isArray(raw) && raw.length) {
    const x = raw[0];
    if (x.title) return x.title;
    if (x.entity && x.entity.title) return x.entity.title;
  }
  return '';
}

/**
 * Extrait l’URL d’image d’un objet produit (product / product_commande) tel
 * que renvoyé par l’API (`field_media_image` ou `field_images`). Renvoie une
 * chaîne vide si aucune image exploitable n’est trouvée.
 */
export function extractProductImageUrl(product) {
  if (!product || typeof product !== 'object') return '';
  const mi = product.field_media_image;
  if (mi && typeof mi === 'object' && mi.image && mi.image.url) {
    return String(mi.image.url);
  }
  if (Array.isArray(mi) && mi[0] && mi[0].image && mi[0].image.url) {
    return String(mi[0].image.url);
  }
  const imgs = product.field_images;
  if (Array.isArray(imgs) && imgs[0] && imgs[0].image && imgs[0].image.url) {
    return String(imgs[0].image.url);
  }
  return '';
}

/**
 * À partir d’un champ `field_product_id` (scalar nid, objet étendu, ou
 * tableau d’objets), tente d’en extraire :
 * - productId : nid numérique du produit référencé, ou null
 * - productImage : URL d’image si le payload est déjà étendu, sinon ''
 * - productTitle : titre du produit si présent, sinon ''
 */
function extractProductRef(raw) {
  let productId = null;
  let productImage = '';
  let productTitle = '';
  if (raw == null || raw === '') {
    return { productId, productImage, productTitle };
  }
  let obj = raw;
  if (Array.isArray(raw) && raw.length > 0) {
    obj = raw[0];
  }
  if (typeof obj === 'number' || (typeof obj === 'string' && /^\d+$/.test(String(obj)))) {
    productId = Number(obj);
    return { productId, productImage, productTitle };
  }
  if (obj && typeof obj === 'object') {
    const cand = obj.nid ?? obj.id ?? obj.target_id ?? null;
    if (cand != null && cand !== '' && !Number.isNaN(Number(cand))) {
      productId = Number(cand);
    }
    if (obj.title) {
      productTitle = String(obj.title);
    } else if (obj.entity && typeof obj.entity === 'object' && obj.entity.title) {
      productTitle = String(obj.entity.title);
    }
    productImage = extractProductImageUrl(obj);
    if (!productImage && obj.entity && typeof obj.entity === 'object') {
      productImage = extractProductImageUrl(obj.entity);
    }
  }
  return { productId, productImage, productTitle };
}

/** cart_commande depuis field_carts (liste ou objet indexé). */
export function normalizeCartLines(raw) {
  if (raw == null || raw === '') return [];
  let arr = [];
  if (Array.isArray(raw)) {
    arr = raw;
  } else if (typeof raw === 'object') {
    arr = Object.values(raw);
  }
  const lines = [];
  for (const item of arr) {
    if (item == null) continue;
    if (typeof item === 'number' || (typeof item === 'string' && /^\d+$/.test(String(item)))) {
      const nid = Number(item);
      lines.push({
        nid,
        title: `Ligne #${nid}`,
        qty: null,
        lineTotal: null,
        cartStatus: CART_STATUS_WORKFLOW[0],
        productId: null,
        productImage: '',
      });
      continue;
    }
    if (typeof item !== 'object') continue;
    const nid = item.nid ?? item.target_id ?? null;
    let title = item.title;
    if (title == null || title === '') {
      if (item.node && typeof item.node === 'object' && item.node.title) {
        title = item.node.title;
      }
    }
    title = (title != null ? String(title) : '').trim() || (nid ? `Ligne #${nid}` : 'Article');
    let qty = scalarField(item.field_quantite);
    if (qty == null && item.field_quantite != null && !Array.isArray(item.field_quantite)) {
      qty = item.field_quantite;
    }
    const q = qty != null && qty !== '' && !Number.isNaN(Number(qty)) ? Number(qty) : null;
    let lineTotal = scalarField(item.field_total);
    if (lineTotal == null && item.field_total != null && !Array.isArray(item.field_total)) {
      lineTotal = item.field_total;
    }
    const lt =
      lineTotal != null && lineTotal !== '' && !Number.isNaN(Number(lineTotal)) ? Number(lineTotal) : null;
    let cartStatus = String(scalarField(item.field_status_cart_commande) || '').trim();
    if (!cartStatus && CART_STATUS_WORKFLOW.length) {
      cartStatus = CART_STATUS_WORKFLOW[0];
    }
    const { productId, productImage, productTitle } = extractProductRef(item.field_product_id);
    lines.push({
      nid,
      title,
      qty: q,
      lineTotal: lt,
      cartStatus,
      productId,
      productImage,
      productTitle,
    });
  }
  return lines;
}

export function normalizeOrderRow(row) {
  const nid = row.nid ?? row.id;
  const status = String(scalarField(row.field_status_commande) || '').trim();
  const total = scalarField(row.field_total);
  let info = scalarField(row.field_info);
  if (info == null) info = row.field_info;
  if (typeof info !== 'string') info = info ? String(info) : '';

  let created = row.created;
  if (created && typeof created === 'object' && created.value) {
    created = created.value;
  }

  return {
    nid,
    title: row.title || `Commande #${nid}`,
    status: String(status),
    total: total != null && total !== '' ? Number(total) : 0,
    infoPreview: info,
    clientLabel: parseClientLabel(row.field_client),
    created,
    cartLines: normalizeCartLines(row.field_carts),
  };
}

export function statusLabel(status) {
  return STATUS_LABELS[status] || status || '—';
}

export function statusPillClass(status) {
  const map = {
    draft: 'bg-slate-200 text-slate-800',
    avance_payer: 'bg-amber-100 text-amber-950 ring-1 ring-amber-200/80',
    process_achat: 'bg-sky-100 text-sky-950 ring-1 ring-sky-200/80',
    transport_vers_mada: 'bg-violet-100 text-violet-950 ring-1 ring-violet-200/80',
    recue_a_mada: 'bg-cyan-100 text-cyan-950 ring-1 ring-cyan-200/80',
    en_livraison: 'bg-orange-100 text-orange-950 ring-1 ring-orange-200/80',
    payer_recue: 'bg-emerald-100 text-emerald-950 ring-1 ring-emerald-200/80',
    annuler: 'bg-red-100 text-red-900 ring-1 ring-red-200/80',
  };
  return map[status] || 'bg-indigo-50 text-indigo-900 ring-1 ring-indigo-100';
}

export function formatOrderCommandeDate(value) {
  if (value == null || value === '') return '—';
  let ts = value;
  if (typeof ts === 'string' && /^\d+$/.test(ts)) ts = Number(ts);
  if (typeof ts === 'number' && ts < 1e12) {
    const d = new Date(ts * 1000);
    if (!isNaN(d.getTime())) {
      return d.toLocaleString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    }
  }
  const d = new Date(value);
  if (isNaN(d.getTime())) return '—';
  return d.toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function formatOrderCommandePrice(price) {
  if (!price && price !== 0) return '0';
  return Number(price).toLocaleString('fr-MG');
}
