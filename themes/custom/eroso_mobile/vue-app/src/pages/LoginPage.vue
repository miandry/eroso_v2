<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-600 to-indigo-900 flex items-center justify-center px-4 font-sans">
    <div class="max-w-md w-full">
      <!-- Logo / Header -->
      <div class="text-center mb-10 text-white">
        <div class="w-20 h-20 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-4 border border-white/30 shadow-xl">
          <i class="ri-box-3-fill text-4xl text-white"></i>
        </div>
        <h1 class="text-3xl font-bold tracking-tight">Stock Manager</h1>
        <p class="text-blue-100 mt-2 opacity-80">Connectez-vous pour continuer</p>
      </div>

      <!-- Login Card -->
      <div class="bg-white/90 backdrop-blur-xl rounded-3xl p-8 shadow-2xl border border-white/20">
        <form @submit.prevent="handleLogin" class="space-y-6">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2 ml-1">Nom d'utilisateur</label>
            <div class="relative">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                <i class="ri-user-line"></i>
              </span>
              <input 
                type="text" 
                v-model="credentials.name" 
                required
                placeholder="Votre nom"
                class="w-full pl-11 pr-4 py-4 bg-gray-50 border-none rounded-2xl text-base focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all outline-none"
              >
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2 ml-1">Mot de passe</label>
            <div class="relative">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                <i class="ri-lock-line"></i>
              </span>
              <input 
                type="password" 
                v-model="credentials.password" 
                required
                placeholder="••••••••"
                class="w-full pl-11 pr-4 py-4 bg-gray-50 border-none rounded-2xl text-base focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all outline-none"
              >
            </div>
          </div>

          <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-2xl text-sm flex items-start space-x-2 animate-pulse">
            <i class="ri-error-warning-line flex-shrink-0 mt-0.5"></i>
            <span>{{ error }}</span>
          </div>

          <button 
            type="submit" 
            :disabled="loading"
            class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-500/30 transition-all active:scale-[0.98] flex items-center justify-center space-x-2 cursor-pointer"
          >
            <i v-if="loading" class="ri-loader-4-line animate-spin text-xl"></i>
            <span>{{ loading ? 'Connexion...' : 'Se connecter' }}</span>
          </button>
        </form>

        <div class="mt-8 pt-8 border-t border-gray-100 text-center">
          <p class="text-sm text-gray-500">
            Problème de connexion ? <a href="#" class="text-blue-600 font-semibold hover:underline">Support</a>
          </p>
        </div>
      </div>

      <!-- Footer -->
      <p class="text-center mt-8 text-white/60 text-sm">
        &copy; 2026 Eroso Mobile. Tous droits réservés.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../services/api.js';

const router = useRouter();
const credentials = ref({ name: '', password: '' });
const loading = ref(false);
const error = ref(null);

const handleLogin = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    const response = await login(credentials.value);
    
    if (response.data.status === true) {
      localStorage.setItem('token', response.data.token);
      localStorage.setItem('username', credentials.value.name);
      localStorage.setItem('uid', response.data.id);
      
      router.push('/stock-manager');
    } else {
      error.value = response.data.message || 'Identifiants invalides';
    }
  } catch (err) {
    console.error('Login error:', err);
    error.value = 'Une erreur est survenue lors de la connexion. Veuillez réessayer.';
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
/* Any additional custom styling */
</style>
