<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <!-- Navigation Haute -->
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button @click="uiStore.toggleSidebar" class="p-1 -ml-1 text-gray-600 cursor-pointer">
            <i class="ri-menu-2-line ri-lg"></i>
          </button>
          <h1 class="text-lg font-semibold text-gray-900">Statistiques</h1>
        </div>
        <div class="flex items-center space-x-2">
          <button @click="refreshStats" class="w-8 h-8 flex items-center justify-center text-blue-600 bg-blue-50 rounded-lg cursor-pointer">
            <i class="ri-refresh-line" :class="{ 'animate-spin': loading }"></i>
          </button>
        </div>
      </div>
    </nav>

    <main class="pt-16 pb-24 px-4">
      <!-- Period Filter -->
      <div class="mb-6">
        <div class="flex items-center space-x-2 overflow-x-auto pb-2 scrollbar-hide">
          <button 
            v-for="period in periods" 
            :key="period.value"
            @click="selectedPeriod = period.value"
            :class="['px-4 py-2 rounded-full text-xs font-medium whitespace-nowrap transition-colors', 
                    selectedPeriod === period.value ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-100']"
          >
            {{ period.label }}
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20 space-y-4">
        <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-gray-500 font-medium">Chargement des statistiques...</p>
      </div>

      <div v-else class="space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-3 text-white shadow-lg">
            <div class="flex items-center justify-between mb-1">
              <i class="ri-box-3-line text-lg opacity-80"></i>
              <span class="text-[10px] font-semibold bg-white/20 px-1.5 py-0.5 rounded-full">Produits</span>
            </div>
            <div class="text-xl font-black">{{ stats.totalProducts }}</div>
            <div class="text-[10px] opacity-90 mt-0.5">Total catalogue</div>
          </div>

          <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-3 text-white shadow-lg">
            <div class="flex items-center justify-between mb-1">
              <i class="ri-stock-line text-lg opacity-80"></i>
              <span class="text-[10px] font-semibold bg-white/20 px-1.5 py-0.5 rounded-full">Stock Dispo</span>
            </div>
            <div class="text-base font-black">{{ stats.totalStock }} unités</div>
            <div class="text-sm font-bold mt-0.5">{{ formatPrice(stats.totalStockValue) }} Ar</div>
            <div class="text-[10px] opacity-90 mt-0.5">Stock disponible</div>
          </div>

          <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-3 text-white shadow-lg">
            <div class="flex items-center justify-between mb-1">
              <i class="ri-arrow-up-line text-lg opacity-80"></i>
              <span class="text-[10px] font-semibold bg-white/20 px-1.5 py-0.5 rounded-full">Entrées</span>
            </div>
            <div class="text-base font-black">{{ stats.totalIn }} unités</div>
            <div class="text-sm font-bold mt-0.5">{{ formatPrice(stats.totalInValue) }} Ar</div>
            <div class="text-[10px] opacity-90 mt-0.5">{{ selectedPeriod }}</div>
          </div>

          <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-3 text-white shadow-lg">
            <div class="flex items-center justify-between mb-1">
              <i class="ri-arrow-down-line text-lg opacity-80"></i>
              <span class="text-[10px] font-semibold bg-white/20 px-1.5 py-0.5 rounded-full">Sorties</span>
            </div>
            <div class="text-base font-black">{{ stats.totalOut }} unités</div>
            <div class="text-sm font-bold mt-0.5">{{ formatPrice(stats.totalOutValue) }} Ar</div>
            <div class="text-[10px] opacity-90 mt-0.5">{{ selectedPeriod }}</div>
          </div>
        </div>

        <!-- Daily Sales Chart -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-gray-900">Ventes Journalières</h3>
            <div class="flex items-center space-x-2">
              <span class="text-xs text-gray-500">{{ dailySalesData.length }} jours</span>
              <i class="ri-bar-chart-line text-lg text-blue-600"></i>
            </div>
          </div>
          <div v-if="dailySalesData.length === 0" class="py-8 text-center">
            <i class="ri-line-chart-line text-4xl text-gray-300 mb-2"></i>
            <p class="text-sm text-gray-500">Aucune donnée de vente disponible</p>
            <p class="text-xs text-gray-400 mt-1">Exits: {{ exitsList.length }}</p>
          </div>
          <div v-else class="space-y-2">
            <!-- Chart Bars -->
            <div class="flex items-end justify-between h-40 space-x-1 bg-gray-50 rounded-lg p-2">
              <div 
                v-for="(day, index) in dailySalesData" 
                :key="index"
                class="flex-1 flex flex-col items-center justify-end group"
              >
                <div class="relative w-full h-full flex items-end">
                  <!-- Tooltip -->
                  <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10 pointer-events-none">
                    {{ formatPrice(day.amount) }} Ar
                    <div class="text-[10px] text-gray-300">{{ day.units }} unités</div>
                  </div>
                  <!-- Bar -->
                  <div 
                    class="w-full bg-gradient-to-t from-orange-500 to-orange-400 rounded-t transition-all duration-300 hover:from-orange-600 hover:to-orange-500 min-h-[8px]"
                    :style="{ height: Math.max(day.height, 5) + '%' }"
                  ></div>
                </div>
                <!-- Date Label -->
                <div class="text-[10px] text-gray-500 mt-2 text-center whitespace-nowrap">{{ day.label }}</div>
              </div>
            </div>
            <!-- Summary -->
            <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
              <div>
                <div class="text-xs text-gray-500">Total ventes</div>
                <div class="text-lg font-bold text-orange-600">{{ formatPrice(totalDailySales) }} Ar</div>
              </div>
              <div class="text-right">
                <div class="text-xs text-gray-500">Unités vendues</div>
                <div class="text-lg font-bold text-gray-900">{{ totalDailyUnits }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Value Cards -->
        <div class="grid grid-cols-1 gap-4">
          <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-xs font-bold text-gray-900">Valeur totale du stock</h3>
              <i class="ri-money-dollar-circle-line text-lg text-green-600"></i>
            </div>
            <div class="text-xl font-black text-green-600">
              {{ formatPrice(stats.totalStockValue) }} <span class="text-xs">Ar</span>
            </div>
            <div class="text-[10px] text-gray-500 mt-1">Basé sur le prix de vente</div>
          </div>

          <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-xs font-bold text-gray-900">Chiffre d'affaires potentiel</h3>
              <i class="ri-line-chart-line text-lg text-blue-600"></i>
            </div>
            <div class="text-xl font-black text-blue-600">
              {{ formatPrice(stats.potentialRevenue) }} <span class="text-xs">Ar</span>
            </div>
            <div class="text-[10px] text-gray-500 mt-1">Si tout le stock est vendu</div>
          </div>
        </div>

        <!-- Categories Breakdown -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
          <div class="p-4 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-900">Répartition par catégorie</h3>
          </div>
          <div v-if="categoryStats.length === 0" class="p-8 text-center">
            <i class="ri-pie-chart-line text-4xl text-gray-300 mb-2"></i>
            <p class="text-sm text-gray-500">Aucune catégorie disponible</p>
          </div>
          <div v-else class="p-4 space-y-3">
            <div 
              v-for="cat in categoryStats" 
              :key="cat.name"
              class="flex items-center justify-between"
            >
              <div class="flex-1">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-sm font-semibold text-gray-900">{{ cat.name }}</span>
                  <span class="text-xs text-gray-500">{{ cat.count }} produits</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                  <div 
                    class="bg-blue-600 h-2 rounded-full transition-all duration-500"
                    :style="{ width: cat.percentage + '%' }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Stock Entries List -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
          <div class="p-4 border-b border-gray-100">
            <div class="flex items-center justify-between">
              <h3 class="text-base font-bold text-gray-900">Produits Entrées</h3>
              <span class="text-xs text-purple-600 font-semibold bg-purple-50 px-2 py-1 rounded-full">{{ entriesList.length }}</span>
            </div>
          </div>
          <div v-if="entriesList.length === 0" class="p-8 text-center">
            <i class="ri-inbox-line text-4xl text-gray-300 mb-2"></i>
            <p class="text-sm text-gray-500">Aucune entrée pour cette période</p>
          </div>
          <div v-else class="divide-y divide-gray-100">
            <div 
              v-for="entry in entriesList" 
              :key="entry.nid"
              @click="goToProduct(entry.product?.nid)"
              class="p-4 hover:bg-gray-50 transition-colors cursor-pointer"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h4 class="text-sm font-semibold text-gray-900">{{ entry.product_title || entry.title }}</h4>
                  <p v-if="entry.field_raison" class="text-xs text-gray-600 mt-1">{{ entry.field_raison }}</p>
                  <div class="flex items-center space-x-2 mt-2">
                    <span class="text-xs text-gray-500">{{ formatDate(entry.created) }}</span>
                  </div>
                </div>
                <div class="text-right ml-4">
                  <div class="text-sm font-bold text-purple-600">+{{ entry.field_quantite }} unités</div>
                  <div class="text-xs text-gray-500 mt-1">{{ formatPrice(entry.field_prix_de_vente * entry.field_quantite) }} Ar</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Stock Exits List -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
          <div class="p-4 border-b border-gray-100">
            <div class="flex items-center justify-between">
              <h3 class="text-base font-bold text-gray-900">Produits Sorties</h3>
              <span class="text-xs text-orange-600 font-semibold bg-orange-50 px-2 py-1 rounded-full">{{ exitsList.length }}</span>
            </div>
          </div>
          <div v-if="exitsList.length === 0" class="p-8 text-center">
            <i class="ri-inbox-line text-4xl text-gray-300 mb-2"></i>
            <p class="text-sm text-gray-500">Aucune sortie pour cette période</p>
          </div>
          <div v-else class="divide-y divide-gray-100">
            <div 
              v-for="exit in exitsList" 
              :key="exit.nid"
              @click="goToProduct(exit.product?.nid)"
              class="p-4 hover:bg-gray-50 transition-colors cursor-pointer"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h4 class="text-sm font-semibold text-gray-900">{{ exit.product_title || exit.title }}</h4>
                  <p v-if="exit.field_raison" class="text-xs text-gray-600 mt-1">{{ exit.field_raison }}</p>
                  <div class="flex items-center space-x-2 mt-2">
                    <span class="text-xs text-gray-500">{{ formatDate(exit.created) }}</span>
                  </div>
                </div>
                <div class="text-right ml-4">
                  <div class="text-sm font-bold text-orange-600">-{{ exit.field_quantite }} unités</div>
                  <div class="text-xs text-gray-500 mt-1">{{ formatPrice(exit.field_prix_de_vente * exit.field_quantite) }} Ar</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 w-full bg-white border-t border-gray-200 z-50">
      <div class="grid grid-cols-4 h-16">
        <router-link to="/stock-manager" class="flex flex-col items-center justify-center space-y-1 text-gray-400">
          <i class="ri-home-line ri-lg"></i>
          <span class="text-xs">Accueil</span>
        </router-link>
        <router-link to="/products" class="flex flex-col items-center justify-center space-y-1 text-gray-400">
          <i class="ri-box-3-line ri-lg"></i>
          <span class="text-xs">Produits</span>
        </router-link>
        <router-link to="/statistics" class="flex flex-col items-center justify-center space-y-1 text-blue-600">
          <i class="ri-bar-chart-fill ri-lg"></i>
          <span class="text-xs font-semibold">Stats</span>
        </router-link>
        <button class="flex flex-col items-center justify-center space-y-1 text-gray-400 cursor-pointer">
          <i class="ri-settings-3-line ri-lg"></i>
          <span class="text-xs">Paramètres</span>
        </button>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useUIStore } from '../stores/useUIStore';
