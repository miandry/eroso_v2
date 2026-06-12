<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button type="button" @click="goBack" class="p-1 -ml-1 text-gray-600 cursor-pointer">
            <i class="ri-arrow-left-line ri-lg"></i>
          </button>
          <h1 class="text-lg font-semibold text-gray-900">Nouveau produit (sur commande)</h1>
        </div>
      </div>
    </nav>

    <main class="pt-16 pb-24 px-4 lg:ml-64">
      <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm p-6 space-y-6 border border-indigo-50">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">Image du produit</label>
            <div class="flex items-center space-x-6">
              <div class="w-32 h-32 bg-gray-100 rounded-2xl flex items-center justify-center overflow-hidden border-2 border-dashed border-gray-300 relative group">
                <img v-if="newProduct.image" :src="formatPreviewImage(newProduct.image)" class="w-full h-full object-cover" :class="{ 'opacity-50': uploadingImage }">
                <div v-if="uploadingImage" class="absolute inset-0 flex items-center justify-center bg-white/60 z-20">
                  <i class="ri-loader-4-line animate-spin text-2xl text-indigo-600"></i>
                </div>
                <i v-else-if="!newProduct.image" class="ri-image-add-line text-3xl text-gray-400"></i>
                <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                  <i class="ri-camera-line text-white text-2xl"></i>
                </div>
                <input type="file" @change="handleImageUpload" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10" :disabled="uploadingImage">
              </div>
              <div class="flex-1 space-y-2">
                <p class="text-sm font-medium text-gray-900">Photo du produit</p>
                <p class="text-xs text-gray-500">
                  <span v-if="uploadingImage" class="text-indigo-600 font-medium">Téléchargement en cours…</span>
                  <span v-else>Cliquez sur le cadre pour télécharger une photo. JPG, PNG.</span>
                </p>
                <button v-if="newProduct.image && !uploadingImage" type="button" class="text-xs text-red-500 font-medium" @click="newProduct.image = ''; newProduct.media_id = ''" >Supprimer la photo</button>
              </div>
            </div>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Catégorie <span class="text-red-500">*</span></label>
              <select v-model="newProduct.category" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-indigo-600 bg-white">
                <option value="">Sélectionner une catégorie</option>
                <option v-for="cat in categories" :key="cat.tid" :value="cat.name">{{ cat.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nom du produit <span class="text-red-500">*</span></label>
              <input v-model="newProduct.name" type="text" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Ex: Produit sur commande">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Référence / SKU</label>
              <div class="relative">
                <input v-model="newProduct.ref" type="text" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-indigo-600 bg-gray-50" readonly placeholder="Généré automatiquement">
                <i class="ri-lock-line absolute right-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
              </div>
              <p class="text-[10px] text-gray-500 mt-1 italic">La référence est générée selon la catégorie choisie.</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Prix de vente (Ar) <span class="text-red-500">*</span></label>
              <input v-model="newProduct.price" type="number" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="0">
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Quantité</label>
                <input v-model.number="newProduct.stock" type="number" min="0" step="1" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="0">
                <p class="text-[10px] text-gray-500 mt-1">Stock initial (optionnel).</p>
              </div>
             <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Prix d’achat (Ar)</label>
                <input v-model.number="newProduct.purchase_price" type="number" min="0" step="1" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="0">
                <p class="text-[10px] text-gray-500 mt-1">Coût unitaire (optionnel).</p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Lien Taobao / externe</label>
              <input v-model="newProduct.taobao_url" type="url" inputmode="url" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="https://item.taobao.com/…">
              <p class="text-[10px] text-gray-500 mt-1">Optionnel — lien vers la fiche fournisseur.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea v-model="newProduct.description" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Détails du produit…"></textarea>
            </div>
          </div>

          <div class="pt-4">
            <button @click="addNewProduct" :disabled="loading" class="w-full bg-indigo-600 text-white py-4 rounded-xl font-bold text-lg shadow-lg shadow-indigo-100 active:scale-[0.98] transition-all disabled:opacity-50 disabled:active:scale-100 flex items-center justify-center space-x-2">
              <i v-if="loading" class="ri-loader-4-line animate-spin"></i>
              <span>{{ loading ? 'Enregistrement…' : 'Enregistrer (product_commande)' }}</span>
            </button>
          </div>
        </div>
      </div>
    </main>

    <div v-if="showSuccessModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-6">
      <div class="bg-white rounded-3xl p-8 w-full max-w-sm text-center space-y-6">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto">
          <i class="ri-checkbox-circle-fill text-4xl text-green-600"></i>
        </div>
        <div>
          <h3 class="text-xl font-bold text-gray-900">Produit ajouté</h3>
          <p class="text-gray-500 mt-2">{{ successMessage }}</p>
        </div>
        <button type="button" class="w-full bg-indigo-600 text-white py-4 rounded-xl font-bold" @click="finish">Voir le catalogue</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useProductStore } from '../../stores/useProductStore';
import { storeToRefs } from 'pinia';
import { proxyImage } from '../../services/image';

const BUNDLE = 'product_commande';

const router = useRouter();
const productStore = useProductStore();
const { categories, loading } = storeToRefs(productStore);

const newProduct = ref({
  name: '',
  ref: '',
  image: '',
  media_id: '',
  category: '',
  price: '',
  taobao_url: '',
  description: '',
  stock: 0,
  purchase_price: 0,
});

const showSuccessModal = ref(false);
const successMessage = ref('');

const generateRef = (categoryName) => {
  let prefix = 'CMD';
  if (categoryName) {
    prefix = categoryName.substring(0, 3).toUpperCase().replace(/[^A-Z]/g, 'X');
  }
  const randomNum = Math.floor(100000 + Math.random() * 900000);
  return `${prefix}-${randomNum}`;
};

watch(() => newProduct.value.category, (newVal) => {
  if (newVal) {
    newProduct.value.ref = generateRef(newVal);
  } else {
    newProduct.value.ref = '';
  }
});

const uploadingImage = ref(false);

const handleImageUpload = async (event) => {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      newProduct.value.image = e.target.result;
    };
    reader.readAsDataURL(file);

    uploadingImage.value = true;
    try {
      const result = await productStore.uploadImage(file);
      if (result.success) {
        newProduct.value.media_id = result.id;
      } else {
        alert("Erreur lors du téléchargement de l'image.");
      }
    } catch (error) {
      console.error('Upload error:', error);
    } finally {
      uploadingImage.value = false;
    }
  }
};

