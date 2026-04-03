<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button type="button" @click="uiStore.toggleSidebar" class="p-1 -ml-1 text-gray-600 cursor-pointer lg:hidden">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <h1 class="text-lg font-semibold text-gray-900">Eroso sur commande</h1>
        </div>
      </div>
    </nav>

    <main class="pt-16 pb-24 px-4 lg:ml-64 max-w-2xl">
      <div class="bg-gradient-to-br from-indigo-600 to-violet-800 rounded-3xl p-8 text-white shadow-xl mb-6">
        <div class="flex items-start space-x-4">
          <div class="w-14 h-14 rounded-2xl bg-white/15 flex items-center justify-center shrink-0">
            <i class="ri-shopping-bag-3-line text-3xl"></i>
          </div>
          <div>
            <h2 class="text-xl font-bold">Espace sur commande</h2>
            <p class="text-indigo-100 text-sm mt-2 leading-relaxed">
              Cet espace est en cours de construction : commandes, préparation et suivi dédiés.
            </p>
          </div>
        </div>
      </div>

      <router-link
        to="/sur-commande/products"
        class="flex items-center justify-between w-full bg-white rounded-2xl border border-indigo-100 p-4 shadow-sm mb-4 active:scale-[0.99] transition-transform"
      >
        <div class="flex items-center space-x-3">
          <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
            <i class="ri-box-3-line text-2xl"></i>
          </div>
          <div class="text-left">
            <p class="text-sm font-bold text-gray-900">Catalogue sur commande</p>
            <p class="text-xs text-gray-500">Produits (type product_commande)</p>
          </div>
        </div>
        <i class="ri-arrow-right-s-line text-xl text-indigo-400"></i>
      </router-link>

      <router-link
        to="/sur-commande/caisse"
        class="flex items-center justify-between w-full bg-white rounded-2xl border border-indigo-100 p-4 shadow-sm mb-4 active:scale-[0.99] transition-transform"
      >
        <div class="flex items-center space-x-3">
          <div class="w-12 h-12 rounded-xl bg-violet-50 flex items-center justify-center text-violet-600">
            <i class="ri-store-2-line text-2xl"></i>
          </div>
          <div class="text-left">
            <p class="text-sm font-bold text-gray-900">Caisse sur commande</p>
            <p class="text-xs text-gray-500">Vente avec catalogue product_commande</p>
          </div>
        </div>
        <i class="ri-arrow-right-s-line text-xl text-indigo-400"></i>
      </router-link>

      <router-link
        to="/sur-commande/orders"
        class="flex items-center justify-between w-full bg-white rounded-2xl border border-indigo-100 p-4 shadow-sm mb-6 active:scale-[0.99] transition-transform"
      >
        <div class="flex items-center space-x-3">
          <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
            <i class="ri-file-list-3-line text-2xl"></i>
          </div>
          <div class="text-left">
            <p class="text-sm font-bold text-gray-900">Liste des commandes</p>
            <p class="text-xs text-gray-500">Nœuds order_commande (API)</p>
          </div>
        </div>
        <i class="ri-arrow-right-s-line text-xl text-indigo-400"></i>
      </router-link>

      <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
        <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">À venir</h3>
        <ul class="space-y-3 text-gray-700 text-sm">
          <li class="flex items-center space-x-2">
            <i class="ri-checkbox-blank-circle-line text-indigo-400"></i>
            <span>Suivi de préparation détaillé</span>
          </li>
        </ul>
      </div>
    </main>
  </div>
</template>

<script setup>
import { useUIStore } from '../../stores/useUIStore';

const uiStore = useUIStore();
</script>
