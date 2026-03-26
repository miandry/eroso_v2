# PWA Setup Guide - Eroso Stock Manager

## ✅ What's Been Configured

Your Vue app now has Progressive Web App (PWA) capabilities with offline support!

### Files Modified/Created:
1. ✅ `package.json` - Added `vite-plugin-pwa` dependency
2. ✅ `vite.config.js` - Configured PWA plugin with service worker
3. ✅ `index.html` - Added PWA meta tags (Vue dev)
4. ✅ `templates/html.html.twig` - Added PWA meta tags (Drupal production)

## 🔴 IMPORTANT: Drupal Integration

Since your Vue app is integrated with Drupal, the **`html.html.twig`** template is what gets rendered in production, not `index.html`. The PWA configuration has been added to both files:

- **Development**: `vue-app/index.html` (for local Vue dev server)
- **Production**: `themes/custom/eroso_mobile/templates/html.html.twig` (for Drupal)

## 📦 Installation Steps

### 1. Install Dependencies
Run this command in the `vue-app` directory:

```bash
npm install
```

This will install the `vite-plugin-pwa` package.

### 2. Create PWA Icons

You need to create the following icon files in the `public` folder:

**Required Icons:**
- `public/pwa-192x192.png` (192x192 pixels)
- `public/pwa-512x512.png` (512x512 pixels)
- `public/apple-touch-icon.png` (180x180 pixels)
- `public/favicon.ico` (32x32 pixels)

**Quick Way to Generate Icons:**

Option A: Use an online tool like [PWA Asset Generator](https://www.pwabuilder.com/imageGenerator)
- Upload your logo (minimum 512x512px)
- Download all generated icons
- Place them in the `public` folder

Option B: Use a simple square logo and resize it:
- Create a 512x512px PNG with your logo
- Use an image editor or online tool to resize to other dimensions

### 3. Build the App

```bash
npm run build
```

This will generate the service worker and manifest files automatically in the `../dist` folder.

**Important for Drupal**: The build outputs to `themes/custom/eroso_mobile/dist/` which is where Drupal will serve the files from. The `html.html.twig` template references:
- `/themes/custom/eroso_mobile/dist/manifest.webmanifest`
- `/themes/custom/eroso_mobile/dist/sw.js` (service worker)
- `/themes/custom/eroso_mobile/dist/apple-touch-icon.png`

### 4. Test PWA Locally

```bash
npm run preview
```

Then open your browser and:
1. Open DevTools (F12)
2. Go to "Application" tab
3. Check "Service Workers" - you should see it registered
4. Check "Manifest" - you should see your app details

## 🚀 Features Enabled

### Offline Support
- **Static Assets**: All JS, CSS, HTML files are cached
- **API Caching**: API responses cached for 5 minutes (NetworkFirst strategy)
- **External Resources**: Google Fonts and CDN resources cached

### Caching Strategies

1. **CacheFirst** (Fonts & CDN):
   - Checks cache first, then network
   - Great for static resources that don't change

2. **NetworkFirst** (API calls):
   - Tries network first with 10s timeout
   - Falls back to cache if offline
   - Keeps data fresh when online

### Install Prompt
Users can install your app on:
- **Mobile**: Add to Home Screen
- **Desktop**: Install button in browser address bar

## 📱 Testing on Mobile

### Android (Chrome):
1. Open your app URL
2. Tap menu (⋮) → "Install app" or "Add to Home Screen"
3. App will open in standalone mode

### iOS (Safari):
1. Open your app URL
2. Tap Share button
3. Tap "Add to Home Screen"
4. App will open in standalone mode

## 🔧 Configuration Details

### Manifest Settings (vite.config.js)
```javascript
{
  name: 'Eroso Stock Manager',
  short_name: 'Eroso',
  theme_color: '#2563eb',  // Blue theme
  background_color: '#ffffff',
  display: 'standalone',    // Full-screen app
  orientation: 'portrait'   // Mobile-first
}
```

### Service Worker Caching
- **Static files**: Cached permanently, updated on new version
- **API responses**: Cached for 5 minutes
- **Fonts**: Cached for 1 year
- **CDN resources**: Cached for 30 days

## 🛠️ Customization

### Change Theme Color
Edit `vite.config.js`:
```javascript
theme_color: '#YOUR_COLOR'
```

### Adjust Cache Duration
Edit the `workbox.runtimeCaching` section in `vite.config.js`:
```javascript
maxAgeSeconds: 60 * 5  // 5 minutes
```

### Add More Cached Routes
Add new patterns to `runtimeCaching`:
```javascript
{
  urlPattern: /\/your-route\/.*/i,
  handler: 'NetworkFirst',
  options: { ... }
}
```

## 🐛 Troubleshooting

### Service Worker Not Registering
1. Make sure you're using HTTPS (or localhost)
2. Clear browser cache and reload
3. Check browser console for errors

### Icons Not Showing
1. Verify icon files exist in `public` folder
2. Check file names match exactly (case-sensitive)
3. Clear cache and rebuild

### Offline Mode Not Working
1. Open DevTools → Application → Service Workers
2. Click "Update" to force refresh
3. Check "Offline" checkbox to test

### Cache Not Updating
1. Increment version in `package.json`
2. Rebuild the app
3. Service worker will auto-update

## 📊 Monitoring

### Check Service Worker Status
```javascript
// In browser console
navigator.serviceWorker.getRegistrations().then(registrations => {
  console.log('Active service workers:', registrations);
});
```

### Check Cache Contents
```javascript
// In browser console
caches.keys().then(keys => {
  console.log('Cache names:', keys);
});
```

## 🎯 Next Steps

1. ✅ Install dependencies: `npm install`
2. ✅ Create PWA icons (see step 2 above)
3. ✅ Build the app: `npm run build`
4. ✅ Test locally: `npm run preview`
5. ✅ Deploy to production
6. ✅ Test on mobile devices

## 📚 Resources

- [Vite PWA Plugin Docs](https://vite-pwa-org.netlify.app/)
- [Workbox Strategies](https://developer.chrome.com/docs/workbox/modules/workbox-strategies/)
- [PWA Builder](https://www.pwabuilder.com/)
- [Web.dev PWA Guide](https://web.dev/progressive-web-apps/)

---

**Note**: The PWA will work best when deployed to a production server with HTTPS. For local development, it works on `localhost` but may have limitations.
