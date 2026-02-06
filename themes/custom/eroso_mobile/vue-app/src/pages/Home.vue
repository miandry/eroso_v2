<template>
  <div>
    <h2>📰 Articles injectés depuis Drupal</h2>
    <ul>
      <li v-for="a in articles" :key="a.id">
        {{ a.title }} <small>({{ a.created }})</small>
      </li>
    </ul>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';

export default {
  name: 'Home',
    computed: {
    transId() {
      // Access the route parameter (e.g., id)
      return this.$route.params.id;
    }
  },
  setup() {
    const articles = ref([]);         // Holds the fetched data
    // Fetch data when the component is mounted
    onMounted(() => {
         articles.value = (window.drupalSettings?.vueArticles || []);
         console.log("ok okoko");
    });

    return {
      articles
    };
  }
};
</script>