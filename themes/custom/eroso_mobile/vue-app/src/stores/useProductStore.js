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
                let params = `sort[val]=nid&sort[op]=DESC&offset=${this.itemsPerPage}&pager=${this.currentPage}`;

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

                const bundle = filters.bundle || 'product';
                const response = await getLists('node', bundle, params);
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
        async createProduct(productData, bundle = 'product') {
            this.loading = true;
            try {
                const normalizedTitle = (productData.name || '').toString().trim();
                if (!normalizedTitle) {
                    return { success: false, message: "Le nom du produit est requis." };
                }

                // Strong duplicate check against database before create.
                // Use title CONTAINS then compare exact (case-insensitive) client-side.
                const duplicateParams = `filters[title][val]=${encodeURIComponent(normalizedTitle)}&filters[title][op]=CONTAINS&offset=20`;
                const duplicateResponse = await getLists('node', bundle, duplicateParams);
                const duplicateRows = duplicateResponse?.data?.rows || [];
                const duplicate = duplicateRows.find((p) => {
                    const title = (p?.title || '').toString().trim().toLowerCase();
                    return title === normalizedTitle.toLowerCase();
                });

                if (duplicate) {
                    return { success: false, message: `Le produit "${normalizedTitle}" existe déjà.` };
                }

                const payload = {
                    entity_type: 'node',
                    bundle: bundle,
                    token: localStorage.getItem('token') || '',
                    author: localStorage.getItem('username') || '',
                    title: normalizedTitle,
                    field_sku: productData.ref,
                    field_category: productData.category,
                    field_prix_vente: productData.price,
                    field_prix_unitaire: "",
                    field_description: productData.description || "",
                    field_media_image: productData.media_id || "",
                    field_tags: []
                };
                // Boutique product only: stock field exists on the type.
                // NOTE: Keep the node's field_quantite_disponible at 0 on create.
                // The stock-movement hook (mz_eroso_v2_node_insert) will increment it
                // when the initial "in" movement is recorded below. Otherwise the
                // quantity would be counted twice (e.g. 56 becomes 112).
                const initialStock = Number(productData.stock);
                const normalizedStock = Number.isFinite(initialStock) && initialStock >= 0 ? initialStock : 1;
                if (bundle === 'product') {
                    payload.field_quantite_disponible = 0;
                }
                if (bundle === 'product_commande' && productData.taobao_url) {
                    const raw = String(productData.taobao_url).trim();
                    if (raw) {
                        const uri = /^https?:\/\//i.test(raw) ? raw : `https://${raw}`;
                        payload.field_taobao_url = { uri, title: '' };
                    }
                }

                const response = await saveItem(payload);
                if (response.data.status === true || response.data.item) {
                    const nid = response.data.item || response.data.id;

                    if (bundle === 'product' && normalizedStock > 0) {
                        const unitPurchasePrice = Number(productData.purchase_price) || 0;
                        await this.recordStockMovement({
                            product_nid: nid,
                            product_title: normalizedTitle,
                            type: 'in',
                            quantity: normalizedStock,
                            unit_price: unitPurchasePrice,
                            sale_price: productData.price,
                            reason: 'Initialisation stock (Nouveau produit)',
                            date: new Date().toISOString().split('T')[0]
                        });
                    }

                    const newProd = {
                        ...productData,
                        nid: nid,
                        field_prix_vente: productData.price,
                        image: productData.image || proxyImage("https://readdy.ai/api/search-image?query=icon%2C%20generic%20product", { w: 48, h: 48, fit: 'cover' })
                    };
                    if (bundle === 'product') {
                        // Reflects the value after the stock-movement hook ran.
                        newProd.field_quantite_disponible = normalizedStock;
                    }
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
        async searchProducts(query, bundle = 'product') {
            if (!query || query.length < 2) return [];
            try {
                // Search by BOTH title and SKU, then merge/dedupe.
                const titleParams = `filters[title][val]=${encodeURIComponent(query)}&filters[title][op]=CONTAINS&offset=5`;
                const skuParams = `filters[field_sku][val]=${encodeURIComponent(query)}&filters[field_sku][op]=CONTAINS&offset=5`;

                const [titleResponse, skuResponse] = await Promise.all([
                    getLists('node', bundle, titleParams),
                    getLists('node', bundle, skuParams),
                ]);

                const titleResults = titleResponse?.data?.rows || [];
                const skuResults = skuResponse?.data?.rows || [];

                const merged = [];
                const seen = new Set();

                // Prefer title matches first, then SKU matches.
                [...titleResults, ...skuResults].forEach((r) => {
                    const key = r?.nid ?? r?.id;
                    if (key === undefined || key === null) return;
                    const k = String(key);
                    if (seen.has(k)) return;
                    seen.add(k);
                    merged.push(r);
                });

                return merged;
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
        async fetchProductDetail(nid, bundle = 'product') {
            this.loading = true;
            try {
                const response = await getDetail('node', bundle, nid);
                return response.data;
            } catch (err) {
                console.error("Error fetching product detail:", err);
                return null;
            } finally {
                this.loading = false;
            }
        },
        async updateProduct(nid, productData, bundle = 'product') {
            this.loading = true;
            try {
                const payload = {
                    entity_type: 'node',
                    bundle: bundle,
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

                if (bundle === 'product_commande') {
                    const raw = String(productData.taobao_url ?? productData.field_taobao_url ?? '').trim();
                    if (raw) {
                        const uri = /^https?:\/\//i.test(raw) ? raw : `https://${raw}`;
                        payload.field_taobao_url = { uri, title: '' };
                    }
                }

                const response = await saveItem(payload);
                if (response.data.status === true || response.data.item) {
                    await this.fetchProducts(false, { bundle });
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
