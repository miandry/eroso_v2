<template>
  <div class="bg-gray-50 font-sans min-h-screen">
    <!-- Navigation Haute -->
    <nav class="fixed top-0 w-full bg-white shadow-sm z-50 lg:ml-64">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center space-x-3">
          <button @click="uiStore.toggleSidebar" class="p-1 -ml-1 text-gray-600 cursor-pointer lg:hidden">
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

    <main class="pt-16 pb-24 px-4 lg:ml-64">
      <!-- Period Filter -->
      <div class="mb-6">
        <div class="flex items-center space-x-2 overflow-x-auto pb-2 scrollbar-hide">
          <button 
            v-for="period in periods" 
            :key="period.value"
            @click="selectedPeriod = period.label"
            :class="['px-4 py-2 rounded-full text-xs font-medium whitespace-nowrap transition-colors', 
                    selectedPeriod === period.label ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-100']"
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

          <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-2xl p-3 text-white shadow-lg">
            <div class="flex items-center justify-between mb-1">
              <i class="ri-alert-line text-lg opacity-80"></i>
              <span class="text-[10px] font-semibold bg-white/20 px-1.5 py-0.5 rounded-full">Rupture</span>
            </div>
            <div class="text-xl font-black">{{ outOfStockCount }}</div>
            <div class="text-[10px] opacity-90 mt-0.5">Produits en rupture</div>
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

        <!-- Stock Movement Chart -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-gray-900">Historique de vente 7 jours jusqu'à maintenant</h3>
            <div class="flex items-center space-x-2">
              <span class="text-xs text-gray-500">7 jours</span>
              <i class="ri-line-chart-line text-lg text-blue-600"></i>
            </div>
          </div>

          <div v-if="dailySalesData.length === 0" class="py-8 text-center">
            <i class="ri-line-chart-line text-4xl text-gray-300 mb-2"></i>
            <p class="text-sm text-gray-500">Aucune donnée disponible</p>
          </div>
          <div v-else class="space-y-2">
            <!-- Line Chart -->
            <div class="bg-gray-50 rounded-lg p-4">
              <svg viewBox="0 0 100 100" class="w-full h-48">
                <polyline
                  fill="none"
                  stroke="#3b82f6"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  :points="salesLinePoints"
                />
                <circle
                  v-for="(day, idx) in dailySalesData"
                  :key="idx"
                  :cx="dailySalesData.length > 1 ? (idx / (dailySalesData.length - 1)) * 100 : 0"
                  :cy="100 - day.height"
                  r="1.6"
                  fill="#3b82f6"
                />
              </svg>

              <div class="grid grid-cols-7 gap-1 mt-2">
                <div
                  v-for="(day, idx) in dailySalesData"
                  :key="`label-${idx}`"
                  class="text-center"
                >
                  <div class="text-[10px] text-gray-500">{{ day.label }}</div>
                </div>
              </div>
            </div>
            
            <!-- Summary -->
            <div class="pt-4 border-t border-gray-100 grid grid-cols-2 gap-4">
              <div>
                <div class="text-xs text-gray-500">Total ventes (7j)</div>
                <div class="text-sm font-bold text-blue-600">{{ formatPrice(totalWeeklySales) }} Ar</div>
              </div>
              <div class="text-right">
                <div class="text-xs text-gray-500">Commandes (7j)</div>
                <div class="text-sm font-bold text-blue-600">{{ totalWeeklyOrders }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bénéfices Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-gray-900">Bénéfices</h3>
            <i class="ri-money-dollar-circle-line text-lg text-green-600"></i>
          </div>
          
          <div class="grid grid-cols-1 gap-4">
            <!-- Total Revenue (Chiffre d'affaires) -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-blue-700 uppercase tracking-wider">Chiffre d'affaires</span>
                <i class="ri-line-chart-line text-blue-600"></i>
              </div>
              <div class="text-2xl font-black text-blue-700">{{ formatPrice(stats.totalOutValue) }} <span class="text-sm">Ar</span></div>
              <div class="text-[10px] text-blue-600 mt-1">Ventes totales ({{ selectedPeriod }})</div>
            </div>

            <!-- Cost of Goods Sold (Coût des marchandises vendues) -->
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-orange-700 uppercase tracking-wider">Coût des ventes</span>
                <i class="ri-shopping-cart-line text-orange-600"></i>
              </div>
              <div class="text-2xl font-black text-orange-700">{{ formatPrice(totalCostOfSales) }} <span class="text-sm">Ar</span></div>
              <div class="text-[10px] text-orange-600 mt-1">Coût d'achat des produits vendus</div>
            </div>

            <!-- Net Profit (Bénéfice net) -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border-2 border-green-200">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-green-700 uppercase tracking-wider">Bénéfice Net</span>
                <i class="ri-trophy-line text-green-600"></i>
              </div>
              <div class="text-3xl font-black text-green-700">{{ formatPrice(netProfit) }} <span class="text-sm">Ar</span></div>
              <div class="flex items-center justify-between mt-2">
                <div class="text-[10px] text-green-600">Marge bénéficiaire</div>
                <div class="text-sm font-bold text-green-700">{{ profitMargin }}%</div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>

    <BottomNav />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useUIStore } from '../stores/useUIStore';
import { useProductStore } from '../stores/useProductStore';
import { getStockStats, getStockEntries, getStockExits, getOrderLocalList } from '../services/api';
import BottomNav from '../components/BottomNav.vue';

const uiStore = useUIStore();
const productStore = useProductStore();

const loading = ref(false);
const selectedPeriod = ref('Aujourd\'hui');
const stockStats = ref(null);
const entriesList = ref([]);
const exitsList = ref([]);
const allProducts = ref([]);
const orderLocalList = ref([]);

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

const outOfStockCount = computed(() => {
  return allProducts.value.filter(p => parseInt(p.field_quantite_disponible || 0) <= 0).length;
});

// Daily sales history from order_local (last 7 days).
const dailySalesData = computed(() => {
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
      count: 0
    });
  }
  
  const sevenDaysAgo = new Date(today);
  sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
  sevenDaysAgo.setHours(0, 0, 0, 0);
  
  if (orderLocalList.value && orderLocalList.value.length > 0) {
    orderLocalList.value.forEach((order) => {
      // Prefer field_date (Y-m-d), fallback to created timestamp.
      const rawDate = order.field_date || order.created;
      let orderDate;
      if (/^\d+$/.test(String(rawDate))) {
        orderDate = new Date(Number(rawDate) * 1000);
      } else {
        orderDate = new Date(String(rawDate).includes('T') ? rawDate : `${rawDate}T00:00:00`);
      }
      if (isNaN(orderDate.getTime())) return;
      orderDate.setHours(0, 0, 0, 0);
      if (orderDate < sevenDaysAgo) return;

      const dateKey = orderDate.toISOString().split('T')[0];
      const dayData = last7Days.find((d) => d.date === dateKey);
      if (!dayData) return;

      const orderTotal = parseFloat(order.field_total || 0) || 0;
      dayData.amount += orderTotal;
      dayData.count += 1;
    });
  }
  
  const maxAmount = Math.max(
    ...last7Days.map(d => d.amount),
    1
  );
  
  return last7Days.map((day, index) => {
    const date = new Date(day.date);
    const label = date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    
    return {
      index,
      date: day.date,
      label,
      amount: day.amount,
      count: day.count,
      height: (day.amount / maxAmount) * 100
    };
  });
});

