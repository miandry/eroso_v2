<template>
  <div class="bg-gray-50 font-sans min-h-screen pb-24 lg:pb-4">
    <!-- Toast Notification -->
    <transition name="slide-down">
      <div v-if="showToast" class="fixed top-20 left-1/2 -translate-x-1/2 z-50 rounded-xl shadow-lg p-4 max-w-sm w-full mx-4" :class="toastType === 'error' ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'">
        <div class="flex items-start space-x-3">
          <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" :class="toastType === 'error' ? 'bg-red-500' : 'bg-green-500'">
            <i :class="toastType === 'error' ? 'ri-error-warning-line text-white' : 'ri-check-line text-white'"></i>
          </div>
          <div class="flex-1">
            <h4 class="text-sm font-bold" :class="toastType === 'error' ? 'text-red-900' : 'text-green-900'">{{ toastType === 'error' ? 'Stock insuffisant' : 'Ajouté au panier !' }}</h4>
            <p class="text-xs mt-1" :class="toastType === 'error' ? 'text-red-700' : 'text-green-700'">{{ toastMessage }}</p>
          </div>
        </div>
      </div>
    </transition>

    <!-- Navigation Haute -->
    <nav class="fixed top-0 left-0 right-0 bg-white shadow-sm z-40 lg:ml-64 lg:mr-96">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button @click="uiStore.toggleSidebar" class="p-1 -ml-1 text-gray-600 cursor-pointer lg:hidden">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <div>
            <p class="text-sm font-semibold text-gray-900">Caisse locale</p>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <button @click="productStore.fetchProducts(false)" class="w-8 h-8 flex items-center justify-center text-blue-600 bg-blue-50 rounded-lg cursor-pointer">
            <i class="ri-refresh-line"></i>
          </button>
        </div>
      </div>
    </nav>

    <main class="pt-16 lg:ml-64 lg:mr-96">
      <div class="p-4">
        <!-- Search Bar -->
        <div class="mb-4 space-y-3">
          <div class="flex space-x-2">
            <select 
              v-model="searchType" 
              class="px-3 py-3 bg-white border border-gray-200 rounded-xl shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="title">Titre</option>
              <option value="sku">Référence (SKU)</option>
            </select>
            <div class="relative flex-1">
              <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              <input 
                v-model="searchQuery" 
                type="text" 
                :placeholder="searchType === 'sku' ? 'Rechercher par référence...' : 'Rechercher un produit...'" 
                class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading && availableProducts.length === 0" class="flex flex-col items-center justify-center py-20 space-y-4">
          <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
          <p class="text-sm text-gray-500 font-medium">Chargement des produits...</p>
        </div>

        <!-- Product Grid -->
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3 mb-4">
          <div 
            v-for="product in filteredProducts" 
            :key="product.nid"
            @click="addToOrder(product)"
            class="bg-white rounded-2xl shadow-sm overflow-hidden cursor-pointer transition-all active:scale-95"
          >
            <!-- Product Image -->
            <div class="relative aspect-square bg-gray-100">
              <img 
                :src="getProductImage(product)" 
                :alt="product.title" 
                class="w-full h-full object-cover"
                loading="lazy"
              >
              <button 
                @click.stop="router.push('/product/' + product.nid)"
                class="absolute bottom-1.5 right-1.5 w-7 h-7 bg-white/80 backdrop-blur-sm rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors shadow-sm"
              >
                <i class="ri-eye-line text-sm"></i>
              </button>
            </div>

            <!-- Product Info -->
            <div class="p-3">
              <h3 class="text-sm font-bold text-gray-900 mb-1 line-clamp-1">{{ product.title }}</h3>
              <p class="text-xs text-gray-500 mb-1">Réf: {{ product.field_sku || 'N/A' }}</p>
              <span 
                class="text-[10px] inline-flex items-center px-1.5 py-0.5 rounded-md font-bold uppercase tracking-tight mb-1"
                :class="parseInt(product.field_quantite_disponible || 0) > 5 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
              >
                Stock: {{ product.field_quantite_disponible || 0 }}
              </span>
              
              <!-- Price -->
              <div class="text-lg font-black text-blue-600">
                {{ formatPrice(product.field_prix_vente || product.field_price) }} Ar
              </div>
            </div>
          </div>
        </div>

        <!-- Load More Button -->
        <div v-if="hasMore && !loading" class="mt-4 text-center mb-4">
          <button 
            @click="loadMore"
            class="px-6 py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition-colors"
          >
            Voir plus
          </button>
        </div>
      </div>
    </main>

    <!-- Bottom Order Panel (Mobile only) -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 lg:hidden safe-bottom">
      <!-- Collapsed Header -->
      <div 
        @click="toggleOrderPanel"
        class="flex items-center justify-between px-4 py-3 cursor-pointer"
      >
        <div class="flex items-center space-x-3">
          <h3 class="text-base font-bold text-gray-900">Vente locale</h3>
          <span class="text-xs text-blue-600 font-semibold">{{ orderItems.length }} articles</span>
        </div>
        <i :class="['text-xl transition-transform', isOrderPanelOpen ? 'ri-arrow-down-s-line' : 'ri-arrow-up-s-line']"></i>
      </div>

      <!-- Expanded Panel -->
      <transition name="slide-up">
        <div v-if="isOrderPanelOpen" class="max-h-[70vh] overflow-y-auto border-t border-gray-100">
          <!-- Order Items -->
          <div class="p-4">
            <div v-if="orderItems.length === 0" class="text-center py-8">
              <i class="ri-shopping-cart-line text-4xl text-gray-300 mb-2"></i>
              <p class="text-sm text-gray-500">Aucun article sélectionné</p>
            </div>

            <div v-else class="space-y-3">
              <div 
                v-for="(item, index) in orderItems" 
                :key="index"
                class="flex items-center space-x-3 bg-white rounded-lg p-3 border border-gray-100"
              >
                <button 
                  @click="removeItem(index)"
                  class="text-red-500 hover:text-red-700 flex-shrink-0"
                >
                  <i class="ri-delete-bin-line text-lg"></i>
                </button>
                <div class="flex-1 min-w-0">
                  <h4 class="text-sm font-semibold text-gray-900 truncate">{{ item.product.title }}</h4>
                  <p class="text-xs text-gray-600">{{ formatPrice(item.product.field_prix_vente) }} Ar chacun</p>
                </div>
                <div class="flex items-center space-x-2 flex-shrink-0">
                  <button 
                    @click="decreaseQuantity(index)"
                    class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg hover:bg-gray-200"
                  >
                    <i class="ri-subtract-line text-sm"></i>
                  </button>
                  <span class="text-sm font-bold w-8 text-center">{{ item.quantity }}</span>
                  <button 
                    @click="increaseQuantity(index)"
                    class="w-8 h-8 flex items-center justify-center bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                  >
                    <i class="ri-add-line text-sm"></i>
                  </button>
                </div>
                <div class="text-right flex-shrink-0">
                  <div class="text-sm font-bold text-blue-600">{{ formatPrice(item.product.field_prix_vente * item.quantity) }} Ar</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Order Summary & Actions -->
          <div class="p-4 border-t border-gray-200 bg-gray-50 space-y-3">
            <!-- Notes -->
            <div class="mb-3">
              <label class="block text-xs font-semibold text-gray-700 mb-2">Notes</label>
              <textarea 
                v-model="orderNotes"
                rows="3"
                placeholder="Ajouter des notes pour cette vente..."
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
              ></textarea>
            </div>
            
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Sous-total</span>
              <span class="font-semibold">{{ formatPrice(subtotal) }} Ar</span>
            </div>
            <div class="flex justify-between text-lg font-bold">
              <span>Total</span>
              <span class="text-blue-600">{{ formatPrice(subtotal) }} Ar</span>
            </div>
            <button 
              @click="finalizeOrder"
              :disabled="savingOrder || orderItems.length === 0"
              :class="[
                'w-full py-3 rounded-xl font-bold text-white transition-colors flex items-center justify-center',
                savingOrder || orderItems.length === 0
                  ? 'bg-gray-300 cursor-not-allowed'
                  : 'bg-green-600 hover:bg-green-700 cursor-pointer'
              ]"
            >
              <div v-if="savingOrder" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
              <i v-else class="ri-check-line mr-2"></i>
              {{ savingOrder ? 'Enregistrement...' : 'Sauvegarder' }}
            </button>
          </div>
        </div>
      </transition>
    </div>

    <!-- Desktop Sidebar Order Panel -->
    <div class="hidden lg:block fixed right-0 top-16 w-96 h-[calc(100vh-4rem)] bg-white border-l border-gray-200 overflow-y-auto">
        <div class="h-full flex flex-col">
          <!-- Header -->
          <div class="bg-gradient-to-r from-green-600 to-green-700 text-white p-4">
            <div class="flex items-center justify-between mb-2">
              <h2 class="text-lg font-bold">Vente locale</h2>
              <button 
                v-if="orderItems.length > 0"
                @click="clearOrder"
                class="text-xs underline hover:text-green-200"
              >
                Tout effacer
              </button>
            </div>
          </div>

          <!-- Order Items -->
          <div class="flex-1 overflow-y-auto p-4">
            <div v-if="orderItems.length === 0" class="text-center py-8">
              <i class="ri-shopping-cart-line text-4xl text-gray-300 mb-2"></i>
              <p class="text-sm text-gray-500">Aucun article sélectionné</p>
            </div>

            <div v-else class="space-y-3">
              <div 
                v-for="(item, index) in orderItems" 
                :key="index"
                class="flex items-center space-x-3 bg-gray-50 rounded-lg p-3"
              >
                <img 
                  :src="getProductImage(item.product)" 
                  :alt="item.product.title" 
                  class="w-12 h-12 rounded-lg object-cover"
                >
                <div class="flex-1 min-w-0">
                  <h4 class="text-sm font-semibold text-gray-900 truncate">{{ item.product.title }}</h4>
                  <p class="text-xs text-gray-600">{{ formatPrice(item.product.field_prix_vente) }} Ar</p>
                </div>
                <div class="flex items-center space-x-2">
                  <button 
                    @click="decreaseQuantity(index)"
                    class="w-7 h-7 flex items-center justify-center bg-gray-200 rounded-full hover:bg-gray-300"
                  >
                    <i class="ri-subtract-line text-sm"></i>
                  </button>
                  <span class="text-sm font-bold w-8 text-center">{{ item.quantity }}</span>
                  <button 
                    @click="increaseQuantity(index)"
                    class="w-7 h-7 flex items-center justify-center bg-blue-600 text-white rounded-full hover:bg-blue-700"
                  >
                    <i class="ri-add-line text-sm"></i>
                  </button>
                </div>
                <button 
                  @click="removeItem(index)"
                  class="text-red-500 hover:text-red-700"
                >
                  <i class="ri-delete-bin-line"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Order Summary -->
          <div class="p-4 border-t border-gray-200 bg-gray-50">
            <!-- Notes -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-gray-700 mb-2">Notes</label>
              <textarea 
                v-model="orderNotes"
                rows="3"
                placeholder="Ajouter des notes pour cette vente..."
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
              ></textarea>
            </div>
            
            <div class="space-y-2 mb-4">
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Sous-total</span>
                <span class="font-semibold">{{ formatPrice(subtotal) }} Ar</span>
              </div>
              <div class="flex justify-between text-lg font-bold">
                <span>Total</span>
                <span class="text-blue-600">{{ formatPrice(subtotal) }} Ar</span>
              </div>
            </div>

            <button 
              @click="finalizeOrder"
              :disabled="savingOrder || orderItems.length === 0"
              :class="[
                'w-full py-3 rounded-xl font-bold text-white transition-colors flex items-center justify-center',
                savingOrder || orderItems.length === 0
                  ? 'bg-gray-300 cursor-not-allowed'
                  : 'bg-green-600 hover:bg-green-700 cursor-pointer'
              ]"
            >
              <div v-if="savingOrder" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
              <i v-else class="ri-check-line mr-2"></i>
              {{ savingOrder ? 'Enregistrement...' : orderItems.length === 0 ? 'Panier vide' : 'Finaliser la vente locale' }}
            </button>
          </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="ri-check-line text-3xl text-green-600"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Vente locale enregistrée!</h3>
        <p class="text-gray-600 mb-6">La vente locale a été créée avec succès.</p>
        <button 
          @click="closeSuccessModal"
          class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700"
        >
          Nouvelle vente
        </button>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useProductStore } from '../stores/useProductStore';
