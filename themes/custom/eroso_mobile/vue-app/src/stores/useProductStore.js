import { defineStore } from 'pinia';
import { getLists, getCategories, saveItem, uploadFile, getDetail } from '../services/api';
import { proxyImage } from '../services/image';

export const useProductStore = defineStore('product', {
    state: () => ({
        products: [],
        categories: [],
        loading: false,
        error: null,
        currentPage: 0,
        hasMore: true,
        itemsPerPage: 12
    }),
    actions: {
        async fetchProducts(append = false, filters = {}) {
            if (this.loading) return;

            if (!append) {
                this.currentPage = 0;
                this.hasMore = true;
            }

            if (!this.hasMore) return;

            this.loading = true;
            try {
                let params = `sort[val]=created&sort[op]=DESC&offset=${this.itemsPerPage}&pager=${this.currentPage}`;

                if (filters.search) {
                    if (filters.searchType === 'sku') {
                        params += `&filters[field_sku][val]=${encodeURIComponent(filters.search)}&filters[field_sku][op]=CONTAINS`;
                    } else {
                        params += `&filters[title][val]=${encodeURIComponent(filters.search)}&filters[title][op]=CONTAINS`;
                    }
                }

                if (filters.category) {
                    params += `&filters[field_category][val]=${filters.category}`;
                }

                const response = await getLists('node', 'product', params);
                const newProducts = response.data.rows || response.data || [];

                if (append) {
                    this.products = [...this.products, ...newProducts];
                } else {
                    this.products = newProducts;
                }

                if (newProducts.length < this.itemsPerPage) {
                    this.hasMore = false;
                } else {
                    this.currentPage++;
                }
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
                    field_quantite_disponible: 0,
                    field_prix_unitaire: "",
                    field_description: productData.description || "",
                    field_media_image: productData.media_id || "",
                    field_tags: []
                };

                const response = await saveItem(payload);
                if (response.data.status === true || response.data.item) {
                    const nid = response.data.item || response.data.id;

                    // Automatically record initial stock movement
                    await this.recordStockMovement({
                        product_nid: nid,
                        product_title: productData.name,
                        type: 'in',
                        quantity: 1,
                        unit_price: 0,
                        sale_price: productData.price,
                        reason: 'Initialisation stock (Nouveau produit)',
                        date: new Date().toISOString().split('T')[0]
                    });

                    // Update local state
                    const newProd = {
                        ...productData,
                        nid: nid,
                        field_quantite_disponible: 1,
                        field_prix_vente: productData.price,
                        image: productData.image || proxyImage("https://readdy.ai/api/search-image?query=icon%2C%20generic%20product", { w: 48, h: 48, fit: 'cover' })
                    };
                    this.products.unshift(newProd);
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
                // Determine if it's likely a SKU (uppercase letters and numbers)
                const isSku = /^[A-Z0-9-]+$/.test(query);
                let params = `filters[title][val]=${encodeURIComponent(query)}&filters[title][op]=CONTAINS&offset=5`;

                if (isSku) {
                    // If it looks like a SKU, we can try searching SKU first or also
                    // For now, let's just use CONTAINS on title as it often includes SKU in this app
                    // OR we could check if SKU field exists in the results
                }

                const response = await getLists('node', 'product', params);
                let results = response.data.rows || [];

                // Secondary check for SKU if no results from title search (or if results are small)
                if (results.length < 3) {
                    const skuParams = `filters[field_sku][val]=${encodeURIComponent(query)}&filters[field_sku][op]=CONTAINS&offset=5`;
                    const skuResponse = await getLists('node', 'product', skuParams);
                    const skuResults = skuResponse.data.rows || [];

                    // Merge and deduplicate
                    const seen = new Set(results.map(r => r.nid));
                    skuResults.forEach(r => {
                        if (!seen.has(r.nid)) {
                            results.push(r);
                            seen.add(r.nid);
                        }
                    });
                }

                return results;
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
        },
        async fetchProductMovements(nid) {
            try {
                const params = `filters[field_product_id][val]=${nid}&filters[field_product_id][op]==&sort[val]=created&sort[op]=DESC`;
                const response = await getLists('node', 'stock', params);
                return response.data.rows || response.data || [];
            } catch (err) {
                console.error("Error fetching product movements:", err);
                return [];
            }
        },
        getProductById(nid) {
            return this.products.find(p => p.nid == nid);
        },
        async fetchProductDetail(nid) {
            this.loading = true;
            try {
                const response = await getDetail('node', 'product', nid);
                return response.data;
            } catch (err) {
                console.error("Error fetching product detail:", err);
                return null;
            } finally {
                this.loading = false;
            }
        },
        async updateProduct(nid, productData) {
            this.loading = true;
            try {
                const payload = {
                    entity_type: 'node',
                    bundle: 'product',
                    nid: nid,
                    token: localStorage.getItem('token') || '',
                    author: localStorage.getItem('username') || '',
                    title: productData.name || productData.title,
                    field_sku: productData.ref || productData.field_sku,
                    field_category: productData.category || productData.field_category,
                    field_prix_vente: productData.price || productData.field_prix_vente,
                    field_description: productData.description || productData.field_description || "",
                    field_tags: []
                };

                // Only include media_image if there's a new image
                if (productData.media_id) {
                    payload.field_media_image = productData.media_id;
                }

                const response = await saveItem(payload);
                if (response.data.status === true || response.data.item) {
                    // Refresh products list to reflect changes
                    await this.fetchProducts();
                    return { success: true };
                } else {
                    return { success: false, message: response.data.message || "Erreur lors de la mise à jour" };
                }
            } catch (err) {
                console.error("Update error:", err);
                return { success: false, message: "Une erreur est survenue lors de la mise à jour." };
            } finally {
                this.loading = false;
            }
        }
    }
});
