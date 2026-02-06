<template>
  <div class="antialiased text-gray-900 min-h-screen bg-gray-50 overflow-x-hidden">
    <Sidebar />
    <router-view />
  </div>
</template>

<script setup>
import Sidebar from './components/Sidebar.vue';
</script>

<style>
/* Global styles */
body {
  margin: 0;
  overscroll-behavior-y: none;
}
</style>
