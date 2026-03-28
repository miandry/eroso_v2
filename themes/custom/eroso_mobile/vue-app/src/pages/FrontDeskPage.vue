<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-950 flex flex-col items-center justify-center px-4 py-10 font-sans">
    <div class="w-full max-w-2xl">
      <div class="text-center mb-10 text-white">
        <div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-4 border border-white/20 shadow-xl">
          <i class="ri-building-4-line text-3xl text-white"></i>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">Accueil</h1>
        <p class="text-blue-100/90 mt-2 text-sm sm:text-base max-w-md mx-auto">
          Choisissez l’espace auquel vous connecter pour continuer.
        </p>
      </div>

      <div class="grid sm:grid-cols-2 gap-4 sm:gap-6">
        <button
          v-for="app in apps"
          :key="app.id"
          type="button"
          @click="choose(app.id)"
          class="group text-left rounded-3xl p-6 sm:p-8 bg-white/95 backdrop-blur-xl border border-white/30 shadow-2xl transition-all duration-200 hover:scale-[1.02] hover:shadow-blue-500/20 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-white/50"
        >
          <div
            :class="[
              'w-14 h-14 rounded-2xl flex items-center justify-center text-2xl text-white mb-5 bg-gradient-to-br shadow-lg',
              app.accent,
            ]"
          >
            <i :class="app.icon"></i>
          </div>
          <h2 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-blue-700 transition-colors">
            {{ app.title }}
          </h2>
          <p class="text-sm text-gray-600 leading-relaxed">
            {{ app.description }}
          </p>
          <div class="mt-6 flex items-center text-sm font-semibold text-blue-600">
            <span>Continuer</span>
            <i class="ri-arrow-right-line ml-2 group-hover:translate-x-1 transition-transform"></i>
          </div>
        </button>
      </div>

      <p class="text-center mt-10 text-white/50 text-xs">
        Eroso Mobile — accès sécurisé
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { EROSO_APPS, setSelectedAppId, getHomePathForApp } from '../config/appContext';

const router = useRouter();

const apps = computed(() => Object.values(EROSO_APPS));

function choose(appId) {
  setSelectedAppId(appId);
  const token = localStorage.getItem('token');
  if (token) {
    router.push(getHomePathForApp(appId));
  } else {
    router.push('/login');
  }
}
</script>
