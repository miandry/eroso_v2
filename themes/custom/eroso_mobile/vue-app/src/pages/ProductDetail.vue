<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <!-- Navigation Haute -->
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button @click="goBack" class="p-1 -ml-1 text-gray-600 cursor-pointer">
            <i class="ri-arrow-left-line ri-lg"></i>
          </button>
          <h1 class="text-lg font-semibold text-gray-900">Détails Produit</h1>
        </div>
        <div class="flex items-center space-x-2">
            <button @click="toggleEdit" class="w-8 h-8 flex items-center justify-center text-blue-600 bg-blue-50 rounded-lg cursor-pointer">
                <i :class="isEditing ? 'ri-eye-line' : 'ri-edit-line'"></i>
            </button>
        </div>
      </div>
    </nav>

    <main v-if="product" class="pt-16 pb-24 px-4">
      <div class="space-y-6">
        <!-- Image Section -->
        <div class="bg-white rounded-2xl shadow-sm p-4 border border-gray-100">
            <div class="relative group cursor-pointer" @click="!isEditing && toggleEdit()">
                <img 
                    :src="displayImage" 
                    class="w-full h-64 object-cover rounded-xl bg-gray-50 transition-all"
                    :class="{'opacity-50 blur-[2px]': uploadingImage, 'hover:brightness-90': !isEditing}"
                >
                <div v-if="uploadingImage" class="absolute inset-0 flex items-center justify-center bg-white/40 z-20">
                    <i class="ri-loader-4-line animate-spin text-3xl text-blue-600"></i>
                </div>
                
                <div v-if="!isEditing" class="absolute top-4 right-4 bg-white/80 backdrop-blur-sm p-2 rounded-lg text-blue-600 opacity-0 group-hover:opacity-100 transition-opacity">
                    <i class="ri-camera-switch-line ri-lg"></i>
                </div>

                <div v-if="isEditing" class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity rounded-xl cursor-pointer">
                    <i class="ri-camera-line text-white text-3xl"></i>
                    <input type="file" @change="handleImageUpload" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between">
                <div>
                   <h2 class="text-sm font-bold text-gray-400 uppercase tracking-widest">{{ product.field_sku || 'N/A' }}</h2>
                   <h1 class="text-xl font-black text-gray-900 leading-tight">{{ product.title }}</h1>
                </div>
                <div v-if="!isEditing" @click="toggleEdit" class="group flex items-center space-x-2 cursor-pointer">
                    <div class="text-2xl font-black text-blue-600 group-hover:text-blue-700 transition-colors">
                        {{ formatPrice(product.field_prix_vente) }} <span class="text-xs">Ar</span>
                    </div>
                    <i class="ri-edit-line text-blue-400 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                </div>
            </div>
        </div>

        <!-- Info Section -->
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100 space-y-6">
            <div v-if="isEditing" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Nom du Produit</label>
                    <input v-model="form.title" type="text" class="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Prix de Vente (Ar)</label>
                    <input v-model="form.field_prix_vente" type="number" class="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 font-bold text-blue-600 text-lg">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Description</label>
                    <textarea v-model="form.field_description" rows="4" class="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 text-sm"></textarea>
                </div>
                
                <button @click="handleSave" :disabled="loading" class="w-full bg-blue-600 text-white py-4 rounded-xl font-bold text-lg shadow-lg shadow-blue-100 active:scale-95 transition-all disabled:opacity-50">
                    {{ loading ? 'Enregistrement...' : 'Enregistrer les modifications' }}
                </button>
            </div>

            <div v-else class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-blue-50 rounded-2xl relative group">
                        <p class="text-[10px] font-bold text-blue-400 uppercase mb-1">Stock Disponible</p>
                        <div class="flex items-end justify-between">
                            <p class="text-xl font-black text-blue-700">{{ product.field_quantite_disponible || 0 }}</p>
                            <div class="flex space-x-1">
                                <button @click="openAdjustment('in')" class="w-6 h-6 bg-green-500 text-white rounded-lg flex items-center justify-center shadow-lg shadow-green-100 active:scale-90 transition-transform cursor-pointer">
                                    <i class="ri-add-line"></i>
                                </button>
                                <button @click="openAdjustment('out')" class="w-6 h-6 bg-red-500 text-white rounded-lg flex items-center justify-center shadow-lg shadow-red-100 active:scale-90 transition-transform cursor-pointer">
                                    <i class="ri-subtract-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 bg-orange-50 rounded-2xl">
                        <p class="text-[10px] font-bold text-orange-400 uppercase mb-1">Catégorie</p>
                        <p class="text-sm font-black text-orange-700 truncate">{{ getCategoryName(product) || 'Standard' }}</p>
                    </div>
                </div>

                <div v-if="product.field_description || true">
                    <div class="flex items-center justify-between mb-3 group cursor-pointer" @click="toggleEdit">
                        <h3 class="text-xs font-bold text-gray-400 uppercase">Description</h3>
                        <i class="ri-edit-line text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                    </div>
                    <div class="text-sm text-gray-600 leading-relaxed italic" v-html="product.field_description || 'Aucune description disponible.'"></div>
                </div>

                <div class="pt-4 border-t border-gray-50 flex items-center justify-between text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                   <span>Dernière modification: {{ formatDate(product.changed) }}</span>
                   <span class="flex items-center"><i class="ri-user-line mr-1"></i> {{ product.author || 'Admin' }}</span>
                </div>
            </div>
        </div>

        <!-- Stock History Section -->
        <div v-if="!isEditing" class="space-y-4">
            <div class="flex items-center justify-between px-2">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Historique des Flux</h3>
                <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">{{ movements.length }} Opérations</span>
            </div>

            <div v-if="movements.length > 0" class="space-y-3">
                <div v-for="m in movements" :key="m.nid" class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div :class="['w-10 h-10 rounded-xl flex items-center justify-center', m.field_type === 'in' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600']">
                            <i :class="m.field_type === 'in' ? 'ri-arrow-right-down-line' : 'ri-arrow-left-up-line'" class="ri-lg"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-900">{{ m.field_type === 'in' ? 'Entrée de Stock' : 'Sortie de Stock' }}</p>
                            <p class="text-[10px] text-gray-400 font-medium">{{ formatDate(m.created, true) }} • {{ m.author || 'Admin' }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p :class="['text-base font-black', m.field_type === 'in' ? 'text-green-600' : 'text-red-600']">
                            {{ m.field_type === 'in' ? '+' : '-' }}{{ m.field_quantite }}
                        </p>
                        <p class="text-[8px] font-bold text-gray-300 uppercase tracking-tighter truncate max-w-[80px]">{{ m.field_raison || 'Standard' }}</p>
                    </div>
                </div>
            </div>
            
            <div v-else class="bg-white/50 border-2 border-dashed border-gray-100 rounded-2xl py-8 flex flex-col items-center justify-center">
                <i class="ri-history-line text-3xl text-gray-200 mb-2"></i>
                <p class="text-xs text-gray-400 font-bold uppercase tracking-widest">Aucun historique</p>
            </div>
        </div>
      </div>
    </main>

    <!-- Adjustment Modal -->
    <div v-if="showAdjustmentModal" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm p-0 sm:p-4">
        <div class="bg-white w-full max-w-lg rounded-t-3xl sm:rounded-3xl p-6 space-y-6 animate-in slide-in-from-bottom duration-300">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div :class="['w-10 h-10 rounded-xl flex items-center justify-center', adjustmentForm.type === 'in' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600']">
                        <i :class="adjustmentForm.type === 'in' ? 'ri-add-line' : 'ri-subtract-line'" class="ri-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-gray-900">{{ adjustmentForm.type === 'in' ? 'Entrée de Stock' : 'Sortie de Stock' }}</h3>
                        <p class="text-xs text-gray-400 font-bold uppercase tracking-widest">{{ product.title }}</p>
                    </div>
                </div>
                <button @click="showAdjustmentModal = false" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line ri-2x"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Quantité</label>
                    <input v-model="adjustmentForm.quantity" type="number" class="w-full px-5 py-4 bg-gray-50 border-none rounded-2xl focus:ring-2 focus:ring-blue-500 text-xl font-black text-gray-900" placeholder="0">
                </div>
                <div v-if="adjustmentForm.type === 'in'" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Prix d'Achat (Ar)</label>
                        <input v-model="adjustmentForm.unit_price" type="number" class="w-full px-5 py-4 bg-gray-50 border-none rounded-2xl focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Prix de Vente (Ar)</label>
                        <input v-model="adjustmentForm.sale_price" type="number" class="w-full px-5 py-4 bg-gray-50 border-none rounded-2xl focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Raison / Commentaire</label>
                    <input v-model="adjustmentForm.reason" type="text" class="w-full px-5 py-4 bg-gray-50 border-none rounded-2xl focus:ring-2 focus:ring-blue-500 font-medium" :placeholder="adjustmentForm.type === 'in' ? 'Réapprovisionnement, etc.' : 'Vente, casse, etc.'">
                </div>
            </div>

            <div v-if="adjustmentError" class="p-4 bg-red-50 text-red-600 text-xs font-bold rounded-2xl flex items-center">
                <i class="ri-error-warning-line mr-2"></i>
                {{ adjustmentError }}
            </div>

            <button @click="submitAdjustment" :disabled="loadingAdjustment" class="w-full py-4 rounded-2xl font-black text-lg transition-all active:scale-95 disabled:opacity-50" :class="adjustmentForm.type === 'in' ? 'bg-green-600 text-white shadow-lg shadow-green-100' : 'bg-red-600 text-white shadow-lg shadow-red-100'">
                {{ loadingAdjustment ? 'Traitement...' : 'Confirmer l\'opération' }}
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div v-else-if="loading" class="flex flex-col items-center justify-center h-screen space-y-4">
        <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-gray-500 font-medium">Récupération des données...</p>
    </div>

    <!-- 404 State -->
    <div v-else class="flex flex-col items-center justify-center h-screen px-10 text-center">
        <i class="ri-error-warning-line text-6xl text-gray-200 mb-4"></i>
        <h2 class="text-xl font-bold text-gray-900">Produit introuvable</h2>
        <p class="text-gray-500 mt-2">Le produit que vous recherchez n'existe pas ou a été supprimé.</p>
        <button @click="router.push('/products')" class="mt-6 px-8 py-3 bg-blue-600 text-white rounded-xl font-bold">Retour au catalogue</button>
    </div>

    <!-- Success Feedback Overlay -->
    <div v-if="showSuccess" class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-black/20 pointer-events-none">
        <div class="bg-white rounded-3xl p-6 shadow-2xl flex items-center space-x-4 animate-in fade-in zoom-in duration-300">
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="ri-check-line text-2xl text-green-600"></i>
            </div>
            <p class="font-bold text-gray-900">Modifications enregistrées !</p>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useProductStore } from '../stores/useProductStore';
