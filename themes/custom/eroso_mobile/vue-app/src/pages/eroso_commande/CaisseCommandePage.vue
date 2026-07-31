<template>
  <div class="bg-gray-50 font-sans min-h-screen pb-24 lg:pb-4">
    <transition name="slide-down">
      <div v-if="showToast" class="fixed top-20 left-1/2 -translate-x-1/2 z-50 rounded-xl shadow-lg p-4 max-w-sm w-full mx-4" :class="toastType === 'error' ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'">
        <div class="flex items-start space-x-3">
          <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" :class="toastType === 'error' ? 'bg-red-500' : 'bg-green-500'">
            <i :class="toastType === 'error' ? 'ri-error-warning-line text-white' : 'ri-check-line text-white'"></i>
          </div>
          <div class="flex-1">
            <h4 class="text-sm font-bold" :class="toastType === 'error' ? 'text-red-900' : 'text-green-900'">{{ toastType === 'error' ? 'Action impossible' : 'Ajouté au panier !' }}</h4>
            <p class="text-xs mt-1" :class="toastType === 'error' ? 'text-red-700' : 'text-green-700'">{{ toastMessage }}</p>
          </div>
        </div>
      </div>
    </transition>

    <nav class="fixed top-0 left-0 right-0 bg-white shadow-sm z-40 lg:ml-64 lg:mr-96">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button type="button" @click="uiStore.toggleSidebar" class="p-1 -ml-1 text-gray-600 cursor-pointer lg:hidden">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <div>
            <p class="text-sm font-semibold text-gray-900">Caisse sur commande</p>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <button type="button" @click="loadProductCommande(false)" class="w-8 h-8 flex items-center justify-center text-indigo-600 bg-indigo-50 rounded-lg cursor-pointer">
            <i class="ri-refresh-line"></i>
          </button>
        </div>
      </div>
    </nav>

    <main class="pt-16 lg:ml-64 lg:mr-96">
      <div class="p-4">
        <div class="mb-4 space-y-3">
          <div class="flex space-x-2">
            <select
              v-model="searchType"
              class="px-3 py-3 bg-white border border-gray-200 rounded-xl shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              :disabled="imageSearchActive"
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
                class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                :disabled="imageSearchActive"
              >
            </div>
            <label
              class="shrink-0 w-12 h-12 flex items-center justify-center bg-violet-50 text-violet-700 border border-violet-100 rounded-xl cursor-pointer hover:bg-violet-100 transition-colors"
              title="Rechercher par photo (Claude AI)"
            >
              <i class="ri-image-search-line text-xl"></i>
              <input
                ref="imageInputRef"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                class="hidden"
                @change="onImageSelected"
              >
            </label>
          </div>

          <div
            v-if="imagePreview || imageSearchActive || imageSearchError"
            class="bg-white border border-violet-100 rounded-2xl p-3 shadow-sm space-y-3"
          >
            <div v-if="imagePreview" class="flex items-start gap-3">
              <img :src="imagePreview" alt="Aperçu recherche" class="w-14 h-14 rounded-xl object-cover border border-gray-100">
              <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-violet-800">Recherche par image (IA)</p>
                <p v-if="imageSearchMeta" class="text-[10px] text-violet-600 mt-0.5">
                  {{ imageSearchMeta.total }} résultat(s) · {{ imageSearchMeta.scanned }} analysé(s)
                </p>
                <p v-if="generatedSearchText" class="text-[10px] text-gray-500 mt-1 line-clamp-2 whitespace-pre-line">
                  {{ generatedSearchText }}
                </p>
              </div>
              <button type="button" class="text-gray-400 hover:text-gray-600 p-1" @click="clearImageSearch">
                <i class="ri-close-line text-lg"></i>
              </button>
            </div>
            <div class="flex flex-wrap gap-2">
              <button
                v-if="imageSearchActive"
                type="button"
                class="px-3 py-2 rounded-xl text-xs font-bold bg-gray-100 text-gray-700 hover:bg-gray-200"
                @click="clearImageSearch"
              >
                Revenir au catalogue
              </button>
            </div>
            <div v-if="imageSearchError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex gap-2 items-start">
              <i class="ri-error-warning-line shrink-0 text-lg"></i>
              <span>{{ imageSearchError }}</span>
            </div>
          </div>
        </div>

        <div v-if="listError" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          Impossible de charger le catalogue. Vérifiez la connexion ou réessayez.
        </div>

        <div v-if="(loading || imageSearching) && filteredProducts.length === 0" class="flex flex-col items-center justify-center py-20 space-y-4">
          <div class="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
          <p class="text-sm text-gray-500 font-medium">{{ imageSearching ? 'Analyse IA et recherche…' : 'Chargement des produits...' }}</p>
        </div>

        <div v-else-if="!loading && !imageSearching && filteredProducts.length === 0 && !listError" class="flex flex-col items-center justify-center py-16 text-center px-4">
          <i class="ri-inbox-line text-5xl text-gray-300 mb-3"></i>
          <p class="text-sm font-medium text-gray-700">
            {{ imageSearchActive ? 'Aucun produit correspondant' : 'Aucun produit sur commande' }}
          </p>
          <p class="text-xs text-gray-500 mt-1">
            {{ imageSearchActive ? 'Essayez une autre photo ou effacez la recherche IA.' : 'Ajoutez des fiches product_commande ou élargissez la recherche.' }}
          </p>
        </div>

        <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3 mb-4">
          <div
            v-for="product in filteredProducts"
            :key="productNid(product)"
            class="bg-white rounded-2xl shadow-sm overflow-hidden cursor-pointer transition-all active:scale-95 border border-indigo-50"
            @click="addToOrder(product)"
          >
            <div class="relative aspect-square bg-gray-100">
              <img
                :src="getProductImage(product)"
                :alt="product.title"
                class="w-full h-full object-cover"
                loading="lazy"
              >
              <div
                v-if="imageSearchActive && product._ai_score != null"
                class="absolute top-1.5 left-1.5 bg-violet-600 text-white text-[10px] font-black px-1.5 py-0.5 rounded-md shadow-sm"
              >
                {{ product._ai_score }}%
              </div>
              <button
                type="button"
                @click.stop="router.push('/sur-commande/product/' + productNid(product))"
                class="absolute bottom-1.5 right-1.5 w-7 h-7 bg-white/80 backdrop-blur-sm rounded-full flex items-center justify-center text-gray-600 hover:bg-indigo-600 hover:text-white transition-colors shadow-sm"
              >
                <i class="ri-eye-line text-sm"></i>
              </button>
            </div>

            <div class="p-3">
              <h3 class="text-sm font-bold text-gray-900 mb-1 line-clamp-1">{{ product.title }}</h3>
              <p class="text-xs text-gray-500 mb-1">Réf: {{ product.field_sku || 'N/A' }}</p>

              <div class="text-lg font-black text-indigo-600">
                {{ formatPrice(product.field_prix_vente || product.field_price) }} Ar
              </div>
            </div>
          </div>
        </div>

        <div v-if="hasMore && !loading && !imageSearchActive" class="mt-4 text-center mb-4">
          <button
            type="button"
            @click="loadMore"
            class="px-6 py-3 bg-indigo-600 text-white rounded-xl font-semibold hover:bg-indigo-700 transition-colors"
          >
            Voir plus
          </button>
        </div>
      </div>
    </main>

    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 lg:hidden safe-bottom">
      <div
        class="flex items-center justify-between px-4 py-3 cursor-pointer"
        @click="toggleOrderPanel"
      >
        <div class="flex items-center space-x-3">
          <h3 class="text-base font-bold text-gray-900">Vente sur commande</h3>
          <span class="text-xs text-indigo-600 font-semibold">{{ orderItems.length }} articles</span>
        </div>
        <i :class="['text-xl transition-transform', isOrderPanelOpen ? 'ri-arrow-down-s-line' : 'ri-arrow-up-s-line']"></i>
      </div>

      <transition name="slide-up">
        <div v-if="isOrderPanelOpen" class="max-h-[70vh] overflow-y-auto border-t border-gray-100">
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
                <button type="button" class="text-red-500 hover:text-red-700 flex-shrink-0" @click="removeItem(index)">
                  <i class="ri-delete-bin-line text-lg"></i>
                </button>
                <div class="flex-1 min-w-0">
                  <h4 class="text-sm font-semibold text-gray-900 truncate">{{ item.product.title }}</h4>
                  <p class="text-xs text-gray-600">{{ formatPrice(item.product.field_prix_vente || item.product.field_price) }} Ar chacun</p>
                </div>
                <div class="flex items-center space-x-2 flex-shrink-0">
                  <button type="button" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg hover:bg-gray-200" @click="decreaseQuantity(index)">
                    <i class="ri-subtract-line text-sm"></i>
                  </button>
                  <span class="text-sm font-bold w-8 text-center">{{ item.quantity }}</span>
                  <button type="button" class="w-8 h-8 flex items-center justify-center bg-indigo-600 text-white rounded-lg hover:bg-indigo-700" @click="increaseQuantity(index)">
                    <i class="ri-add-line text-sm"></i>
                  </button>
                </div>
                <div class="text-right flex-shrink-0">
                  <div class="text-sm font-bold text-indigo-600">{{ formatPrice((item.product.field_prix_vente || item.product.field_price) * item.quantity) }} Ar</div>
                </div>
              </div>
            </div>
          </div>

          <div class="p-4 border-t border-gray-200 bg-gray-50 space-y-3">
            <div class="rounded-xl bg-[#f3f4f6] border border-gray-200/90 p-4 space-y-3">
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-medium text-gray-500">Client <span class="text-red-500">*</span></span>
                <button
                  v-if="showClientSummary"
                  type="button"
                  class="text-xs font-semibold text-blue-600 hover:text-blue-700"
                  @click="openClientEditor"
                >
                  Changer
                </button>
              </div>

              <template v-if="showClientSummary">
                <div class="flex items-center gap-3">
                  <div class="w-12 h-12 rounded-full bg-blue-600 text-white text-sm font-bold flex items-center justify-center shrink-0 uppercase tracking-tight">
                    {{ clientInitials(orderClient) }}
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-gray-900 leading-snug break-words">{{ orderClient }}</p>
                    <p v-if="orderClientPhone.trim()" class="text-xs text-gray-500 mt-1">{{ orderClientPhone }}</p>
                  </div>
                </div>
              </template>

              <template v-else>
                <button
                  type="button"
                  class="w-full py-3 rounded-lg text-sm font-semibold bg-white border border-dashed border-gray-300 text-gray-800 hover:bg-gray-100/90 flex items-center justify-center gap-2"
                  @click="openClientPickerModal"
                >
                  <i class="ri-user-search-line text-lg text-blue-600"></i>
                  Choisir ou créer un client
                </button>
                <p v-if="orderClient.trim()" class="text-xs text-gray-500 text-center mt-2 line-clamp-2">
                  {{ orderClient }}<span v-if="orderClientPhone.trim()"> · {{ orderClientPhone }}</span>
                </p>
              </template>
            </div>
            <div class="rounded-lg bg-[#f3f4f6] border border-gray-200/90 px-3 py-2">
              <p class="text-[10px] font-medium text-gray-500 mb-1.5">Statut commande</p>
              <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <label
                  v-for="opt in orderCommandeStatusOptions"
                  :key="opt.value"
                  class="inline-flex items-center gap-1.5 cursor-pointer select-none"
                >
                  <input
                    v-model="orderStatusCommande"
                    type="radio"
                    class="h-3.5 w-3.5 shrink-0 accent-blue-600 border-gray-300 focus:ring-blue-500"
                    :value="opt.value"
                  >
                  <span class="text-[11px] text-gray-800 leading-tight">{{ opt.label }}</span>
                </label>
              </div>
            </div>
            <div class="mb-3">
              <label class="block text-xs font-semibold text-gray-700 mb-2">Notes</label>
              <textarea
                v-model="orderNotes"
                rows="3"
                placeholder="Ajouter des notes pour cette vente..."
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
              ></textarea>
            </div>

            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Sous-total</span>
              <span class="font-semibold">{{ formatPrice(subtotal) }} Ar</span>
            </div>
            <div class="flex justify-between text-lg font-bold">
              <span>Total</span>
              <span class="text-indigo-600">{{ formatPrice(subtotal) }} Ar</span>
            </div>
            <button
              type="button"
              @click="finalizeOrder"
              :disabled="savingOrder || orderItems.length === 0 || !orderClient.trim()"
              :class="[
                'w-full py-3 rounded-xl font-bold text-white transition-colors flex items-center justify-center',
                savingOrder || orderItems.length === 0 || !orderClient.trim()
                  ? 'bg-gray-300 cursor-not-allowed'
                  : 'bg-emerald-600 hover:bg-emerald-700 cursor-pointer'
              ]"
            >
              <div v-if="savingOrder" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
              <i v-else class="ri-check-line mr-2"></i>
              {{ savingOrder ? 'Enregistrement...' : orderItems.length === 0 ? 'Panier vide' : !orderClient.trim() ? 'Indiquez le client' : 'Sauvegarder' }}
            </button>
          </div>
        </div>
      </transition>
    </div>

    <div class="hidden lg:block fixed right-0 top-16 w-96 h-[calc(100vh-4rem)] bg-white border-l border-gray-200 overflow-y-auto">
      <div class="h-full flex flex-col">
        <div class="bg-gradient-to-r from-indigo-600 to-violet-700 text-white p-4">
          <div class="flex items-center justify-between mb-2">
            <h2 class="text-lg font-bold">Vente sur commande</h2>
            <button
              v-if="orderItems.length > 0"
              type="button"
              class="text-xs underline hover:text-indigo-200"
              @click="clearOrder"
            >
              Tout effacer
            </button>
          </div>
        </div>

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
                <p class="text-xs text-gray-600">{{ formatPrice(item.product.field_prix_vente || item.product.field_price) }} Ar</p>
              </div>
              <div class="flex items-center space-x-2">
                <button type="button" class="w-7 h-7 flex items-center justify-center bg-gray-200 rounded-full hover:bg-gray-300" @click="decreaseQuantity(index)">
                  <i class="ri-subtract-line text-sm"></i>
                </button>
                <span class="text-sm font-bold w-8 text-center">{{ item.quantity }}</span>
                <button type="button" class="w-7 h-7 flex items-center justify-center bg-indigo-600 text-white rounded-full hover:bg-indigo-700" @click="increaseQuantity(index)">
                  <i class="ri-add-line text-sm"></i>
                </button>
              </div>
              <button type="button" class="text-red-500 hover:text-red-700" @click="removeItem(index)">
                <i class="ri-delete-bin-line"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-gray-200 bg-gray-50">
          <div class="mb-4 rounded-xl bg-[#f3f4f6] border border-gray-200/90 p-4 space-y-3">
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs font-medium text-gray-500">Client <span class="text-red-500">*</span></span>
              <button
                v-if="showClientSummary"
                type="button"
                class="text-xs font-semibold text-blue-600 hover:text-blue-700"
                @click="openClientEditor"
              >
                Changer
              </button>
            </div>

            <template v-if="showClientSummary">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-blue-600 text-white text-sm font-bold flex items-center justify-center shrink-0 uppercase tracking-tight">
                  {{ clientInitials(orderClient) }}
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-bold text-gray-900 leading-snug break-words">{{ orderClient }}</p>
                  <p v-if="orderClientPhone.trim()" class="text-xs text-gray-500 mt-1">{{ orderClientPhone }}</p>
                </div>
              </div>
            </template>

            <template v-else>
              <button
                type="button"
                class="w-full py-3 rounded-lg text-sm font-semibold bg-white border border-dashed border-gray-300 text-gray-800 hover:bg-gray-100/90 flex items-center justify-center gap-2"
                @click="openClientPickerModal"
              >
                <i class="ri-user-search-line text-lg text-blue-600"></i>
                Choisir ou créer un client
              </button>
              <p v-if="orderClient.trim()" class="text-xs text-gray-500 text-center mt-2 line-clamp-2">
                {{ orderClient }}<span v-if="orderClientPhone.trim()"> · {{ orderClientPhone }}</span>
              </p>
            </template>
          </div>
          <div class="mb-4 rounded-lg bg-[#f3f4f6] border border-gray-200/90 px-3 py-2">
            <p class="text-[10px] font-medium text-gray-500 mb-1.5">Statut commande</p>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
              <label
                v-for="opt in orderCommandeStatusOptions"
                :key="opt.value"
                class="inline-flex items-center gap-1.5 cursor-pointer select-none"
              >
                <input
                  v-model="orderStatusCommande"
                  type="radio"
                  class="h-3.5 w-3.5 shrink-0 accent-blue-600 border-gray-300 focus:ring-blue-500"
                  :value="opt.value"
                >
                <span class="text-[11px] text-gray-800 leading-tight">{{ opt.label }}</span>
              </label>
            </div>
          </div>
          <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-700 mb-2">Notes</label>
            <textarea
              v-model="orderNotes"
              rows="3"
              placeholder="Ajouter des notes pour cette vente..."
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
            ></textarea>
          </div>

          <div class="space-y-2 mb-4">
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Sous-total</span>
              <span class="font-semibold">{{ formatPrice(subtotal) }} Ar</span>
            </div>
            <div class="flex justify-between text-lg font-bold">
              <span>Total</span>
              <span class="text-indigo-600">{{ formatPrice(subtotal) }} Ar</span>
            </div>
          </div>

          <button
            type="button"
            @click="finalizeOrder"
            :disabled="savingOrder || orderItems.length === 0 || !orderClient.trim()"
            :class="[
              'w-full py-3 rounded-xl font-bold text-white transition-colors flex items-center justify-center',
              savingOrder || orderItems.length === 0 || !orderClient.trim()
                ? 'bg-gray-300 cursor-not-allowed'
                : 'bg-emerald-600 hover:bg-emerald-700 cursor-pointer'
            ]"
          >
            <div v-if="savingOrder" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
            <i v-else class="ri-check-line mr-2"></i>
            {{ savingOrder ? 'Enregistrement...' : orderItems.length === 0 ? 'Panier vide' : !orderClient.trim() ? 'Indiquez le client' : 'Finaliser la vente' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Client : recherche / récents / nouveau — dans une modale pour libérer la barre latérale -->
    <div
      v-if="showClientPickerModal"
      class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="client-picker-title"
    >
      <div class="absolute inset-0 bg-black/45" @click="closeClientPickerModal"></div>
      <div
        class="relative w-full max-w-lg max-h-[min(88vh,640px)] flex flex-col bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl border border-gray-200 overflow-hidden sm:max-h-[85vh]"
        @click.stop
      >
        <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-gray-100 bg-gray-50 shrink-0">
          <h2 id="client-picker-title" class="text-base font-bold text-gray-900">
            Client
          </h2>
          <button
            type="button"
            class="p-2 rounded-lg text-gray-500 hover:bg-gray-200"
            aria-label="Fermer"
            @click="closeClientPickerModal"
          >
            <i class="ri-close-line text-xl"></i>
          </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3 min-h-0">
          <p class="text-xs text-gray-500">
            Au moins 2 lettres pour filtrer ; les 5 derniers clients s’affichent sinon.
          </p>
          <div class="relative">
            <input
              v-model="orderClient"
              type="text"
              autocomplete="off"
              placeholder="Nom ou référence client…"
              class="w-full px-3 py-2.5 pr-9 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              @input="onClientInput"
              @focus="onClientFocus"
            >
            <div v-if="clientDropdownBusy" class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin pointer-events-none"></div>
          </div>
          <div
            v-if="showClientDropdownPanel"
            class="rounded-xl border border-gray-200 bg-white max-h-44 overflow-y-auto"
          >
            <div class="px-3 py-2 border-b border-gray-100 bg-gray-50 sticky top-0">
              <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ clientDropdownLabel }}</p>
            </div>
            <div v-if="clientDropdownBusy && displayedClientList.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">
              Chargement…
            </div>
            <div v-else-if="isClientSearchMode && displayedClientList.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">
              Aucun résultat
            </div>
            <button
              v-for="c in displayedClientList"
              :key="'modal-' + c.nid"
              type="button"
              class="w-full text-left px-3 py-2.5 text-sm hover:bg-blue-50 border-b border-gray-50 last:border-0 flex items-center gap-3"
              @click="pickClient(c)"
            >
              <span class="w-9 h-9 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center shrink-0">{{ clientInitials(c.title) }}</span>
              <span class="min-w-0 flex-1">
                <span class="font-medium text-gray-900 block truncate">{{ c.title }}</span>
                <span v-if="c.field_phone" class="block text-xs text-gray-500 truncate">{{ c.field_phone }}</span>
              </span>
            </button>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Téléphone (optionnel)</label>
            <input
              v-model="orderClientPhone"
              type="tel"
              autocomplete="tel"
              placeholder="Rempli au choix ou avec un client existant"
              class="w-full px-3 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
          </div>
          <button
            type="button"
            class="w-full py-2.5 rounded-lg text-sm font-semibold border border-blue-200 text-blue-700 bg-white hover:bg-blue-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="!orderClient.trim() || savingClient"
            @click="saveNewClient"
          >
            <span v-if="savingClient" class="inline-block w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin align-middle mr-2"></span>
            <i v-else class="ri-user-add-line mr-1"></i>
            Enregistrer comme nouveau client
          </button>
          <button
            v-if="orderClient.trim()"
            type="button"
            class="w-full py-2.5 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700"
            @click="applyClientName"
          >
            Valider
          </button>
        </div>
      </div>
    </div>

    <div v-if="showSuccessModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 text-center">
        <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="ri-check-line text-3xl text-indigo-600"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Vente enregistrée</h3>
        <p class="text-gray-600 mb-6">La vente sur commande a été créée avec succès.</p>
        <button
          type="button"
          class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700"
          @click="closeSuccessModal"
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
import { useUIStore } from '../../stores/useUIStore';
import { proxyImage } from '../../services/image';
import { getLists, saveOrderCommande, saveItem, searchProductsByImage, getApiErrorMessage } from '../../services/api';

