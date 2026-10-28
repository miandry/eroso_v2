const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const MAX_UPLOAD_BYTES = 8 * 1024 * 1024;

/**
 * Compresse et redimensionne une photo avant envoi à l'API de recherche IA.
 *
 * @returns {Promise<{ file: File, previewUrl: string, compressed: boolean }>}
 */
export async function prepareImageForSearch(file, options = {}) {
  const maxDim = options.maxDim ?? 1280;
  const maxBytes = options.maxBytes ?? 700000;
  const jpegQuality = options.jpegQuality ?? 0.82;

  if (!file || !file.type?.startsWith('image/')) {
    throw new Error('Veuillez choisir une image (JPG, PNG, WebP).');
  }
  if (!ACCEPTED_TYPES.includes(file.type)) {
    throw new Error('Format non supporté. Utilisez JPG, PNG ou WebP.');
  }
  if (file.size > MAX_UPLOAD_BYTES) {
    throw new Error('Image trop volumineuse (max 8 Mo).');
  }

  const previewUrl = await readFileAsDataUrl(file);

  if (file.type === 'image/jpeg' && file.size <= maxBytes) {
    const dims = await loadImageDimensions(previewUrl);
    if (Math.max(dims.width, dims.height) <= maxDim) {
      return { file, previewUrl, compressed: false };
    }
  }

  const compressed = await compressWithCanvas(file, previewUrl, maxDim, maxBytes, jpegQuality);
  return { ...compressed, compressed: true };
}

function readFileAsDataUrl(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ''));
    reader.onerror = () => reject(new Error('Impossible de lire l’image.'));
    reader.readAsDataURL(file);
  });
}

function loadImageDimensions(src) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve({ width: img.naturalWidth, height: img.naturalHeight });
    img.onerror = () => reject(new Error('Image illisible.'));
    img.src = src;
  });
}

async function compressWithCanvas(file, dataUrl, maxDim, maxBytes, jpegQuality) {
  const img = await loadImageElement(dataUrl);
  let { width, height } = img;
  const longest = Math.max(width, height);
  if (longest > maxDim) {
    const ratio = maxDim / longest;
    width = Math.max(1, Math.round(width * ratio));
    height = Math.max(1, Math.round(height * ratio));
  }

  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');
  if (!ctx) {
    return { file, previewUrl: dataUrl };
  }

  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, width, height);
  ctx.drawImage(img, 0, 0, width, height);

  let quality = jpegQuality;
  let blob = await canvasToBlob(canvas, quality);
  while (blob.size > maxBytes && quality > 0.55) {
    quality -= 0.07;
    blob = await canvasToBlob(canvas, quality);
  }

  const outFile = new File(
    [blob],
    (file.name || 'photo').replace(/\.[^.]+$/, '') + '-search.jpg',
    { type: 'image/jpeg', lastModified: Date.now() },
  );
  const previewUrl = URL.createObjectURL(blob);
  return { file: outFile, previewUrl };
}

function loadImageElement(src) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error('Image illisible.'));
    img.src = src;
  });
}

function canvasToBlob(canvas, quality) {
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => (blob ? resolve(blob) : reject(new Error('Compression échouée.'))),
      'image/jpeg',
      quality,
    );
  });
}
