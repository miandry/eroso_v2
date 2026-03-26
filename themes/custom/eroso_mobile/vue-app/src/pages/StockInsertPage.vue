<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <!-- Top nav -->
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button @click="uiStore.toggleSidebar" class="p-1 -ml-1 text-gray-600 cursor-pointer lg:hidden">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <div>
            <h1 class="text-lg font-semibold text-gray-900">Stock</h1>
            <p class="text-[11px] text-gray-500 -mt-0.5">Nouvelle entrée / sortie</p>
          </div>
        </div>

        <button
          @click="handleSubmit"
          :disabled="submitting"
          class="inline-flex items-center space-x-2 bg-blue-600 text-white px-4 py-2 rounded-xl font-bold hover:bg-blue-700 disabled:bg-blue-400 transition-colors"
        >
          <i v-if="submitting" class="ri-loader-4-line animate-spin"></i>
          <span>{{ submitting ? 'Enregistrement...' : 'Enregistrer' }}</span>
        </button>
      </div>
    </nav>

    <main class="pt-20 pb-24 px-4 lg:ml-64">
      <div class="max-w-3xl mx-auto space-y-4">
        <div v-if="error" class="bg-red-50 border border-red-100 text-red-700 rounded-2xl p-4 text-sm">
          {{ error }}
        </div>
        <div v-if="success" class="bg-green-50 border border-green-100 text-green-700 rounded-2xl p-4 text-sm">
          Mouvement de stock enregistré.
        </div>

        <!-- Form card -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Type -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
              <select
                v-model="form.field_type"
                class="w-full px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="in">Entrée</option>
                <option value="out">Sortie</option>
              </select>
            </div>

            <!-- Date -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Date entrée</label>
              <input
                v-model="form.field_date_entree"
                type="date"
                class="w-full px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <!-- Product search / select -->
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Produit</label>
              <div class="relative">
                <div class="relative">
                  <i class="ri-search-line absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                  <input
                    v-model="productQuery"
                    @focus="dropdownOpen = true"
                    @input="onProductQueryInput"
                    type="text"
                    placeholder="Rechercher un produit (titre ou SKU)..."
                    class="w-full pl-11 pr-4 py-3 bg-gray-50 rounded-2xl border border-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div v-if="dropdownOpen" class="absolute mt-2 w-full bg-white border border-gray-100 rounded-2xl shadow-xl overflow-hidden z-50">
                  <div class="max-h-72 overflow-y-auto">
                    <button
                      v-for="p in productResults"
                      :key="p.nid"
                      type="button"
                      @click="selectProduct(p)"
                      class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center justify-between"
                    >
                      <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900 truncate">
                          {{ p.title }}
                        </div>
                        <div class="text-xs text-gray-500 mt-0.5">
                          SKU: {{ p.field_sku || 'N/A' }} · Stock: {{ p.field_quantite_disponible ?? 0 }}
                        </div>
                      </div>
                      <i class="ri-arrow-right-s-line text-gray-400"></i>
                    </button>

                    <div v-if="productResults.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">
                      {{ productQuery.length < 2 ? 'Tapez au moins 2 caractères.' : 'Aucun produit trouvé.' }}
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="selectedProduct" class="mt-3 bg-blue-50/60 border border-blue-100 rounded-2xl p-4 flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-sm font-bold text-blue-900 truncate">{{ selectedProduct.title }}</div>
                  <div class="text-xs text-blue-700 mt-0.5">
                    SKU: {{ selectedProduct.field_sku || 'N/A' }} · Stock actuel: {{ selectedProduct.field_quantite_disponible ?? 0 }}
                  </div>
                </div>
                <button type="button" @click="clearSelectedProduct" class="text-blue-700 hover:text-blue-900 text-sm font-bold">
                  Changer
                </button>
              </div>
            </div>

            <!-- Quantity -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Quantité</label>
              <input
                v-model.number="form.field_quantite"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <!-- Unit price -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Price unitaire</label>
              <input
                v-model.number="form.field_price"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <!-- Sale price -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Prix de vente</label>
              <input
                v-model.number="form.field_prix_de_vente"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <!-- Total -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Total price</label>
              <input
                :value="formatNumber(totalPrice)"
                type="text"
                readonly
                class="w-full px-4 py-3 bg-gray-100 rounded-2xl border border-gray-100 text-gray-700"
              />
              <p class="text-[11px] text-gray-500 mt-1">Calculé: Price unitaire × Quantité</p>
            </div>

            <!-- Reason -->
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Raison</label>
              <input
                v-model="form.field_raison"
                type="text"
                placeholder="Ex: Réapprovisionnement, Correction, Retour client..."
                class="w-full px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <!-- Description -->
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
              <textarea
                v-model="form.field_description"
                rows="4"
                placeholder="Détails supplémentaires..."
                class="w-full px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
              ></textarea>
            </div>
          </div>
        </div>

        <div class="text-center text-xs text-gray-400">
          Les champs sont enregistrés dans le content type <span class="font-semibold">stock</span>.
        </div>
      </div>
    </main>

    <BottomNav />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useUIStore } from '../stores/useUIStore';