import { useUIStore } from '../stores/useUIStore';
import { proxyImage } from '../services/image';
import { saveOrderLocal } from '../services/api';

const router = useRouter();
const productStore = useProductStore();
const uiStore = useUIStore();
const { products, loading, hasMore } = storeToRefs(productStore);

const searchQuery = ref('');
const searchType = ref('title');
const orderItems = ref([]);
const showSuccessModal = ref(false);
const isOrderPanelOpen = ref(false);
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('success');
const searchTimeout = ref(null);
const savingOrder = ref(false);
const orderNotes = ref('');

// Filter products to show only available ones.
// Some APIs return different status labels (dispo/disponible/empty),
// so we primarily rely on positive stock.
const availableProducts = computed(() => {
  return products.value.filter(product => {
    const status = (product.field_status || '').toString().toLowerCase().trim();
    const stock = parseFloat(product.field_quantite_disponible || 0);
    const isStatusAvailable = !status || status === 'dispo' || status === 'disponible';
    return stock > 0 && isStatusAvailable;
  });
});

// Use availableProducts directly (search is now handled by API)
const filteredProducts = computed(() => availableProducts.value);

// Calculate subtotal
const subtotal = computed(() => {
  return orderItems.value.reduce((total, item) => {
    const price = parseFloat(item.product.field_prix_vente || item.product.field_price || 0);
    return total + (price * item.quantity);
  }, 0);
});