const BUNDLE = 'product_commande';
const CLIENT_BUNDLE = 'client';
const ITEMS_PER_PAGE = 12;

/** Caisse sur commande : seuls ces statuts sont proposés (alignés sur field_status_commande). */
const orderCommandeStatusOptions = [
  { value: 'draft', label: 'Draft' },
  { value: 'avance_payer', label: 'Avance payer' },
];

const router = useRouter();
const uiStore = useUIStore();

/** Liste chargée via API `api_solutions/api/v2/node/product_commande` (indépendante du store boutique). */
const products = ref([]);
const loading = ref(false);
const hasMore = ref(true);
const currentPage = ref(0);
const listError = ref(null);

const searchQuery = ref('');
const searchType = ref('title');
const imageInputRef = ref(null);
const imageFile = ref(null);
const imagePreview = ref('');
const imageSearchResults = ref([]);
const imageSearchActive = ref(false);
const imageSearching = ref(false);
const imageSearchError = ref('');
const generatedSearchText = ref('');
const imageSearchMeta = ref(null);
const orderItems = ref([]);
const showSuccessModal = ref(false);
const isOrderPanelOpen = ref(false);
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('success');
const searchTimeout = ref(null);
const savingOrder = ref(false);
const orderClient = ref('');
const orderClientPhone = ref('');
const orderNotes = ref('');
const orderStatusCommande = ref('draft');
const clientSuggestions = ref([]);
const showClientSuggestions = ref(false);
const clientSearchLoading = ref(false);
const selectedClientNid = ref(null);
const savingClient = ref(false);
const recentClients = ref([]);
const recentClientsLoading = ref(false);
/** Résumé type caisse (carte grise) vs formulaire de saisie. */
const clientEditorOpen = ref(true);
/** Recherche / création client dans une modale (économise la place dans le panneau). */
const showClientPickerModal = ref(false);
let clientSearchTimer = null;

