<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button @click="uiStore.toggleSidebar" class="p-1 -ml-1 text-gray-600 cursor-pointer lg:hidden">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <h1 class="text-lg font-semibold text-gray-900">Réglages IA</h1>
        </div>
      </div>
    </nav>

    <main class="pt-16 pb-24 px-4 lg:ml-64 max-w-2xl">
      <p class="text-sm text-gray-600 mb-6">
        Choisissez le moteur d'intelligence artificielle utilisé pour l'analyse d'images produit,
        la recherche visuelle et le remplissage automatique du catalogue.
      </p>

      <div v-if="loading" class="flex flex-col items-center justify-center py-16 space-y-3">
        <div class="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-gray-500">Chargement…</p>
      </div>

      <div v-else class="space-y-4">
        <div
          v-if="lockedBySettings"
          class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
          <i class="ri-lock-line mr-1"></i>
          Le fournisseur est verrouillé dans <code class="text-xs bg-amber-100 px-1 rounded">settings.php</code>
          (<code class="text-xs bg-amber-100 px-1 rounded">$settings['mz_ai_provider']</code>).
          Modifiez ce fichier pour changer le moteur IA.
        </div>

        <div
          v-for="provider in providers"
          :key="provider.id"
          @click="selectProvider(provider.id)"
          :class="[
            'rounded-2xl border-2 p-4 transition-all cursor-pointer',
            selectedProvider === provider.id
              ? 'border-blue-600 bg-blue-50 shadow-md'
              : 'border-gray-200 bg-white hover:border-blue-300',
            lockedBySettings ? 'pointer-events-none opacity-80' : '',
          ]"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
              <div
                :class="[
                  'w-11 h-11 rounded-xl flex items-center justify-center text-xl',
                  providerIconClass(provider.id),
                ]"
              >
                <i :class="providerIcon(provider.id)"></i>
              </div>
              <div>
                <h2 class="font-bold text-gray-900">{{ provider.label }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">Modèle : {{ provider.model }}</p>
              </div>
            </div>
            <div
              v-if="selectedProvider === provider.id"
              class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-white shrink-0"
            >
              <i class="ri-check-line text-sm"></i>
            </div>
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span
              :class="[
                'text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full',
                provider.configured ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500',
              ]"
            >
              {{ provider.configured ? 'Clé API configurée' : 'Clé API manquante' }}
            </span>
            <span
              v-if="activeProvider === provider.id"
              class="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-blue-100 text-blue-700"
            >
              Actif
            </span>
          </div>

          <div v-if="provider.usage" class="mt-3 pt-3 border-t border-gray-100 space-y-1">
            <p class="text-xs font-semibold text-gray-700">
              Coût estimé (app Eroso) :
              <span class="text-gray-900">{{ provider.usage.cost_usd_formatted || '$0.00' }} USD</span>
            </p>
            <p class="text-[11px] text-gray-500">
              {{ provider.usage.requests || 0 }} appel{{ (provider.usage.requests || 0) > 1 ? 's' : '' }}
              · {{ formatTokens(provider.usage.input_tokens) }} tokens entrée
              · {{ formatTokens(provider.usage.output_tokens) }} tokens sortie
            </p>
            <p v-if="provider.usage.last_request_label" class="text-[10px] text-gray-400">
              Dernier appel : {{ provider.usage.last_request_label }}
            </p>
          </div>
        </div>

        <p v-if="usageNote" class="text-[11px] text-gray-500 leading-relaxed px-1">
          {{ usageNote }}
        </p>

        <div
          v-if="errorMessage"
          class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
          {{ errorMessage }}
        </div>

        <div
          v-if="successMessage"
          class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
        >
          {{ successMessage }}
        </div>

        <button
          v-if="!lockedBySettings"
          type="button"
          @click="saveSettings"
          :disabled="saving || selectedProvider === activeProvider"
          class="w-full py-3.5 rounded-xl font-bold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <span v-if="saving">Enregistrement…</span>
          <span v-else>Enregistrer le fournisseur IA</span>
        </button>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useUIStore } from '../stores/useUIStore';
import { getAiSettings, saveAiSettings } from '../services/api';

const uiStore = useUIStore();
const router = useRouter();

const loading = ref(true);
const saving = ref(false);
const providers = ref([]);
const activeProvider = ref('gemini');
const selectedProvider = ref('gemini');
const lockedBySettings = ref(false);
const usageNote = ref('');
const errorMessage = ref('');
const successMessage = ref('');

const isAdmin = () => {
  try {
    const roles = JSON.parse(localStorage.getItem('roles') || '[]');
    return Array.isArray(roles) && roles.includes('administrator');
  } catch {
    return false;
  }
};

const providerIcon = (id) => {
  if (id === 'gemini') return 'ri-sparkling-2-line';
  if (id === 'claude') return 'ri-robot-2-line';
  if (id === 'chatgpt') return 'ri-openai-fill';
  return 'ri-cpu-line';
};

const providerIconClass = (id) => {
  if (id === 'gemini') return 'bg-blue-100 text-blue-600';
  if (id === 'claude') return 'bg-orange-100 text-orange-600';
  if (id === 'chatgpt') return 'bg-emerald-100 text-emerald-600';
  return 'bg-gray-100 text-gray-600';
};

const selectProvider = (id) => {
  if (lockedBySettings.value) return;
  selectedProvider.value = id;
  errorMessage.value = '';
  successMessage.value = '';
};

const formatTokens = (n) => {
  const value = Number(n) || 0;
  if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(2)} M`;
  if (value >= 1_000) return `${(value / 1_000).toFixed(1)} k`;
  return String(value);
};

const loadSettings = async () => {
  loading.value = true;
  errorMessage.value = '';
  try {
    const res = await getAiSettings();
    const data = res.data || {};
    if (!data.status) {
      throw new Error(data.message || 'Impossible de charger les réglages IA.');
    }
    providers.value = data.providers || [];
    activeProvider.value = data.ai_provider || 'gemini';
    selectedProvider.value = activeProvider.value;
    lockedBySettings.value = !!data.locked_by_settings;
    usageNote.value = data.usage_note || '';
  } catch (e) {
    errorMessage.value = e.response?.data?.message || e.message || 'Erreur de chargement.';
  } finally {
    loading.value = false;
  }
};

const saveSettings = async () => {
  if (lockedBySettings.value || selectedProvider.value === activeProvider.value) return;
  saving.value = true;
  errorMessage.value = '';
  successMessage.value = '';
  try {
    const res = await saveAiSettings(selectedProvider.value);
    const data = res.data || {};
    if (!data.status) {
      throw new Error(data.message || 'Enregistrement impossible.');
    }
    activeProvider.value = data.ai_provider || selectedProvider.value;
    selectedProvider.value = activeProvider.value;
    providers.value = data.providers || providers.value;
    successMessage.value = data.message || 'Fournisseur IA mis à jour.';
  } catch (e) {
    errorMessage.value = e.response?.data?.message || e.message || 'Erreur lors de l\'enregistrement.';
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  if (!isAdmin()) {
    router.replace('/');
    return;
  }
  loadSettings();
});
</script>