import { proxyImage } from '../services/image';
import { storeToRefs } from 'pinia';

const route = useRoute();
const router = useRouter();
const productStore = useProductStore();
const { loading } = storeToRefs(productStore);

const product = ref(null);
const movements = ref([]);
const isEditing = ref(false);
const uploadingImage = ref(false);
const showSuccess = ref(false);

// Stock Adjustment State
const showAdjustmentModal = ref(false);
const loadingAdjustment = ref(false);
const adjustmentError = ref('');
const adjustmentForm = ref({
    type: 'in', // 'in' or 'out'
    quantity: 0,
    unit_price: 0,
    sale_price: 0,
    reason: ''
});

const form = ref({
    title: '',
    field_prix_vente: 0,
    field_description: '',
    media_id: ''
});

const displayImage = computed(() => {
    if (form.value.localImage) return form.value.localImage;
    return getProductImage(product.value);
});

const getProductImage = (p) => {
  if (!p) return '';
  let url = '';
  if (p.field_media_image && p.field_media_image.image && p.field_media_image.image.url) {
    url = p.field_media_image.image.url;
  } else if (p.field_images && p.field_images[0] && p.field_images[0].image && p.field_images[0].image.url) {
    url = p.field_images[0].image.url;
  } else {
    url = 'https://readdy.ai/api/search-image?query=icon%2C%20generic%20product';
  }
  return proxyImage(url, { w: 400, h: 400, fit: 'cover' });
};