const RECENT_CLIENTS_COUNT = 5;

const showClientSummary = computed(() => Boolean(orderClient.value.trim()) && !clientEditorOpen.value);

function buildListQueryParams() {
  let params = `sort[val]=nid&sort[op]=DESC&offset=${ITEMS_PER_PAGE}&pager=${currentPage.value}`;
  if (searchQuery.value && searchQuery.value.length >= 2) {
    if (searchType.value === 'sku') {
      params += `&filters[field_sku][val]=${encodeURIComponent(searchQuery.value)}&filters[field_sku][op]=CONTAINS`;
    } else {
      params += `&filters[title][val]=${encodeURIComponent(searchQuery.value)}&filters[title][op]=CONTAINS`;
    }
  }
  return params;
}

/**
 * Charge les nœuds product_commande depuis l’API (même contrat que ProductList / useProductStore.fetchProducts).
 */
async function loadProductCommande(append = false) {
  if (loading.value) {
    return;
  }
  if (!append) {
    currentPage.value = 0;
    hasMore.value = true;
  }
  if (!hasMore.value && append) {
    return;
  }

  loading.value = true;
  listError.value = null;
  try {
    const parameters = buildListQueryParams();
    const response = await getLists('node', BUNDLE, parameters);
    const raw = response.data?.rows ?? response.data ?? [];
    const newProducts = Array.isArray(raw) ? raw.map(normalizeProductRow) : [];

    if (append) {
      products.value = [...products.value, ...newProducts];
    } else {
      products.value = newProducts;
    }

    if (newProducts.length < ITEMS_PER_PAGE) {
      hasMore.value = false;
    } else {
      currentPage.value += 1;
    }
  } catch (e) {
    listError.value = e;
    console.error('Caisse sur commande — API product_commande:', e);
    if (!append) {
      products.value = [];
    }
    hasMore.value = false;
  } finally {
    loading.value = false;
  }
}

