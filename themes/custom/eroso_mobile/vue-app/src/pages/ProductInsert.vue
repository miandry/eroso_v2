<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <!-- Navigation Haute -->
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button @click="goBack" class="p-1 -ml-1 text-gray-600 cursor-pointer">
            <i class="ri-arrow-left-line ri-lg"></i>
          </button>
          <h1 class="text-lg font-semibold text-gray-900">Nouveau Produit</h1>
        </div>
      </div>
    </nav>

    <main class="pt-16 pb-20 px-4 lg:ml-64">
      <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm p-6 space-y-6">
          <!-- Image Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">Image du Produit</label>
            <div class="flex items-center space-x-6">
              <div class="w-32 h-32 bg-gray-100 rounded-2xl flex items-center justify-center overflow-hidden border-2 border-dashed border-gray-300 relative group">
                <img v-if="newProduct.image" :src="formatPreviewImage(newProduct.image)" class="w-full h-full object-cover" :class="{'opacity-50': uploadingImage}">
                <div v-if="uploadingImage" class="absolute inset-0 flex items-center justify-center bg-white/60 z-20">
                    <i class="ri-loader-4-line animate-spin text-2xl text-blue-600"></i>
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
                  <span v-if="uploadingImage" class="text-blue-600 font-medium">Téléchargement en cours...</span>
                  <span v-else-if="analyzingImage" class="text-violet-600 font-medium">Analyse IA : catégorie, nom, SKU…</span>
                  <span v-else>Cliquez sur le cadre pour télécharger une photo. Format: JPG, PNG. Max 2Mo.</span>
                </p>
                <button v-if="newProduct.image && !uploadingImage && !analyzingImage" type="button" @click="clearImageAfterDuplicate" class="text-xs text-red-500 font-medium">Supprimer la photo</button>
              </div>
            </div>
            <div v-if="aiAnalysisError" class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex gap-2 items-start">
              <i class="ri-error-warning-line shrink-0 text-lg"></i>
              <span>{{ aiAnalysisError }}</span>
            </div>
          </div>

          <!-- Form Fields -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Catégorie <span class="text-red-500">*</span></label>
              <input
                v-model="newProduct.category"
                list="product-category-list"
                type="text"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-blue-600 bg-white"
                placeholder="Choisir ou saisir une nouvelle catégorie"
              >
              <datalist id="product-category-list">
                <option v-for="cat in categories" :key="cat.tid" :value="cat.name" />
              </datalist>
              <p v-if="isNewCategory" class="text-[10px] text-amber-600 mt-1 font-medium">
                Nouvelle catégorie — elle sera créée à l'enregistrement.
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nom du Produit <span class="text-red-500">*</span></label>
              <input type="text" v-model="newProduct.name" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Ex: iPhone 15 Pro Max">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Référence / SKU</label>
              <div class="relative">
                <input type="text" v-model="newProduct.ref" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-blue-600 bg-gray-50" readonly placeholder="Généré automatiquement">
                <i class="ri-lock-line absolute right-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
              </div>
              <p class="text-[10px] text-gray-500 mt-1 italic">La référence est générée selon la catégorie choisie.</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Prix de Vente (Ar) <span class="text-red-500">*</span></label>
              <input type="number" v-model="newProduct.price" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="0">
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Quantité</label>
                <input v-model.number="newProduct.stock" type="number" min="0" step="1" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="0">
                <p class="text-[10px] text-gray-500 mt-1">Stock initial.</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Prix d’achat (Ar)</label>
                <input v-model.number="newProduct.purchase_price" type="number" min="0" step="1" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="0">
                <p class="text-[10px] text-gray-500 mt-1">Coût unitaire.</p>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea v-model="newProduct.description" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Ajouter des détails sur le produit..."></textarea>
            </div>

            <div v-if="newProduct.search_image || analyzingImage">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Texte recherche image (IA)
                <span v-if="analyzingImage" class="text-violet-600 text-xs font-normal ml-1">génération...</span>
              </label>
              <textarea
                v-model="newProduct.search_image"
                rows="4"
                class="w-full px-4 py-3 border border-violet-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 bg-violet-50/40"
                placeholder="Généré automatiquement à partir de la photo pour optimiser la recherche par image..."
                :disabled="analyzingImage"
              ></textarea>
              <p class="text-[10px] text-gray-500 mt-1">Utilisé pour retrouver ce produit via la recherche par photo sur le catalogue.</p>
            </div>
          </div>

          <div class="pt-4">
            <button @click="addNewProduct" :disabled="loading || analyzingImage || uploadingImage" class="w-full bg-blue-600 text-white py-4 rounded-xl font-bold text-lg shadow-lg shadow-blue-200 active:scale-[0.98] transition-all disabled:opacity-50 disabled:active:scale-100 flex items-center justify-center space-x-2">
              <i v-if="loading || analyzingImage" class="ri-loader-4-line animate-spin"></i>
              <span>{{ analyzingImage ? 'Analyse IA en cours...' : (loading ? 'Enregistrement...' : 'Enregistrer le Produit') }}</span>
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- Modal doublon image -->
    <div v-if="showDuplicateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4">
      <div class="bg-white rounded-3xl w-full max-w-md max-h-[85vh] flex flex-col shadow-xl">
        <div class="p-6 pb-3 border-b border-gray-100">
          <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="ri-image-line text-2xl text-amber-600"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-900 text-center">Image déjà existante</h3>
          <p class="text-sm text-gray-500 text-center mt-1">
            {{ duplicateProducts.length }} produit(s) similaire(s) trouvé(s) dans le catalogue.
          </p>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
          <div
            v-for="product in duplicateProducts"
            :key="product.nid"
            class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 bg-gray-50"
          >
            <img
              :src="getProductThumb(product)"
              :alt="product.title"
              class="w-14 h-14 rounded-lg object-cover bg-white shrink-0"
            >
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold text-gray-900 truncate">{{ product.title }}</p>
              <p class="text-xs text-gray-500">Réf: {{ product.field_sku || 'N/A' }}</p>
              <p v-if="product._ai_match_reason" class="text-[10px] text-violet-700 mt-0.5 line-clamp-2">
                {{ product._ai_match_reason }}
              </p>
            </div>
            <div class="shrink-0 text-right space-y-1">
              <span class="inline-block px-2 py-0.5 rounded-md bg-violet-600 text-white text-[10px] font-black">
                {{ product._ai_score }}%
              </span>
              <router-link
                :to="`/product/${product.nid}`"
                class="block text-xs font-semibold text-blue-600 hover:underline"
                @click="showDuplicateModal = false"
              >
                Voir
              </router-link>
            </div>
          </div>
        </div>
        <div class="p-4 border-t border-gray-100 space-y-2">
          <button
            type="button"
            class="w-full py-3 rounded-xl font-bold bg-blue-600 text-white hover:bg-blue-700"
            @click="dismissDuplicateModal"
          >
            Continuer quand même
          </button>
          <button
            type="button"
            class="w-full py-3 rounded-xl font-bold bg-gray-100 text-gray-700 hover:bg-gray-200"
            @click="clearImageAfterDuplicate"
          >
            Changer la photo
          </button>
        </div>
      </div>
    </div>

    <!-- Modal de Succès -->
    <div v-if="showSuccessModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-6">
      <div class="bg-white rounded-3xl p-8 w-full max-w-sm text-center space-y-6">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto">
          <i class="ri-checkbox-circle-fill text-4xl text-green-600"></i>
        </div>
        <div>
          <h3 class="text-xl font-bold text-gray-900">Produit Ajouté !</h3>
          <p class="text-gray-500 mt-2">{{ successMessage }}</p>
        </div>
        <button @click="finish" class="w-full bg-blue-600 text-white py-4 rounded-xl font-bold">Voir le Catalogue</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useProductStore } from '../stores/useProductStore';