import { useProductStore } from '../stores/useProductStore';
import { saveItem } from '../services/api';
import BottomNav from '../components/BottomNav.vue';

const router = useRouter();
const uiStore = useUIStore();
const productStore = useProductStore();

const submitting = ref(false);
const error = ref('');
const success = ref(false);

const form = ref({
  field_type: 'in',
  field_date_entree: new Date().toISOString().split('T')[0],
  field_description: '',
  field_price: 0,
  field_prix_de_vente: 0,
  field_product_id: '',
  field_quantite: 1,
  field_raison: '',
});

const selectedProduct = ref(null);
const productQuery = ref('');
const productResults = ref([]);
const dropdownOpen = ref(false);
let searchTimer = null;

const totalPrice = computed(() => {
  const q = Number(form.value.field_quantite || 0);
  const p = Number(form.value.field_price || 0);
  return (Number.isFinite(q) ? q : 0) * (Number.isFinite(p) ? p : 0);
});

const formatNumber = (n) => {
  const val = Number(n || 0);
  if (!Number.isFinite(val)) return '0';
  return val.toLocaleString('fr-MG');
};

const clearSelectedProduct = () => {
  selectedProduct.value = null;
  form.value.field_product_id = '';
  productQuery.value = '';
  productResults.value = [];
  dropdownOpen.value = true;
};

const selectProduct = (p) => {
  selectedProduct.value = p;
  form.value.field_product_id = p.nid;
  productQuery.value = p.title;
  dropdownOpen.value = false;
};

const doSearch = async () => {
  const q = (productQuery.value || '').trim();
  if (q.length < 2) {
    productResults.value = [];
    return;
  }
  const results = await productStore.searchProducts(q);
  productResults.value = Array.isArray(results) ? results : [];
};

const onProductQueryInput = () => {
  dropdownOpen.value = true;
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    doSearch();
  }, 300);
};

watch(productQuery, (val) => {
  // If user edits the query after selecting a product, clear selection.
  if (selectedProduct.value && val !== selectedProduct.value.title) {
    selectedProduct.value = null;
    form.value.field_product_id = '';
  }
});

const handleSubmit = async () => {
  error.value = '';
  success.value = false;

  if (!form.value.field_product_id) {
    error.value = 'Veuillez sélectionner un produit.';
    dropdownOpen.value = true;
    return;
  }
  if (!form.value.field_date_entree) {
    error.value = 'Veuillez choisir la date.';
    return;
  }
  if (!form.value.field_type) {
    error.value = 'Veuillez choisir le type.';
    return;
  }
  if (!Number(form.value.field_quantite) || Number(form.value.field_quantite) <= 0) {
    error.value = 'La quantité doit être supérieure à 0.';
    return;
  }

  submitting.value = true;
  try {
    const titleBase = selectedProduct.value?.title || `Produit #${form.value.field_product_id}`;
    const payload = {
      entity_type: 'node',
      bundle: 'stock',
      title: `${form.value.field_type === 'out' ? 'Sortie' : 'Entrée'} - ${titleBase}`,
      field_date_entree: form.value.field_date_entree,
      field_description: form.value.field_description || '',
      field_price: Number(form.value.field_price || 0),
      field_prix_de_vente: Number(form.value.field_prix_de_vente || 0),
      field_product_id: form.value.field_product_id,
      field_quantite: Number(form.value.field_quantite || 0),
      field_raison: form.value.field_raison || '',
      field_total_price: totalPrice.value,
      field_type: form.value.field_type,
    };

    const res = await saveItem(payload);
    const ok = res?.data?.status === true || !!res?.data?.item || !!res?.data?.id;
    if (!ok) {
      error.value = res?.data?.message || 'Erreur lors de l’enregistrement.';
      return;
    }

    success.value = true;
    // Optional: refresh products so stock changes reflect elsewhere (if your backend updates it).
    productStore.fetchProducts(false, {});

    // Reset minimal fields for next entry
    form.value.field_description = '';
    form.value.field_raison = '';
    form.value.field_quantite = 1;
    form.value.field_price = 0;
    // Keep product selected for quick multiple entries.
    setTimeout(() => (success.value = false), 2500);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Erreur serveur lors de l’enregistrement.';
  } finally {
    submitting.value = false;
  }
};

onMounted(() => {
  // pre-load some products for quick selection
  productStore.fetchProducts(false, {});

  // close dropdown when clicking outside
  document.addEventListener('click', (evt) => {
    const target = evt.target;
    if (!(target instanceof Element)) return;
    if (!target.closest('.relative')) return;
    // noop: we keep dropdown controlled by focus/input; outside clicks will blur input anyway
  });
});
</script>