/**
 * Assure nid + champs numériques exploitables (l’API peut renvoyer id, ou quantité sous forme tableau / objet).
 */
function normalizeProductRow(p) {
  const id = p.nid ?? p.id;
  const copy = { ...p, nid: id };
  const q = copy.field_quantite_disponible;
  if (Array.isArray(q) && q[0] && q[0].value !== undefined) {
    copy.field_quantite_disponible = q[0].value;
  } else if (q && typeof q === 'object' && q.value !== undefined) {
    copy.field_quantite_disponible = q.value;
  }
  return copy;
}

function productNid(p) {
  return p.nid ?? p.id;
}

function normalizeClientRow(c) {
  const id = c.nid ?? c.id;
  let phone = c.field_phone;
  if (phone && typeof phone === 'object' && phone.value !== undefined) {
    phone = phone.value;
  }
  return {
    nid: id,
    title: (c.title || '').trim(),
    field_phone: phone != null && phone !== '' ? String(phone).trim() : '',
  };
}

/** Initiales pour l’avatar (ex. « Jean Dupont » → « JD »). */
function clientInitials(title) {
  const parts = (title || '').trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) {
    return '?';
  }
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase();
  }
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

async function loadRecentClients() {
  recentClientsLoading.value = true;
  try {
    const params = `sort[val]=nid&sort[op]=DESC&offset=${RECENT_CLIENTS_COUNT}&pager=0`;
    const res = await getLists('node', CLIENT_BUNDLE, params);
    const raw = res.data?.rows ?? res.data ?? [];
    const list = Array.isArray(raw) ? raw.map(normalizeClientRow) : [];
    recentClients.value = list.slice(0, RECENT_CLIENTS_COUNT);
  } catch (e) {
    console.error('Clients récents:', e);
    recentClients.value = [];
  } finally {
    recentClientsLoading.value = false;
  }
}