import { storeToRefs } from 'pinia';
import { proxyImage } from '../services/image';
import { analyzeProductImageForSearch, searchProductsByImage, getApiErrorMessage } from '../services/api';

const DUPLICATE_SCORE_MIN = 48;

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
  description: '',
  search_image: '',
  stock: 1,
  purchase_price: 0
});

const showSuccessModal = ref(false);
const showDuplicateModal = ref(false);
const duplicateProducts = ref([]);
const duplicateDismissed = ref(false);
const successMessage = ref('');

const generateRef = (categoryName) => {
  let prefix = 'PRD';
  if (categoryName) {
    prefix = categoryName.substring(0, 3).toUpperCase().replace(/[^A-Z]/g, 'X');
  }
  const randomNum = Math.floor(100000 + Math.random() * 900000);
  return `${prefix}-${randomNum}`;
};

/** Associe category_guess (IA) à une catégorie du catalogue. */
const matchCategoryFromAi = (guess, categoryList) => {
  if (!guess || !categoryList?.length) return '';
  const normalized = guess.toLowerCase().trim();
  const exact = categoryList.find((c) => (c.name || '').toLowerCase().trim() === normalized);
  if (exact) return exact.name;

  const partial = categoryList.find((c) => {
    const name = (c.name || '').toLowerCase().trim();
    return name.includes(normalized) || normalized.includes(name);
  });
  if (partial) return partial.name;

  const guessParts = normalized.split(/[\s/|,;-]+/).filter((p) => p.length >= 3);
  for (const cat of categoryList) {
    const name = (cat.name || '').toLowerCase().trim();
    if (guessParts.some((part) => name.includes(part) || part.includes(name))) {
      return cat.name;
    }
  }
  return '';
};