const getProductImage = (p) => {
  let url = '';
  if (p.field_media_image && p.field_media_image.image && p.field_media_image.image.url) {
    url = p.field_media_image.image.url;
  } else if (p.field_images && p.field_images[0] && p.field_images[0].image && p.field_images[0].image.url) {
    url = p.field_images[0].image.url;
  } else {
    url = 'https://readdy.ai/api/search-image?query=icon%2C%20generic%20product';
  }
  
  return proxyImage(url, { w: 200, h: 200, fit: 'cover' });
};

const formatPrice = (price) => {
  if (!price) return '0';
  return Number(price).toLocaleString('fr-MG');
};

const toggleOrderPanel = () => {
  isOrderPanelOpen.value = !isOrderPanelOpen.value;
};

const addToOrder = (product) => {
  const stock = parseInt(product.field_quantite_disponible || 0);
  const existingItem = orderItems.value.find(item => item.product.nid === product.nid);
  const currentQty = existingItem ? existingItem.quantity : 0;

  if (stock <= 0 || currentQty >= stock) {
    toastType.value = 'error';
    toastMessage.value = `Stock insuffisant pour ${product.title} (dispo: ${stock})`;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 3000);
    return;
  }

  if (existingItem) {
    existingItem.quantity++;
  } else {
    orderItems.value.push({
      product: product,
      quantity: 1
    });
  }

  // Show toast notification
  toastType.value = 'success';
  toastMessage.value = product.title;
  showToast.value = true;
  setTimeout(() => {
    showToast.value = false;
  }, 3000);
};