const displayedClientList = computed(() => {
  const q = orderClient.value.trim();
  if (q.length >= 2) {
    return clientSuggestions.value;
  }
  return recentClients.value;
});

const clientDropdownLabel = computed(() => {
  return orderClient.value.trim().length >= 2 ? 'Résultats' : 'Clients récents';
});

const clientDropdownBusy = computed(() => {
  const q = orderClient.value.trim();
  if (q.length >= 2) {
    return clientSearchLoading.value;
  }
  return recentClientsLoading.value;
});

const isClientSearchMode = computed(() => orderClient.value.trim().length >= 2);

const showClientDropdownPanel = computed(() => {
  if (!showClientSuggestions.value) {
    return false;
  }
  if (clientDropdownBusy.value) {
    return true;
  }
  if (isClientSearchMode.value) {
    return true;
  }
  return displayedClientList.value.length > 0;
});

function buildClientLabel() {
  const name = orderClient.value.trim();
  const tel = orderClientPhone.value.trim();
  if (!name) {
    return '';
  }
  return tel ? `${name} — Tél. ${tel}` : name;
}

function onClientInput() {
  selectedClientNid.value = null;
  if (clientSearchTimer) {
    clearTimeout(clientSearchTimer);
  }
  const q = orderClient.value.trim();
  if (q.length < 2) {
    clientSuggestions.value = [];
    showClientSuggestions.value = true;
    return;
  }
  clientSearchTimer = setTimeout(() => {
    searchClients(q);
  }, 350);
}