const cleanCategoryGuess = (guess) => {
  if (!guess) return '';
  let name = guess.trim().replace(/^catégorie\s*:\s*/i, '');
  if (name.includes('/')) {
    const parts = name.split('/').map((p) => p.trim()).filter(Boolean);
    name = parts[0] || name;
  }
  if (!name) return '';
  return name.charAt(0).toUpperCase() + name.slice(1);
};

const resolveCategoryFromAi = (guess, categoryList) => {
  const matched = matchCategoryFromAi(guess, categoryList);
  if (matched) return matched;
  return cleanCategoryGuess(guess);
};

const isNewCategory = computed(() => {
  const cat = (newProduct.value.category || '').trim();
  if (!cat) return false;
  return !categories.value.some((c) => (c.name || '').trim().toLowerCase() === cat.toLowerCase());
});

const applyAiAnalysis = (data) => {
  const analysis = data?.analysis;
  if (!analysis) return;

  if (data.field_search_image) {
    newProduct.value.search_image = data.field_search_image;
  }

  if (analysis.title_guess) {
    newProduct.value.name = analysis.title_guess.trim();
  }

  const resolvedCategory = resolveCategoryFromAi(analysis.category_guess, categories.value);
  if (resolvedCategory) {
    newProduct.value.category = resolvedCategory;
    newProduct.value.ref = generateRef(resolvedCategory);
  }
};

watch(() => newProduct.value.category, (newVal) => {
  if (newVal) {
    newProduct.value.ref = generateRef(newVal);
  } else {
    newProduct.value.ref = '';
  }
});

const uploadingImage = ref(false);
const analyzingImage = ref(false);
const aiAnalysisError = ref('');

const resetDuplicateState = () => {
  showDuplicateModal.value = false;
  duplicateProducts.value = [];
  duplicateDismissed.value = false;
};

const getProductThumb = (p) => {
  let url = '';
  if (p.field_media_image?.image?.url) {
    url = p.field_media_image.image.url;
  } else if (p.field_images?.[0]?.image?.url) {
    url = p.field_images[0].image.url;
  } else {
    url = 'https://readdy.ai/api/search-image?query=icon%2C%20generic%20product';
  }
  return proxyImage(url, { w: 80, h: 80, fit: 'cover' });
};

const checkDuplicateImage = (rows) => {
  const matches = (rows || []).filter((p) => Number(p._ai_score || 0) >= DUPLICATE_SCORE_MIN);
  duplicateProducts.value = matches;
  if (matches.length > 0) {
    duplicateDismissed.value = false;
    showDuplicateModal.value = true;
  }
};

const dismissDuplicateModal = () => {
  showDuplicateModal.value = false;
  duplicateDismissed.value = true;
};