const increaseQuantity = (index) => {
  const item = orderItems.value[index];
  const stock = parseInt(item.product.field_quantite_disponible || 0);
  if (item.quantity >= stock) {
    toastType.value = 'error';
    toastMessage.value = `Stock max atteint pour ${item.product.title} (dispo: ${stock})`;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 3000);
    return;
  }
  item.quantity++;
};

const decreaseQuantity = (index) => {
  if (orderItems.value[index].quantity > 1) {
    orderItems.value[index].quantity--;
  } else {
    removeItem(index);
  }
};

const removeItem = (index) => {
  orderItems.value.splice(index, 1);
};

const clearOrder = () => {
  orderItems.value = [];
};

const finalizeOrder = async () => {
  if (orderItems.value.length === 0 || savingOrder.value) return;

  savingOrder.value = true;

  try {
    const payload = {
      items: orderItems.value.map(item => ({
        product_nid: item.product.nid,
        quantity: item.quantity,
        prix_unitaire: parseFloat(item.product.field_prix_vente || item.product.field_price || 0),
      })),
      notes: orderNotes.value,
    };

    const response = await saveOrderLocal(payload);
    
    if (response.data.status === true) {
      // Update local product stock from API response
      if (response.data.updated_products) {
        response.data.updated_products.forEach(updated => {
          const product = products.value.find(p => p.nid == updated.nid);
          if (product) {
            product.field_quantite_disponible = updated.new_stock;
          }
        });
      }

      // Save to localStorage for offline access
      const order = {
        id: response.data.order_id,
        items: orderItems.value.map(item => ({
          product_id: item.product.nid,
          product_name: item.product.title,
          quantity: item.quantity,
          price: item.product.field_prix_vente || item.product.field_price
        })),
        total: response.data.total || subtotal.value,
        date: new Date().toISOString(),
        status: 'en_cours'
      };

      const savedOrders = JSON.parse(localStorage.getItem('orders_local') || '[]');
      savedOrders.unshift(order);
      localStorage.setItem('orders_local', JSON.stringify(savedOrders));

      // Show success modal
      showSuccessModal.value = true;
    } else {
      alert(response.data.message || 'Erreur lors de la création de la vente locale');
    }
  } catch (error) {
    console.error('Error creating local order:', error);
    const msg = error.response?.data?.message || error.response?.data?.errors?.join('\n') || 'Erreur lors de la création de la vente locale';
    alert(msg);
  } finally {
    savingOrder.value = false;
  }
};

