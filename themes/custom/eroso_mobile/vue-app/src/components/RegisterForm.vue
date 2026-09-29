<template>
  <form @submit.prevent="handleRegister" class="space-y-6">
    <div>
      <label for="register-username" class="block text-sm font-semibold text-gray-700 mb-2 ml-1">Nom d'utilisateur</label>
      <div class="relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
          <i class="ri-user-line"></i>
        </span>
        <input
          id="register-username"
          v-model="credentials.username"
          type="text"
          autocomplete="username"
          required
          placeholder="Votre nom"
          class="w-full pl-11 pr-4 py-4 bg-gray-50 border-none rounded-2xl text-base focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all outline-none"
        >
      </div>
    </div>

    <div>
      <label for="register-phone" class="block text-sm font-semibold text-gray-700 mb-2 ml-1">Numéro de téléphone</label>
      <div class="relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
          <i class="ri-phone-line"></i>
        </span>
        <input
          id="register-phone"
          v-model="credentials.phone"
          type="tel"
          autocomplete="tel"
          required
          maxlength="55"
          placeholder="Votre numéro de téléphone"
          class="w-full pl-11 pr-4 py-4 bg-gray-50 border-none rounded-2xl text-base focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all outline-none"
        >
      </div>
    </div>

    <div>
      <label for="register-password" class="block text-sm font-semibold text-gray-700 mb-2 ml-1">Mot de passe</label>
      <div class="relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
          <i class="ri-lock-line"></i>
        </span>
        <input
          id="register-password"
          v-model="credentials.password"
          type="password"
          autocomplete="new-password"
          required
          placeholder="••••••••"
          class="w-full pl-11 pr-4 py-4 bg-gray-50 border-none rounded-2xl text-base focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all outline-none"
        >
      </div>
    </div>

    <div>
      <label for="register-password-confirmation" class="block text-sm font-semibold text-gray-700 mb-2 ml-1">Confirmer le mot de passe</label>
      <div class="relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
          <i class="ri-lock-password-line"></i>
        </span>
        <input
          id="register-password-confirmation"
          v-model="credentials.passwordConfirmation"
          type="password"
          autocomplete="new-password"
          required
          placeholder="••••••••"
          class="w-full pl-11 pr-4 py-4 bg-gray-50 border-none rounded-2xl text-base focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all outline-none"
        >
      </div>
    </div>

    <p v-if="message" :class="['p-4 rounded-2xl text-sm', messageType === 'error' ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-700']" role="status" aria-live="polite">
      {{ message }}
    </p>

    <button
      type="submit"
      :disabled="loading"
      class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-500/30 transition-all active:scale-[0.98] flex items-center justify-center space-x-2 cursor-pointer"
    >
      <i :class="loading ? 'ri-loader-4-line animate-spin text-xl' : 'ri-user-add-line text-xl'"></i>
      <span>{{ loading ? 'Création...' : 'Créer mon compte' }}</span>
    </button>
  </form>
</template>

<script setup>
import { ref } from 'vue';
import { register } from '../services/api.js';

const emit = defineEmits(['registered']);
const credentials = ref({ username: '', phone: '', password: '', passwordConfirmation: '' });
const message = ref('');
const messageType = ref('error');
const loading = ref(false);

async function handleRegister() {
  message.value = '';

  const username = credentials.value.username;
  if (Array.from(username).length < 5 || /\s/u.test(username)) {
    message.value = 'Le nom d’utilisateur doit contenir au moins 5 caractères et aucun espace.';
    return;
  }

  if (credentials.value.password !== credentials.value.passwordConfirmation) {
    message.value = 'Les mots de passe ne correspondent pas.';
    return;
  }

  loading.value = true;
  try {
    const response = await register({
      name: username,
      phone: credentials.value.phone.trim(),
      password: credentials.value.password,
    });

    if (response.data.status !== true) {
      message.value = response.data.message || response.data.error || 'L’inscription a échoué.';
      return;
    }

    emit('registered', { name: username, ...response.data });
  } catch (error) {
    message.value = error.response?.data?.message || error.response?.data?.error || 'Une erreur est survenue lors de la création du compte.';
  } finally {
    loading.value = false;
  }
}
</script>