const addNewProduct = async () => {
  if (!newProduct.value.name || !newProduct.value.price || !newProduct.value.category) {
    alert('Veuillez remplir le nom, la catégorie et le prix du produit.');
    return;
  }

  const normalizedName = newProduct.value.name.trim();
  if (!normalizedName) {
    alert('Le nom du produit est invalide.');
    return;
  }

  const existingProducts = await productStore.searchProducts(normalizedName, BUNDLE);
  const duplicate = (existingProducts || []).find((p) => {
    const title = (p?.title || '').toString().trim().toLowerCase();
    return title === normalizedName.toLowerCase();
  });

  if (duplicate) {
    alert(`Le produit "${normalizedName}" existe déjà dans le catalogue sur commande.`);
    return;
  }

  newProduct.value.name = normalizedName;

  const response = await productStore.createProduct(newProduct.value, BUNDLE);
  if (response.success) {
    const stockQty = Number(newProduct.value.stock) || 0;
    let stockMsg = '';
    if (stockQty > 0) {
      const pid = response.product?.nid;
      const stockRes = await productStore.recordStockMovement({
        product_nid: pid,
        product_title: normalizedName,
        type: 'in',
        quantity: stockQty,
        unit_price: Number(newProduct.value.purchase_price) || 0,
        sale_price: Number(newProduct.value.price) || 0,
        reason: 'Initialisation stock (Produit sur commande)',
        date: new Date().toISOString().split('T')[0],
      });
      stockMsg = stockRes.success
        ? ` ${stockQty} en stock ajouté.`
        : ' (Échec de l’enregistrement du mouvement de stock.)';
    }
    successMessage.value =
      `Le produit « ${newProduct.value.name} » a été créé (product_commande).${stockMsg}`;
    showSuccessModal.value = true;
  } else {
    alert('Erreur lors de la sauvegarde : ' + response.message);
  }
};

const goBack = () => router.push('/sur-commande/products');
const finish = () => router.push('/sur-commande/products');

const formatPreviewImage = (img) => {
  if (!img) return '';
  if (img.startsWith('data:')) return img;
  return proxyImage(img, { w: 300, h: 300, fit: 'cover' });
};

onMounted(() => {
  productStore.fetchCategories();
});
</script>

<style scoped>
.space-y-6 > * + * {
  margin-top: 1.5rem;
}
</style>