async function onClientFocus() {
  showClientSuggestions.value = true;
  if (recentClients.value.length === 0 && !recentClientsLoading.value) {
    await loadRecentClients();
  }
  const q = orderClient.value.trim();
  if (q.length >= 2) {
    await searchClients(q);
  }
}

/**
 * Détecte un client existant avec le même nom (insensible à la casse).
 */
async function findClientByExactTitle(name) {
  const n = name.trim();
  if (!n) {
    return null;
  }
  const params = `sort[val]=nid&sort[op]=DESC&offset=50&pager=0&filters[title][val]=${encodeURIComponent(n)}&filters[title][op]=CONTAINS`;
  const res = await getLists('node', CLIENT_BUNDLE, params);
  const raw = res.data?.rows ?? res.data ?? [];
  const rows = Array.isArray(raw) ? raw.map(normalizeClientRow) : [];
  const target = n.toLowerCase();
  return rows.find((r) => (r.title || '').trim().toLowerCase() === target) || null;
}

async function searchClients(q) {
  if (!q || q.length < 2) {
    return;
  }
  clientSearchLoading.value = true;
  try {
    const params = `sort[val]=title&sort[op]=ASC&offset=20&pager=0&filters[title][val]=${encodeURIComponent(q)}&filters[title][op]=CONTAINS`;
    const res = await getLists('node', CLIENT_BUNDLE, params);
    const raw = res.data?.rows ?? res.data ?? [];
    clientSuggestions.value = Array.isArray(raw) ? raw.map(normalizeClientRow) : [];
    showClientSuggestions.value = true;
  } catch (e) {
    console.error('Recherche clients:', e);
    clientSuggestions.value = [];
    showClientSuggestions.value = true;
  } finally {
    clientSearchLoading.value = false;
  }
}