import { useProductStore } from '../stores/useProductStore';
import { getStockStats, getStockEntries, getStockExits } from '../services/api';

const router = useRouter();

const uiStore = useUIStore();
const productStore = useProductStore();

const loading = ref(false);
const selectedPeriod = ref('Aujourd\'hui');
const stockStats = ref(null);
const entriesList = ref([]);
const exitsList = ref([]);
const allProducts = ref([]);

const periods = [
  { label: 'Aujourd\'hui', value: 'today' },
  { label: '7 jours', value: '7days' },
  { label: '30 jours', value: '30days' }
];

// Map period labels to API values
const getPeriodApiValue = (period) => {
  const periodMap = {
    'Aujourd\'hui': 'today',
    '7 jours': '7days',
    '30 jours': '30days'
  };
  return periodMap[period] || 'today';
};

const stats = computed(() => {
  if (!stockStats.value) {
    return {
      totalProducts: 0,
      totalStock: 0,
      totalStockValue: 0,
      potentialRevenue: 0,
      totalIn: 0,
      totalOut: 0,
      totalInValue: 0,
      totalOutValue: 0
    };
  }

  const data = stockStats.value.data;
  
  return {
    totalProducts: data.products?.total_products || 0,
    totalStock: data.products?.total_stock || 0,
    totalStockValue: data.products?.total_stock_value || 0,
    potentialRevenue: data.products?.total_stock_value || 0,
    totalIn: data.entries?.total_units || 0,
    totalOut: data.exits?.total_units || 0,
    totalInValue: data.entries?.total_value || 0,
    totalOutValue: data.exits?.total_value || 0
  };
});