const getCategoryName = (p) => {
  if (!p) return null;
  if (p.field_category && p.field_category.title) return p.field_category.title;
  if (Array.isArray(p.field_category) && p.field_category.length > 0) return p.field_category[0].title;
  return null;
};

const formatPrice = (price) => {
  if (!price) return '0';
  return Number(price).toLocaleString('fr-MG');
};

const formatDate = (timestamp, includeTime = false) => {
  if (!timestamp) return 'Jamais';
  const date = isNaN(timestamp) ? new Date(timestamp) : new Date(timestamp * 1000);
  
  if (includeTime) {
    return date.toLocaleString('fr-FR', { 
        day: 'numeric', 
        month: 'short', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
  }
  
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
};

const loadProduct = async () => {
    const id = route.params.id;
    // Try to get from store first
    let p = productStore.getProductById(id);
    if (!p) {
        p = await productStore.fetchProductDetail(id);
    }
    product.value = p;
    
    if (p) {
        form.value = {
            title: p.title,
            field_prix_vente: p.field_prix_vente,
            field_description: p.field_description || '',
            localImage: null,
            media_id: ''
        };
        // Load stock movements
        movements.value = await productStore.fetchProductMovements(id);
    }
};

const toggleEdit = () => {
    isEditing.value = !isEditing.value;
};

const openAdjustment = (type) => {
    adjustmentForm.value = {
        type: type,
        quantity: 0,
        unit_price: 0,
        sale_price: product.value.field_prix_vente || 0,
        reason: ''
    };
    adjustmentError.value = '';
    showAdjustmentModal.value = true;
};

const submitAdjustment = async () => {
    if (adjustmentForm.value.quantity <= 0) {
        adjustmentError.value = "La quantité doit être supérieure à 0.";
        return;
    }

    if (adjustmentForm.value.type === 'out' && parseInt(product.value.field_quantite_disponible || 0) < adjustmentForm.value.quantity) {
        adjustmentError.value = "Stock insuffisant pour cette sortie.";
        return;
    }

    loadingAdjustment.value = true;
    adjustmentError.value = '';

    try {
        const result = await productStore.recordStockMovement({
            product_nid: product.value.nid,
            product_title: product.value.title,
            quantity: adjustmentForm.value.quantity,
            unit_price: adjustmentForm.value.unit_price,
            sale_price: adjustmentForm.value.sale_price,
            date: new Date().toISOString().split('T')[0],
            reason: adjustmentForm.value.reason,
            type: adjustmentForm.value.type
        });

        if (result.success) {
            showAdjustmentModal.value = false;
            // Success feedback already exists for edits, maybe we can reuse or just reload
            showSuccess.value = true;
            setTimeout(() => {
                showSuccess.value = false;
                loadProduct(); // Refresh data and history
            }, 1000);
        } else {
            adjustmentError.value = "Erreur lors de l'enregistrement.";
        }
    } catch (e) {
        console.error(e);
        adjustmentError.value = "Une erreur est survenue.";
    } finally {
        loadingAdjustment.value = false;
    }
};

const handleImageUpload = async (event) => {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            form.value.localImage = e.target.result;
        };
        reader.readAsDataURL(file);

        uploadingImage.value = true;
        try {
            const result = await productStore.uploadImage(file);
            if (result.success) {
                form.value.media_id = result.id;
            } else {
                alert("Erreur de téléchargement");
            }
        } catch (e) {
            console.error(e);
        } finally {
            uploadingImage.value = false;
        }
    }
};

const handleSave = async () => {
    const result = await productStore.updateProduct(product.value.nid, form.value);
    if (result.success) {
        showSuccess.value = true;
        setTimeout(() => {
            showSuccess.value = false;
            isEditing.value = false;
            loadProduct();
        }, 1500);
    } else {
        alert(result.message);
    }
};

const goBack = () => router.back();

onMounted(loadProduct);
</script>

<style scoped>
.animate-in {
    animation: animate-in 0.3s ease-out;
}
@keyframes animate-in {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
</style>