async function openClientPickerModal() {
  showClientPickerModal.value = true;
  clientEditorOpen.value = true;
  showClientSuggestions.value = true;
  if (recentClients.value.length === 0 && !recentClientsLoading.value) {
    await loadRecentClients();
  }
  const q = orderClient.value.trim();
  if (q.length >= 2) {
    await searchClients(q);
  }
}

function closeClientPickerModal() {
  showClientPickerModal.value = false;
  showClientSuggestions.value = false;
}

function openClientEditor() {
  openClientPickerModal();
}

function applyClientName() {
  if (!orderClient.value.trim()) {
    return;
  }
  clientEditorOpen.value = false;
  closeClientPickerModal();
}

function pickClient(c) {
  orderClient.value = c.title;
  orderClientPhone.value = c.field_phone || '';
  selectedClientNid.value = c.nid;
  showClientSuggestions.value = false;
  clientSuggestions.value = [];
  clientEditorOpen.value = false;
  closeClientPickerModal();
}


async function saveNewClient() {
  const name = orderClient.value.trim();
  if (!name || savingClient.value) {
    return;
  }
  savingClient.value = true;
  try {
    const existing = await findClientByExactTitle(name);
    if (existing) {
      alert('Un client avec ce nom existe déjà. Sélectionnez-le dans la liste ou modifiez le nom.');
      return;
    }
    const res = await saveItem({
      entity_type: 'node',
      bundle: 'client',
      title: name,
      field_phone: orderClientPhone.value.trim(),
    });
    if (res.data?.status === true || res.data?.item || res.data?.id) {
      const nid = res.data?.item ?? res.data?.id ?? res.data?.nid;
      if (nid) {
        selectedClientNid.value = nid;
      }
      loadRecentClients();
      clientEditorOpen.value = false;
      closeClientPickerModal();
      toastType.value = 'success';
      toastMessage.value = 'Client enregistré';
      showToast.value = true;
      setTimeout(() => {
        showToast.value = false;
      }, 2500);
      showClientSuggestions.value = false;
    } else {
      alert(res.data?.message || 'Impossible de créer le client');
    }
  } catch (e) {
    console.error(e);
    alert(e.response?.data?.message || 'Erreur lors de la création du client');
  } finally {
    savingClient.value = false;
  }
}

const filteredProducts = computed(() => {
  if (imageSearchActive.value) {
    return [...imageSearchResults.value]
      .map(normalizeProductRow)
      .sort((a, b) => {
        const sa = Number(a._ai_score || 0);
        const sb = Number(b._ai_score || 0);
        if (sb !== sa) return sb - sa;
        return parseInt(String(productNid(b)), 10) - parseInt(String(productNid(a)), 10);
      });
  }
  return [...products.value].sort((a, b) => parseInt(String(productNid(b)), 10) - parseInt(String(productNid(a)), 10));
});

