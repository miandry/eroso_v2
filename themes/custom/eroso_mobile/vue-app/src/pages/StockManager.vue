<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <!-- Navigation Haute -->
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button @click="uiStore.toggleSidebar" class="p-1 -ml-1 text-gray-600 cursor-pointer">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <h1 class="text-lg font-semibold text-gray-900">Gestion des Stocks</h1>
        </div>
        <div class="flex items-center space-x-2">
          <button class="w-8 h-8 flex items-center justify-center text-gray-600 cursor-pointer">
            <i class="ri-settings-3-line ri-lg"></i>
          </button>
        </div>
      </div>
    </nav>

    <main class="pt-16 pb-20 px-4">
      <!-- Vue Inventaire -->
      <div v-if="currentSection === 'inventory'" id="inventory-view" class="space-y-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-gray-900">Inventaire</h2>
          <div class="flex items-center space-x-2">
            <button class="w-8 h-8 flex items-center justify-center text-gray-600 bg-gray-100 rounded-lg cursor-pointer">
              <i class="ri-search-line"></i>
            </button>
            <button class="w-8 h-8 flex items-center justify-center text-gray-600 bg-gray-100 rounded-lg cursor-pointer">
              <i class="ri-filter-3-line"></i>
            </button>
          </div>
        </div>
        <div class="space-y-4">
          <div v-for="product in products" :key="product.ref" class="bg-white rounded-lg shadow-sm p-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <img :src="getProductImage(product)" class="w-10 h-10 rounded-lg object-cover">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">{{ product.title }}</p>
                <p class="text-xs text-gray-500 uppercase">{{ product.field_sku || 'N/A' }}</p>
              </div>
              </div>
              <div class="text-right">
                <p class="text-sm font-bold text-gray-900">{{ product.field_quantite_disponible || 0 }}</p>
                <p class="text-[10px] text-gray-500 uppercase">Unités</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Gestion Stock (Accueil) -->
      <div v-if="currentSection === 'stock'" id="stock-management" class="space-y-6">
        <div class="grid grid-cols-2 gap-4 mt-6">
          <button @click="openEntryForm" id="entry-btn" class="bg-blue-600 text-white p-4 rounded-lg shadow-sm flex flex-col items-center space-y-2 cursor-pointer transition-colors hover:bg-blue-700">
            <div class="w-8 h-8 flex items-center justify-center">
              <i class="ri-add-line ri-xl"></i>
            </div>
            <span class="text-sm font-medium">Entrée Stock</span>
          </button>
          <button @click="openExitForm" id="exit-btn" class="bg-red-500 text-white p-4 rounded-lg shadow-sm flex flex-col items-center space-y-2 cursor-pointer transition-colors hover:bg-red-600">
            <div class="w-8 h-8 flex items-center justify-center">
              <i class="ri-subtract-line ri-xl"></i>
            </div>
            <span class="text-sm font-medium">Sortie Stock</span>
          </button>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900">Aperçu du Stock</h2>
            <button @click="router.push('/products')" class="text-blue-600 text-sm cursor-pointer">Voir tout</button>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <div class="text-center">
              <div class="text-2xl font-bold text-gray-900">1,247</div>
              <div class="text-xs text-gray-500">Articles Total</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-green-600">89</div>
              <div class="text-xs text-gray-500">Entrées Aujourd'hui</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-red-500">12</div>
              <div class="text-xs text-gray-500">Stock Bas</div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900">Mouvements Récents</h2>
            <button class="w-6 h-6 flex items-center justify-center text-gray-400 cursor-pointer">
              <i class="ri-filter-line"></i>
            </button>
          </div>
          <div class="space-y-3">
            <div v-for="(mv, idx) in recentMovements" :key="idx" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div class="flex items-center space-x-3">
                <div :class="['w-8 h-8 flex items-center justify-center rounded-full', mv.type === 'in' ? 'bg-green-100' : 'bg-red-100']">
                  <i :class="[mv.type === 'in' ? 'ri-arrow-up-line text-green-600' : 'ri-arrow-down-line text-red-600']"></i>
                </div>
                <div>
                  <div class="text-sm font-medium text-gray-900">{{ mv.name }}</div>
                  <div class="text-xs text-gray-500">Réf: {{ mv.ref }}</div>
                </div>
              </div>
              <div class="text-right">
                <div :class="['text-sm font-semibold', mv.type === 'in' ? 'text-green-600' : 'text-red-600']">{{ mv.type === 'in' ? '+' : '-' }}{{ mv.qty }}</div>
                <div class="text-xs text-gray-500">{{ mv.time }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Statistiques -->
      <div v-if="currentSection === 'stats'" id="statistics" class="space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">Statistiques</h2>
          <select class="text-sm border border-gray-300 rounded-lg px-3 py-2">
            <option>Cette semaine</option>
            <option>Ce mois</option>
            <option>Cette année</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-white rounded-lg shadow-sm p-4">
            <div class="flex items-center space-x-2 mb-2">
              <i class="ri-box-3-line text-blue-600"></i>
              <span class="text-sm text-gray-600">Stock Total</span>
            </div>
            <div class="text-2xl font-bold text-gray-900">Ar 245,890</div>
            <div class="text-xs text-green-600 mt-1">+12.5% vs mois dernier</div>
          </div>
          <div class="bg-white rounded-lg shadow-sm p-4">
            <div class="flex items-center space-x-2 mb-2">
              <i class="ri-exchange-line text-orange-500"></i>
              <span class="text-sm text-gray-600">Rotation</span>
            </div>
            <div class="text-2xl font-bold text-gray-900">4.2x</div>
            <div class="text-xs text-green-600 mt-1">+0.8x vs mois dernier</div>
          </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-4">
          <h3 class="text-base font-semibold text-gray-900 mb-4">Évolution du Stock</h3>
          <div ref="chartRef" style="height: 200px;"></div>
        </div>
        <!-- Top Produits & Rapports (simulated) -->
      </div>

      <!-- Formulaire Entrée -->
      <div v-if="currentSection === 'entryForm'" id="entry-form" class="space-y-6">
        <div class="flex items-center space-x-4">
          <button @click="showStock" class="w-8 h-8 flex items-center justify-center text-gray-600 cursor-pointer">
            <i class="ri-arrow-left-line ri-lg"></i>
          </button>
          <h2 class="text-lg font-semibold text-gray-900">Entrée de Stock</h2>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
            <input type="date" v-model="entryData.date" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Référence Produit</label>
            <div class="relative">
              <input type="text" v-model="productSearch" @input="handleSearch" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent" placeholder="Rechercher un produit..." autocomplete="off">
              <div v-if="searchResults.length && showResults" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <div v-for="res in searchResults" :key="res.nid" @click="selectProduct(res)" class="px-4 py-3 hover:bg-gray-50 cursor-pointer flex items-center space-x-3">
                  <div class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg">
                    <i class="ri-smartphone-line text-gray-600"></i>
                  </div>
                  <div>
                    <div class="text-sm font-medium text-gray-900">{{ res.title }}</div>
                    <div class="text-xs text-gray-500">{{ res.field_sku || 'N/A' }}</div>
                  </div>
                </div>
              </div>
            </div>
            <button @click="currentSection = 'addProduct'" class="mt-2 text-sm text-blue-600 flex items-center space-x-1">
              <i class="ri-add-line"></i>
              <span>Ajouter un nouveau produit</span>
            </button>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Quantité</label>
            <input type="number" v-model="entryData.qty" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-600">
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Prix Unitaire (Ar)</label>
              <input type="number" v-model="entryData.unit_price" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Prix de Vente (Ar)</label>
              <input type="number" v-model="entryData.sale_price" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Raison / Note</label>
            <input type="text" v-model="entryData.reason" placeholder="Ex: Réapprovisionnement" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-600">
          </div>
        </div>
        <button @click="submitEntry" class="w-full bg-blue-600 text-white py-4 rounded-lg font-medium cursor-pointer">Valider l'Entrée</button>
      </div>

      <!-- Formulaire Ajouter Nouveau Produit -->
      <div v-if="currentSection === 'addProduct'" id="add-product-form" class="space-y-6">
        <div class="flex items-center space-x-4">
          <button @click="currentSection = 'entryForm'" class="w-8 h-8 flex items-center justify-center text-gray-600 cursor-pointer">
            <i class="ri-arrow-left-line ri-lg"></i>
          </button>
          <h2 class="text-lg font-semibold text-gray-900">Ajouter un Produit</h2>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Image du Produit</label>
            <div class="flex items-center space-x-4">
              <div class="w-24 h-24 bg-gray-100 rounded-2xl flex items-center justify-center overflow-hidden border-2 border-dashed border-gray-300 relative group">
                <img v-if="newProduct.image" :src="newProduct.image" class="w-full h-full object-cover">
                <i v-else class="ri-image-add-line text-2xl text-gray-400"></i>
                <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                  <i class="ri-camera-line text-white text-xl"></i>
                </div>
                <input type="file" @change="handleImageUpload" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
              </div>
              <div class="flex-1 text-xs text-gray-500">
                <p>Cliquez pour télécharger une photo.</p>
                <p class="mt-1">Format: JPG, PNG. Max 2Mo.</p>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Catégorie</label>
            <select v-model="newProduct.category" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-600 bg-white">
              <option value="">Sélectionner une catégorie</option>
              <option v-for="cat in categories" :key="cat.tid" :value="cat.name">{{ cat.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Nom du Produit <span class="text-red-500">*</span>
            </label>
            <input type="text" v-model="newProduct.name" @input="updatePreviewImage" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Ex: iPhone 15 Pro">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Référence</label>
            <input type="text" v-model="newProduct.ref" readonly class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base bg-gray-50 text-gray-500 cursor-not-allowed" placeholder="Générée automatiquement">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Prix de Vente (Ar) <span class="text-red-500">*</span>
            </label>
            <input type="number" v-model="newProduct.price" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Ex: 45000">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea v-model="newProduct.description" rows="3" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Entrez la description du produit..."></textarea>
          </div>
        </div>
        <button @click="addNewProduct" class="w-full bg-blue-600 text-white py-4 rounded-lg font-medium cursor-pointer">Ajouter le Produit</button>
      </div>

      <!-- Formulaire Sortie -->
      <div v-if="currentSection === 'exitForm'" id="exit-form" class="space-y-6">
        <div class="flex items-center space-x-4">
          <button @click="showStock" class="w-8 h-8 flex items-center justify-center text-gray-600 cursor-pointer">
            <i class="ri-arrow-left-line ri-lg"></i>
          </button>
          <h2 class="text-lg font-semibold text-gray-900">Sortie de Stock</h2>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
            <input type="date" v-model="exitData.date" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Produit (Réf ou Nom)</label>
            <div class="relative">
              <input type="text" v-model="productSearch" @input="handleSearch" class="w-full px-3 py-3 border border-gray-200 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="Rechercher un produit..." autocomplete="off">
              <div v-if="searchResults.length && showResults" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <div v-for="res in searchResults" :key="res.nid" @click="selectProduct(res)" class="px-4 py-3 hover:bg-gray-50 cursor-pointer flex items-center space-x-3">
                  <div class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg">
                    <i class="ri-smartphone-line text-gray-600"></i>
                  </div>
                  <div>
                    <div class="text-sm font-medium text-gray-900">{{ res.title }}</div>
                    <div class="text-xs text-gray-500">{{ res.field_sku || 'N/A' }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Quantité à Sortir</label>
            <input type="number" v-model="exitData.qty" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-red-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Raison / Note</label>
            <input type="text" v-model="exitData.reason" placeholder="Ex: Vente client" class="w-full px-3 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-red-500">
          </div>
          <div v-if="errorMessage" class="p-3 bg-red-50 border border-red-200 rounded-lg flex items-center space-x-2 text-red-600 text-sm">
            <i class="ri-error-warning-line"></i>
            <span>{{ errorMessage }}</span>
          </div>
        </div>
        <button @click="submitExit" class="w-full bg-red-500 text-white py-4 rounded-lg font-medium cursor-pointer">Valider la Sortie</button>
      </div>
    </main>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 w-full bg-white border-t border-gray-200 z-50 shadow-[0_-2px_10px_rgba(0,0,0,0.05)]">
      <div class="grid grid-cols-4 h-20">
        <button @click="showStock" :class="['flex flex-col items-center justify-center space-y-1.5 cursor-pointer transition-colors', currentSection.includes('stock') || currentSection.includes('Form') || currentSection === 'addProduct' ? 'text-blue-600' : 'text-gray-400']">
          <i class="ri-home-line text-2xl"></i>
          <span class="text-[11px] font-semibold uppercase tracking-wider">Accueil</span>
        </button>
        <router-link to="/products" class="flex flex-col items-center justify-center space-y-1.5 text-gray-400 transition-colors">
          <i class="ri-box-3-line text-2xl"></i>
          <span class="text-[11px] font-semibold uppercase tracking-wider">Produits</span>
        </router-link>
        <button @click="showStats" :class="['flex flex-col items-center justify-center space-y-1.5 cursor-pointer transition-colors', currentSection === 'stats' ? 'text-blue-600' : 'text-gray-400']">
          <i class="ri-bar-chart-line text-2xl"></i>
          <span class="text-[11px] font-semibold uppercase tracking-wider">Stats</span>
        </button>
        <button class="flex flex-col items-center justify-center space-y-1.5 text-gray-400 cursor-pointer transition-colors">
          <i class="ri-settings-3-line text-2xl"></i>
          <span class="text-[11px] font-semibold uppercase tracking-wider">Réglages</span>
        </button>
      </div>
    </nav>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 mx-4 max-w-sm w-full shadow-xl">
        <div class="text-center">
          <div class="w-16 h-16 flex items-center justify-center bg-green-100 rounded-full mx-auto mb-4">
            <i class="ri-check-line ri-2x text-green-600"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Opération Réussie</h3>
          <p class="text-sm text-gray-600 mb-6">{{ successMessage }}</p>
          <button @click="showSuccessModal = false" class="w-full bg-blue-600 text-white py-3 rounded-lg font-medium cursor-pointer">Continuer</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, watch } from 'vue';
import { useRouter } from 'vue-router';
import * as echarts from 'echarts';
import 'remixicon/fonts/remixicon.css';
import { storeToRefs } from 'pinia';
import { useProductStore } from '../stores/useProductStore';
import { useUIStore } from '../stores/useUIStore';

const router = useRouter();
const productStore = useProductStore();
const uiStore = useUIStore();
const { products, categories, loading } = storeToRefs(productStore);

const currentSection = ref('stock');
const showSuccessModal = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const chartRef = ref(null);
let chartInstance = null;
// Products and categories are now coming from productStore.

const recentMovements = ref([]);

const loadMovements = async () => {
  const rows = await productStore.fetchRecentMovements(6);
  recentMovements.value = rows.map(m => {
    // Extract name from title "Entrée/Sortie - Product Name"
    const nameParts = m.title.split(' - ');
    const displayName = nameParts.length > 1 ? nameParts[1] : m.title;
    
    return {
      name: displayName,
      ref: m.field_reason || 'N/A', // Use reason or something else for subtitle if SKU not handy
      qty: m.field_quantite,
      type: m.field_type,
      time: m.created ? new Date(m.created * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''
    };
  });
};

const entryData = ref({ date: new Date().toISOString().split('T')[0], ref: '', qty: 0, unit_price: 0, sale_price: 0, reason: '', product_nid: null, product_title: '' });
const exitData = ref({ date: new Date().toISOString().split('T')[0], ref: '', qty: 0, reason: '', product_nid: null, product_title: '' });
const productSearch = ref('');
const showResults = ref(false);
const searchResults = ref([]);
const generateRef = (categoryName = null) => {
  let prefix = 'REF';
  if (categoryName) {
    prefix = categoryName.substring(0, 3).toUpperCase().replace(/[^A-Z]/g, 'X');
  }
  const randomNum = Math.floor(100000 + Math.random() * 900000); // 6 random digits
  return `${prefix}-${randomNum}`;
};
const newProduct = ref({ name: '', ref: '', image: '', media_id: '', category: '', price: '', description: '' });

watch(() => newProduct.value.category, (newVal) => {
  if (newVal) {
    newProduct.value.ref = generateRef(newVal);
  } else {
    newProduct.value.ref = '';
  }
});

let searchTimer = null;
const handleSearch = () => {
  clearTimeout(searchTimer);
  if (productSearch.value.trim().length >= 2) {
    searchTimer = setTimeout(async () => {
      searchResults.value = await productStore.searchProducts(productSearch.value);
      showResults.value = true;
    }, 400);
  } else {
    searchResults.value = [];
    showResults.value = false;
  }
};

const selectProduct = (p) => {
  productSearch.value = `${p.title} - ${p.field_sku || 'N/A'}`;
  if (currentSection.value === 'entryForm') {
    entryData.value.ref = p.field_sku || p.ref;
    entryData.value.product_nid = p.nid;
    entryData.value.product_title = p.title;
    entryData.value.sale_price = p.field_prix_vente || 0;
  } else {
    exitData.value.ref = p.field_sku || p.ref;
    exitData.value.product_nid = p.nid;
    exitData.value.product_title = p.title;
    // For exit, we might want to know the buy price too for stats, but let's keep it simple
    exitData.value.sale_price = p.field_prix_vente || 0;
  }
  showResults.value = false;
};

const handleImageUpload = async (event) => {
  const file = event.target.files[0];
  if (file) {
    // Show local preview
    const reader = new FileReader();
    reader.onload = (e) => {
      newProduct.value.image = e.target.result;
    };
    reader.readAsDataURL(file);

    // Upload to server to get Media ID
    try {
      const response = await productStore.uploadImage(file);
      if (response && response.success) {
        newProduct.value.media_id = response.id;
        console.log("Image uploaded, Media ID:", response.id);
      } else {
        alert("Erreur lors du téléchargement de l'image sur le serveur.");
      }
    } catch (error) {
      console.error("Upload error:", error);
      alert("Une erreur est survenue lors du téléchargement de l'image.");
    }
  }
};

const updatePreviewImage = () => {
  if (!newProduct.value.media_id && newProduct.value.name.length > 2) {
    newProduct.value.image = `https://readdy.ai/api/search-image?query=icon%20product%20${encodeURIComponent(newProduct.value.name)}&width=200&height=200&orientation=squarish`;
  }
};

const addNewProduct = async () => {
  if (!newProduct.value.name || !newProduct.value.price) {
    alert("Veuillez remplir le nom du produit et le prix de vente.");
    return;
  }
  
  if (newProduct.value.name && newProduct.value.ref) {
    const response = await productStore.createProduct(newProduct.value);
    
    if (response.success) {
      const addedProduct = { ...newProduct.value };
      newProduct.value = { name: '', ref: '', image: '', media_id: '', category: '', price: '', description: '' };
      selectProduct(addedProduct);
      currentSection.value = 'entryForm';
      
      successMessage.value = "Le produit a été créé et synchronisé avec succès.";
      showSuccessModal.value = true;
    } else {
      alert("Erreur lors de la sauvegarde: " + response.message);
    }
  }
};

const openEntryForm = () => {
    productSearch.value = '';
    currentSection.value = 'entryForm';
};
const openExitForm = () => {
    productSearch.value = '';
    currentSection.value = 'exitForm';
};
const showStock = () => currentSection.value = 'stock';
const showInventory = () => currentSection.value = 'inventory';
const showStats = () => {
  currentSection.value = 'stats';
  nextTick(() => initChart());
};

const initChart = () => {
  if (chartRef.value) {
    if (chartInstance) chartInstance.dispose();
    chartInstance = echarts.init(chartRef.value);
    const option = {
      grid: { left: "10%", right: "10%", top: "10%", bottom: "15%" },
      xAxis: { type: "category", data: ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"] },
      yAxis: { type: "value" },
      series: [{
        data: [1200, 1350, 1180, 1420, 1380, 1250, 1320],
        type: "line",
        smooth: true,
        lineStyle: { color: "#2563eb", width: 3 },
        areaStyle: {
          color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
            { offset: 0, color: "rgba(37, 99, 235, 0.3)" },
            { offset: 1, color: "rgba(37, 99, 235, 0.05)" }
          ])
        }
      }]
    };
    chartInstance.setOption(option);
  }
};

const submitEntry = async () => {
  const p = products.value.find(prod => (prod.field_sku || prod.ref) === entryData.value.ref);
  if (p) {
    if (!p.field_quantite_disponible) p.field_quantite_disponible = 0;
    p.field_quantite_disponible = parseInt(p.field_quantite_disponible) + parseInt(entryData.value.qty);
    recentMovements.value.unshift({
      name: p.title,
      ref: p.field_sku || p.ref,
      qty: entryData.value.qty,
      type: 'in',
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    });

    // Save to Drupal API
    await productStore.recordStockMovement({
      product_nid: p.nid || entryData.value.product_nid,
      product_title: p.title,
      quantity: entryData.value.qty,
      unit_price: entryData.value.unit_price,
      sale_price: entryData.value.sale_price,
      date: entryData.value.date,
      reason: entryData.value.reason,
      type: 'in'
    });

    successMessage.value = "L'entrée de stock a été enregistrée avec succès.";
    showSuccessModal.value = true;
    loadMovements();
    showStock();
  }
};

const submitExit = async () => {
  errorMessage.value = '';
  const p = products.value.find(prod => (prod.field_sku || prod.ref) === exitData.value.ref);
  
  if (!p) {
    errorMessage.value = "Veuillez sélectionner un produit valide.";
    return;
  }

  const currentStock = parseInt(p.field_quantite_disponible || 0);
  if (currentStock < exitData.value.qty) {
    errorMessage.value = `Stock insuffisant. Disponible: ${currentStock}`;
    return;
  }

  p.field_quantite_disponible = currentStock - parseInt(exitData.value.qty);
  recentMovements.value.unshift({
    name: p.title,
    ref: p.field_sku || p.ref,
    qty: exitData.value.qty,
    type: 'out',
    time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  });

  // Save to Drupal API
  await productStore.recordStockMovement({
    product_nid: p.nid || exitData.value.product_nid,
    product_title: p.title,
    quantity: exitData.value.qty,
    unit_price: 0, 
    sale_price: 0,
    date: exitData.value.date,
    reason: exitData.value.reason,
    type: 'out'
  });

  successMessage.value = "La sortie de stock a été enregistrée avec succès.";
  showSuccessModal.value = true;
  loadMovements();
  showStock();
};

const getProductImage = (p) => {
  if (p.field_media_image && p.field_media_image.image && p.field_media_image.image.url) {
    return p.field_media_image.image.url;
  }
  if (p.field_images && p.field_images[0] && p.field_images[0].image && p.field_images[0].image.url) {
    return p.field_images[0].image.url;
  }
  return 'https://readdy.ai/api/search-image?query=icon%2C%20generic%20product&width=120&height=120';
};

onMounted(() => {
  productStore.fetchCategories();
  productStore.fetchProducts();
  loadMovements();
  if (currentSection.value === 'stats') initChart();
});

watch(currentSection, (newSection) => {
  if (newSection === 'stats') nextTick(() => initChart());
});
</script>

<style scoped>
/* Transposer les styles tailwind spécifiques ou globaux si nécessaire */
:deep(.ri-lg) {
  font-size: 1.25rem;
}
</style>
