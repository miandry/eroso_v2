import { defineStore } from 'pinia';
import { getLists, getCategories, saveItem, uploadFile } from '../services/api';

export const useProductStore = defineStore('product', {
    state: () => ({
        products: [],
        categories: [],
        loading: false,
        error: null,
    }),
    actions: {
        async fetchProducts() {
            this.loading = true;
            try {

                const response = await getLists('node', 'product', 'sort[val]=created&sort[op]=DESC');
                this.products = response.data.rows || response.data;
            } catch (err) {
                this.error = "Erreur lors de la récupération des produits.";
                console.error(err);
            } finally {
                this.loading = false;
            }
        },
        async fetchCategories() {
            try {
                const response = await getCategories();
                this.categories = response.data.rows || response.data;
            } catch (err) {
                console.error("Error fetching categories:", err);
            }
        },
        async createProduct(productData) {
            this.loading = true;
            try {
                const payload = {
                    entity_type: 'node',
                    bundle: 'product',
                    token: localStorage.getItem('token') || '',
                    author: localStorage.getItem('username') || '',
                    title: productData.name,
                    field_sku: productData.ref,
                    field_category: productData.category,
                    field_prix_vente: productData.price,
                    field_description: productData.description || "",
                    field_media_image: productData.media_id || "",
                    field_tags: []
                };

                const response = await saveItem(payload);
                if (response.data.status === true || response.data.item) {
                    // Update local state
                    const newProd = {
                        ...productData,
                        quantity: 0,
                        price: productData.price,
                        image: productData.image || "https://readdy.ai/api/search-image?query=icon%2C%20generic%20product&width=48&height=48"
                    };
                    this.products.push(newProd);
                    return { success: true, product: newProd };
                } else {
                    return { success: false, message: response.data.message || "Erreur inconnue" };
                }
            } catch (err) {
                console.error("API Error:", err);
                return { success: false, message: "Une erreur est survenue lors de la communication avec l'API." };
            } finally {
                this.loading = false;
            }
        },
        async uploadImage(file) {
            try {
                const response = await uploadFile(file);
                if (response.data && response.data.status === true) {
                    return { success: true, id: response.data.id };
                }
                return { success: false };
            } catch (err) {
                console.error("Upload error:", err);
                return { success: false };
            }
        },
        async searchProducts(query) {
            if (!query || query.length < 2) return [];
            try {
                const params = `filters[title][val]=${encodeURIComponent(query)}&filters[title][op]=CONTAINS&offset=5`;
                const response = await getLists('node', 'product', params);
                return response.data.rows || [];
            } catch (err) {
                console.error("Search error:", err);
                return [];
            }
        },
        async recordStockMovement(movementData) {
            try {
                const payload = {
                    entity_type: 'node',
                    bundle: 'stock',
                    token: localStorage.getItem('token') || '',
                    author: localStorage.getItem('username') || '',
                    title: `${movementData.type === 'in' ? 'Entrée' : 'Sortie'} - ${movementData.product_title}`,
                    field_date_entree: movementData.date,
                    field_description: movementData.description || "",
                    field_price: movementData.unit_price || 0,
                    field_prix_de_vente: movementData.sale_price || 0,
                    field_product_id: movementData.product_nid,
                    field_quantite: movementData.quantity,
                    field_raison: movementData.reason || "",
                    field_total_price: (movementData.unit_price || 0) * (movementData.quantity || 0),
                    field_type: movementData.type
                };

                const response = await saveItem(payload);
                return { success: response.data.status === true || response.data.item };
            } catch (err) {
                console.error("Stock tracking error:", err);
                return { success: false };
            }
        },
        async fetchRecentMovements(limit = 10) {
            try {
                const params = `sort[val]=created&sort[op]=DESC&offset=${limit}`;
                const response = await getLists('node', 'stock', params);
                return response.data.rows || response.data || [];
            } catch (err) {
                console.error("Error fetching movements:", err);
                return [];
            }
        }
    }
});