function onImageSelected(event) {
  const file = event.target.files?.[0];
  if (!file) return;
  if (!file.type.startsWith('image/')) {
    imageSearchError.value = 'Veuillez choisir une image (JPG, PNG, WebP).';
    return;
  }
  if (file.size > 8 * 1024 * 1024) {
    imageSearchError.value = 'Image trop volumineuse (max 8 Mo).';
    return;
  }
  imageFile.value = file;
  imageSearchError.value = '';
  generatedSearchText.value = '';
  imageSearchActive.value = false;
  imageSearchResults.value = [];
  const reader = new FileReader();
  reader.onload = (e) => {
    imagePreview.value = e.target?.result || '';
  };
  reader.readAsDataURL(file);
  runImageSearch();
}

async function runImageSearch() {
  if (!imageFile.value || imageSearching.value) return;
  imageSearching.value = true;
  imageSearchError.value = '';
  try {
    const res = await searchProductsByImage(imageFile.value, BUNDLE);
    const data = res?.data;
    if (!data?.status) {
      throw new Error(data?.message || 'Recherche IA échouée.');
    }
    generatedSearchText.value = data.field_search_image || '';
    imageSearchResults.value = data.rows || [];
    imageSearchMeta.value = {
      total: data.total ?? (data.rows?.length || 0),
      scanned: data.scanned ?? 0,
    };
    imageSearchActive.value = true;
    if (!imageSearchResults.value.length && data.message) {
      imageSearchError.value = data.message;
    }
  } catch (e) {
    imageSearchError.value = getApiErrorMessage(e, 'Erreur lors de la recherche par image.');
    imageSearchActive.value = false;
    imageSearchResults.value = [];
  } finally {
    imageSearching.value = false;
  }
}

function clearImageSearch() {
  imageFile.value = null;
  imagePreview.value = '';
  imageSearchResults.value = [];
  imageSearchActive.value = false;
  imageSearchError.value = '';
  generatedSearchText.value = '';
  imageSearchMeta.value = null;
  if (imageInputRef.value) {
    imageInputRef.value.value = '';
  }
}

const subtotal = computed(() => {
  return orderItems.value.reduce((total, item) => {
    const price = parseFloat(item.product.field_prix_vente || item.product.field_price || 0);
    return total + price * item.quantity;
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
  const existingItem = orderItems.value.find((item) => productNid(item.product) === productNid(product));

  if (existingItem) {
    existingItem.quantity++;
  } else {
    orderItems.value.push({
      product,
      quantity: 1,
    });
  }

  toastType.value = 'success';
  toastMessage.value = product.title;
  showToast.value = true;
  setTimeout(() => {
    showToast.value = false;
  }, 3000);
};

const increaseQuantity = (index) => {
  orderItems.value[index].quantity++;
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
  if (!orderClient.value.trim()) return;

  savingOrder.value = true;

  try {
    const payload = {
      items: orderItems.value.map((item) => ({
        product_nid: productNid(item.product),
        quantity: item.quantity,
        prix_unitaire: parseFloat(item.product.field_prix_vente || item.product.field_price || 0),
      })),
      client: buildClientLabel(),
      notes: orderNotes.value.trim(),
      client_nid: selectedClientNid.value || null,
      field_status_commande: orderStatusCommande.value,
    };

    const response = await saveOrderCommande(payload);

    if (response.data.status === true) {
      const order = {
        id: response.data.order_id,
        client: buildClientLabel(),
        client_nid: selectedClientNid.value,
        items: orderItems.value.map((item) => ({
          product_id: item.product.nid,
          product_name: item.product.title,
          quantity: item.quantity,
          price: item.product.field_prix_vente || item.product.field_price,
        })),
        total: response.data.total || subtotal.value,
        date: new Date().toISOString(),
        status: orderStatusCommande.value,
      };

      const savedOrders = JSON.parse(localStorage.getItem('orders_commande') || '[]');
      savedOrders.unshift(order);
      localStorage.setItem('orders_commande', JSON.stringify(savedOrders));

      showSuccessModal.value = true;
    } else {
      alert(response.data.message || 'Erreur lors de la création de la vente');
    }
  } catch (error) {
    console.error('Error creating order commande:', error);
    const msg = error.response?.data?.message || error.response?.data?.errors?.join('\n') || 'Erreur lors de la création de la vente';
    alert(msg);
  } finally {
    savingOrder.value = false;
  }
};

const closeSuccessModal = () => {
  showSuccessModal.value = false;
  orderItems.value = [];
  orderClient.value = '';
  orderClientPhone.value = '';
  orderNotes.value = '';
  clientSuggestions.value = [];
  showClientSuggestions.value = false;
  selectedClientNid.value = null;
  clientEditorOpen.value = true;
  showClientPickerModal.value = false;
  orderStatusCommande.value = 'draft';
};

const performSearch = () => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }

  searchTimeout.value = setTimeout(() => {
    loadProductCommande(false);
  }, 500);
};

const loadMore = () => {
  loadProductCommande(true);
};

watch([searchQuery, searchType], () => {
  if (imageSearchActive.value) return;
  performSearch();
});

onMounted(() => {
  loadProductCommande(false);
  loadRecentClients();
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