const clearImageAfterDuplicate = () => {
  resetDuplicateState();
  newProduct.value.image = '';
  newProduct.value.media_id = '';
  newProduct.value.search_image = '';
  newProduct.value.name = '';
  newProduct.value.description = '';
  newProduct.value.category = '';
  newProduct.value.ref = '';
  aiAnalysisError.value = '';
};

const handleImageUpload = async (event) => {
  const file = event.target.files[0];
  if (file) {
    resetDuplicateState();
    newProduct.value.search_image = '';
    newProduct.value.name = '';
    newProduct.value.description = '';
    newProduct.value.category = '';
    newProduct.value.ref = '';
    // Show local preview
    const reader = new FileReader();
    reader.onload = (e) => {
      newProduct.value.image = e.target.result;
    };
    reader.readAsDataURL(file);

    // Upload to server
    uploadingImage.value = true;
    try {
      const result = await productStore.uploadImage(file);
      if (result.success) {
        newProduct.value.media_id = result.id;
      } else {
        alert("Erreur lors du téléchargement de l'image.");
        return;
      }
    } catch (error) {
      console.error("Upload error:", error);
      alert("Erreur lors du téléchargement de l'image.");
      return;
    } finally {
      uploadingImage.value = false;
    }

    analyzingImage.value = true;
    aiAnalysisError.value = '';
    let applied = false;
    let lastError = '';
    try {
      if (!categories.value?.length) {
        await productStore.fetchCategories();
      }
      try {
        const response = await searchProductsByImage(file);
        const data = response?.data;
        if (data?.status) {
          applyAiAnalysis(data);
          checkDuplicateImage(data.rows);
          applied = Boolean(data.field_search_image || data.analysis);
        } else if (data?.message) {
          lastError = data.message;
        }
      } catch (error) {
        lastError = getApiErrorMessage(error, '');
      }
      if (!applied) {
        try {
          const fallback = await analyzeProductImageForSearch(file);
          if (fallback?.data?.status) {
            applyAiAnalysis(fallback.data);
            lastError = '';
          } else if (fallback?.data?.message) {
            lastError = fallback.data.message;
          }
        } catch (e) {
          lastError = getApiErrorMessage(e, lastError || 'Erreur lors de l\'analyse IA.');
        }
      }
      aiAnalysisError.value = lastError;
    } finally {
      analyzingImage.value = false;
    }
  }
};

const addNewProduct = async () => {
  if (!newProduct.value.name || !newProduct.value.price || !newProduct.value.category) {
    alert("Veuillez remplir le nom, la catégorie et le prix du produit.");
    return;
  }

  if (duplicateProducts.value.length > 0 && !duplicateDismissed.value) {
    showDuplicateModal.value = true;
    return;
  }

  const normalizedName = newProduct.value.name.trim();
  if (!normalizedName) {
    alert("Le nom du produit est invalide.");
    return;
  }

  // Duplicate check: exact match on title (case-insensitive).
  const existingProducts = await productStore.searchProducts(normalizedName);
  const duplicate = (existingProducts || []).find((p) => {
    const title = (p?.title || '').toString().trim().toLowerCase();
    return title === normalizedName.toLowerCase();
  });

  if (duplicate) {
    alert(`Le produit "${normalizedName}" existe déjà.`);
    return;
  }

  // Ensure clean value sent to API.
  newProduct.value.name = normalizedName;
  
  const response = await productStore.createProduct(newProduct.value);
  if (response.success) {
    await productStore.fetchCategories();
    const qty = Number(newProduct.value.stock) || 0;
    const stockMsg = qty > 0 ? ` ${qty} en stock ajouté.` : '';
    successMessage.value = `Le produit "${newProduct.value.name}" a été créé avec succès.${stockMsg}`;
    showSuccessModal.value = true;
  } else {
    alert("Erreur lors de la sauvegarde: " + response.message);
  }
};

const goBack = () => router.back();
const finish = () => router.push('/products');

const formatPreviewImage = (img) => {
  if (!img) return '';
  // Don't proxy base64 data strings
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