const lowStockProducts = computed(() => {
  return allProducts.value
    .filter(p => parseInt(p.field_quantite_disponible || 0) <= 5)
    .sort((a, b) => parseInt(a.field_quantite_disponible || 0) - parseInt(b.field_quantite_disponible || 0));
});

// Daily sales data computed property - Always shows last 7 days
const dailySalesData = computed(() => {
  // Create array for last 7 days
  const last7Days = [];
  const today = new Date();
  
  for (let i = 6; i >= 0; i--) {
    const date = new Date(today);
    date.setDate(date.getDate() - i);
    date.setHours(0, 0, 0, 0);
    const dateKey = date.toISOString().split('T')[0];
    
    last7Days.push({
      date: dateKey,
      dateObj: date,
      amount: 0,
      units: 0
    });
  }
  
  // Group exits by date (only from last 7 days)
  if (exitsList.value && exitsList.value.length > 0) {
    const sevenDaysAgo = new Date(today);
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
    sevenDaysAgo.setHours(0, 0, 0, 0);
    
    exitsList.value.forEach(exit => {
      const exitDate = new Date(exit.created * 1000);
      exitDate.setHours(0, 0, 0, 0);
      
      // Only include exits from last 7 days
      if (exitDate >= sevenDaysAgo) {
        const dateKey = exitDate.toISOString().split('T')[0];
        const dayData = last7Days.find(d => d.date === dateKey);
        
        if (dayData) {
          const amount = exit.calculated_value || (exit.field_quantite * exit.field_prix_de_vente) || 0;
          dayData.amount += amount;
          dayData.units += parseFloat(exit.field_quantite || 0);
        }
      }
    });
  }
  
  // Get max amount for height calculation
  const maxAmount = Math.max(...last7Days.map(s => s.amount), 1);
  
  // Format data for chart
  return last7Days.map(sale => {
    const date = new Date(sale.date);
    const label = date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    
    return {
      date: sale.date,
      label: label,
      amount: sale.amount,
      units: sale.units,
      height: (sale.amount / maxAmount) * 100
    };
  });
});

