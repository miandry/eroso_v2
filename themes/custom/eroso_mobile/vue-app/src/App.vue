<template>
  <div class="antialiased text-gray-900 min-h-screen bg-gray-50 overflow-x-hidden">
    <Sidebar v-if="shouldShowSidebar" />
    <router-view />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from './components/Sidebar.vue';

const route = useRoute();

// Hide sidebar on login page (all devices).
const shouldShowSidebar = computed(() => {
  const nameOk = route.name !== 'login' && route.name !== 'front-desk';
  const path = route.path || '';
  const pathOk =
    path !== '/login' &&
    path !== '/login/' &&
    path !== '/front-desk' &&
    path !== '/front-desk/';
  return nameOk && pathOk;
});
</script>

<style>
/* Global styles */
body {
  margin: 0;
  overscroll-behavior-y: none;
}
</style>