const closeSuccessModal = () => {
  showSuccessModal.value = false;
  orderItems.value = [];
};

const performSearch = () => {
  // Clear existing timeout
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }

  // Debounce search by 500ms
  searchTimeout.value = setTimeout(() => {
    const filters = {};
    
    if (searchQuery.value && searchQuery.value.length >= 2) {
      filters.search = searchQuery.value;
      filters.searchType = searchType.value;
    }
    
    productStore.fetchProducts(false, filters);
  }, 500);
};

const loadMore = () => {
  const filters = {};
  
  if (searchQuery.value && searchQuery.value.length >= 2) {
    filters.search = searchQuery.value;
    filters.searchType = searchType.value;
  }
  
  productStore.fetchProducts(true, filters);
};

// Watch for search changes
watch([searchQuery, searchType], () => {
  performSearch();
});

onMounted(() => {
  productStore.fetchProducts(false);
});
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.3s ease;
}

.slide-up-enter-from {
  transform: translateY(100%);
  opacity: 0;
}

.slide-up-leave-to {
  transform: translateY(100%);
  opacity: 0;
}

.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.3s ease;
}

.slide-down-enter-from {
  transform: translateY(-100%);
  opacity: 0;
}

.slide-down-leave-to {
  transform: translateY(-100%);
  opacity: 0;
}

.safe-bottom {
  padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
