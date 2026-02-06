<template>
  <div>
    <h1>Users</h1>
    <div v-if="loading">Loading...</div>
    <div v-else-if="error">❌ {{ error }}</div>
    <ul v-else>
      <li v-for="user in users.rows" :key="user.uid">
        {{ user.name }}
      </li>
    </ul>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { getListUser } from '../../services/api.js'


export default {
  name: 'UserList',
    computed: {
    transId() {
      // Access the route parameter (e.g., id)
      return this.$route.params.id;
    }
  },
  setup() {
    const users = ref([]);         // Holds the fetched data
    const loading = ref(true)
    const error = ref(null)

    onMounted(async () => {
      try {
        const parameter = "" ;
        const res = await getListUser(parameter);
        users.value = res.data // Drupal JSON:API structure
      } catch (err) {
        error.value = err.message
      } finally {
        loading.value = false
      }
    })
    return {
      users,
      loading,
      error
    };
  }
};
</script>