<template>
  <div class="public-profile min-h-screen bg-[#fdf2f9] font-sans pb-[calc(3.5rem+env(safe-area-inset-bottom))]">
    <header class="sticky top-0 z-40 bg-[#fdf2f9]/95 backdrop-blur-md pt-[env(safe-area-inset-top)] border-b border-[#e8d4f0]/60">
      <div class="flex items-center gap-3 px-4 py-3">
        <button
          type="button"
          class="w-9 h-9 rounded-full bg-white border border-[#e8d4f0] text-[#4b2c82] flex items-center justify-center"
          aria-label="Retour au catalogue"
          @click="router.push('/home')"
        >
          <i class="ri-arrow-left-s-line text-2xl"></i>
        </button>
        <h1 class="text-base font-bold text-[#4b2c82]">Profil</h1>
      </div>
    </header>

    <main class="max-w-lg mx-auto px-4 py-6">
      <div class="flex flex-col items-center text-center py-5">
        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-[#9b59b6] to-[#4b2c82] text-white flex items-center justify-center shadow-lg shadow-[#4b2c82]/20">
          <i class="ri-user-3-line text-4xl"></i>
        </div>
        <p class="mt-3 text-lg font-bold text-[#4b2c82]">{{ username || 'Mon profil' }}</p>
        <p v-if="phone && phone.trim()" class="mt-1 text-sm text-[#6b5878]">{{ phone.trim() }}</p>
      </div>

      <div class="mt-4 bg-white rounded-2xl border border-[#f0e4f7] overflow-hidden shadow-sm shadow-[#4b2c82]/5">
        <a
          href="/profile/orders"
          class="flex items-center gap-3 px-4 py-4 text-[#3d2a52] border-b border-[#f0e4f7]"
          @click.prevent="router.push('/profile/orders')"
        >
          <i class="ri-file-list-3-line text-xl text-[#8e44ad]"></i>
          <span class="font-semibold">Mes commandes</span>
          <i class="ri-arrow-right-s-line text-xl text-[#9b8aab] ml-auto"></i>
        </a>
        <button
          type="button"
          class="w-full flex items-center gap-3 px-4 py-4 text-red-600 font-semibold text-left"
          @click="handleLogout"
        >
          <i class="ri-logout-box-r-line text-xl"></i>
          <span>Déconnexion</span>
        </button>
      </div>
    </main>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { logout, logoutCrud } from '../services/api';

const router = useRouter();
const username = localStorage.getItem('username');
const phone = localStorage.getItem('phone');

async function handleLogout() {
  try {
    await logout();
  } catch (e) {
    try {
      await logoutCrud();
    } catch (_) {
      // Local logout still happens when the server is unavailable.
    }
  } finally {
    localStorage.removeItem('token');
    localStorage.removeItem('username');
    localStorage.removeItem('phone');
    localStorage.removeItem('uid');
    localStorage.removeItem('roles');
    router.push('/login');
  }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap');

.public-profile {
  font-family: 'Nunito', system-ui, -apple-system, sans-serif;
  -webkit-tap-highlight-color: transparent;
}
</style>