const salesLinePoints = computed(() => {
  const total = dailySalesData.value.length;
  return dailySalesData.value.map((day, index) => {
    const x = total > 1 ? (index / (total - 1)) * 100 : 0;
    const y = 100 - day.height;
    return `${x},${y}`;
  }).join(' ');
});

const totalWeeklySales = computed(() => {
  return dailySalesData.value.reduce((sum, day) => sum + day.amount, 0);
});

const totalWeeklyOrders = computed(() => {
  return dailySalesData.value.reduce((sum, day) => sum + day.count, 0);
});

// Profit calculations
const totalCostOfSales = computed(() => {
  // Calculate cost of goods sold based on actual purchase prices from entries
  let totalCost = 0;
  
  if (exitsList.value && exitsList.value.length > 0) {
    // Create a map of product purchase prices from entries (average cost method)
    const productCostMap = {};
    
    if (entriesList.value && entriesList.value.length > 0) {
      entriesList.value.forEach(entry => {
        const productId = entry.product?.nid || entry.product_nid;
        const purchasePrice = parseFloat(entry.field_prix_de_vente || 0);
        const quantity = parseFloat(entry.field_quantite || 0);
        
        if (productId && purchasePrice > 0 && quantity > 0) {
          if (!productCostMap[productId]) {
            productCostMap[productId] = { totalCost: 0, totalQty: 0 };
          }
          productCostMap[productId].totalCost += purchasePrice * quantity;
          productCostMap[productId].totalQty += quantity;
        }
      });
    }
    
    // Calculate cost for each exit using average purchase price
    // Only include products that have entry data (known purchase price)
    exitsList.value.forEach(exit => {
      const productId = exit.product?.nid || exit.product_nid;
      const quantity = parseFloat(exit.field_quantite || 0);
      
      // Only calculate cost if we have purchase price data from entries
      if (productId && productCostMap[productId]) {
        const purchasePrice = productCostMap[productId].totalCost / productCostMap[productId].totalQty;
        totalCost += purchasePrice * quantity;
      }
      // If no entry data, skip this product (don't include in profit calculation)
    });
  }
  
  return totalCost;
});

const netProfit = computed(() => {
  // Net profit = Revenue - Cost of sales
  return stats.value.totalOutValue - totalCostOfSales.value;
});

const profitMargin = computed(() => {
  // Profit margin percentage
  if (stats.value.totalOutValue === 0) return 0;
  return ((netProfit.value / stats.value.totalOutValue) * 100).toFixed(2);
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

    // Fetch order_local list for line chart (historique ventes).
    const orderParams = 'sort[val]=created&sort[op]=DESC&offset=200&pager=0';
    const orderResponse = await getOrderLocalList(orderParams);
    orderLocalList.value = orderResponse?.data?.rows || [];
    
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
