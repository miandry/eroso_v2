const isLocal = typeof window !== 'undefined' &&
    (window.location.hostname === 'localhost' ||
        window.location.hostname.endsWith('.local') ||
        window.location.hostname.includes('127.0.0.1'));

const BASE_URL_LOCAL = 'http://eroso.local:8888';
const BASE_URL_ONLINE = 'https://eroso-madagascar.com';

const BASE_URL = isLocal ? BASE_URL_LOCAL : BASE_URL_ONLINE;

/**
 * Proxies an image URL through images.weserv.nl
 * @param {string} url - The original image URL
 * @param {object} options - Transformation options (w, h, fit, etc.)
 * @returns {string} - The proxied URL
 */
export const proxyImage = (url, options = {}) => {
    if (!url) return url;

    // Resolve relative URLs
    let fullUrl = url;
    if (url.startsWith('/')) {
        fullUrl = `${BASE_URL}${url}`;
    }

    // If it's already a proxied URL, return it
    if (fullUrl.includes('images.weserv.nl')) return fullUrl;

    // images.weserv.nl cannot access local URLs (localhost, .local, etc.)
    // If we are on a local environment and the URL is local, we return it directly.
    if (isLocal && (fullUrl.includes('localhost') || fullUrl.includes('.local') || fullUrl.includes('eroso.local'))) {
        return fullUrl;
    }

    const baseUrl = 'https://images.weserv.nl/';
    const params = new URLSearchParams({
        url: fullUrl,
        ...options
    });

    return `${baseUrl}?${params.toString()}`;
};