// Total daily sales
const totalDailySales = computed(() => {
  return dailySalesData.value.reduce((sum, day) => sum + day.amount, 0);
});

// Total daily units
const totalDailyUnits = computed(() => {
  return dailySalesData.value.reduce((sum, day) => sum + day.units, 0);
});

const categoryStats = computed(() => {
  const categoryMap = {};
  const total = allProducts.value.length;

  allProducts.value.forEach(product => {
    let catName = 'Sans catégorie';
    if (product.field_category && product.field_category.title) {
      catName = product.field_category.title;
    } else if (Array.isArray(product.field_category) && product.field_category.length > 0) {
      catName = product.field_category[0].title;
    }

    if (!categoryMap[catName]) {
      categoryMap[catName] = 0;
    }
    categoryMap[catName]++;
  });

  return Object.entries(categoryMap)
    .map(([name, count]) => ({
      name,
      count,
      percentage: total > 0 ? ((count / total) * 100).toFixed(1) : 0
    }))
    .sort((a, b) => b.count - a.count);
});

const formatPrice = (price) => {
  if (!price) return '0';
  return Number(price).toLocaleString('fr-MG');
};

const formatDate = (timestamp) => {
  if (!timestamp) return 'Récemment';
  const date = isNaN(timestamp) ? new Date(timestamp) : new Date(timestamp * 1000);
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
};

const goToProduct = (productId) => {
  if (productId) {
    router.push(`/product/${productId}`);
  }
};

const refreshStats = async () => {
  loading.value = true;
  try {
    const period = getPeriodApiValue(selectedPeriod.value);
    
    // Fetch stock statistics
    const statsResponse = await getStockStats(period);
    stockStats.value = statsResponse.data;
    
    // Fetch stock entries
    const entriesResponse = await getStockEntries(period, 50, 0);
    entriesList.value = entriesResponse.data.data || [];
    
    // Fetch stock exits
    const exitsResponse = await getStockExits(period, 50, 0);
    exitsList.value = exitsResponse.data.data || [];
    
    // Fetch all products for category stats
    await productStore.fetchProducts(false, {});
    allProducts.value = productStore.products;
  } catch (err) {
    console.error('Error loading statistics:', err);
  } finally {
    loading.value = false;
  }
};

// Watch for period changes and reload data
watch(selectedPeriod, async () => {
  await refreshStats();
});

onMounted(() => {
  refreshStats();
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
</style>
