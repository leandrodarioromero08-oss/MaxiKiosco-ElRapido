/* sw.js — SERVICE WORKER DE EL RÁPIDO (modo app)
 * Qué hace:
 *  - Hace que el sitio se pueda instalar como app (junto con manifest.webmanifest).
 *  - Si no hay internet al abrir/navegar una página, muestra offline.html en vez del error del navegador.
 *  - Guarda imágenes (fotos de productos, logos) para que carguen rápido y se vean aunque la señal sea mala.
 *  - CSS/JS con versión en la URL (?v=<fecha> o paquete _b-xxxx.css, ver includes/assets.php): se sirven desde lo
 *    guardado, al instante y sin red. Si editás el archivo, la URL cambia sola y se baja la versión nueva.
 *  - CSS/JS/fuentes sin versión: se piden primero a la red (los cambios se ven al instante);
 *    la copia guardada se usa solo si no hay conexión.
 * Qué NO hace, a propósito:
 *  - No guarda páginas HTML (tienen sesión, carrito y tokens de seguridad de cada persona).
 *  - No toca el panel admin, ni ningún POST, ni las consultas *_api.php (carrito, avisos...).
 * Para forzar que todos los dispositivos renueven lo guardado, subí VERSION.
 */
const VERSION = 'v3';
const CACHE_ESTATICO = 'elrapido-estatico-' + VERSION;
const CACHE_IMAGENES = 'elrapido-imagenes-' + VERSION;
const MAX_IMAGENES = 80;
const BASE = new URL('./', self.location).pathname;          // "/el-rapido/" en Laragon, "/" en un dominio propio
const OFFLINE = new URL('offline.html', self.location).href;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_ESTATICO)
      .then((c) => c.add(new Request(OFFLINE, { cache: 'reload' })))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((claves) => Promise.all(claves
        .filter((k) => k.startsWith('elrapido-') && k !== CACHE_ESTATICO && k !== CACHE_IMAGENES)
        .map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET' || req.headers.has('range')) return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;                 // Google Fonts, mapas, etc.: los maneja el navegador
  if (url.pathname.startsWith(BASE + 'admin/')) return;            // el panel admin nunca pasa por acá
  if (url.pathname.startsWith(BASE + 'assets/intro/')) return;     // la intro (≈2 MB) la cachea el navegador; no debe desplazar las fotos de productos

  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE, { cacheName: CACHE_ESTATICO })));
    return;
  }
  if (req.destination === 'image') {
    event.respondWith(imagen(event));
    return;
  }
  if (['style', 'script', 'font'].includes(req.destination) && url.pathname.startsWith(BASE + 'assets/')) {
    const versionado = url.searchParams.has('v') || /\/_b-[0-9a-f]+-[0-9a-f]+\.css$/.test(url.pathname);
    event.respondWith(versionado ? cachePrimero(req, url) : redPrimero(req));
  }
});

// Recurso versionado: lo guardado es SIEMPRE el correcto para esa URL, así que no hace falta preguntarle a la red.
async function cachePrimero(req, url) {
  const cache = await caches.open(CACHE_ESTATICO);
  const guardada = await cache.match(req);
  if (guardada) return guardada;
  try {
    const resp = await fetch(req);
    if (resp.ok) {
      // se descartan las versiones viejas del mismo archivo (mismo camino, otro ?v=)
      const claves = await cache.keys();
      await Promise.all(claves
        .filter((k) => { const u = new URL(k.url); return u.pathname === url.pathname && u.search !== url.search; })
        .map((k) => cache.delete(k)));
      await cache.put(req, resp.clone());
    }
    return resp;
  } catch (e) {
    return Response.error();
  }
}

async function redPrimero(req) {
  const cache = await caches.open(CACHE_ESTATICO);
  try {
    const resp = await fetch(req);
    if (resp.ok) cache.put(req, resp.clone());
    return resp;
  } catch (e) {
    const guardada = await cache.match(req);
    return guardada || Response.error();
  }
}

async function imagen(event) {
  const req = event.request;
  const cache = await caches.open(CACHE_IMAGENES);
  const guardada = await cache.match(req);
  const actualizar = fetch(req)
    .then(async (resp) => {
      if (resp.ok) { await cache.put(req, resp.clone()); await recortar(cache); }
      return resp;
    })
    .catch(() => null);
  if (guardada) { event.waitUntil(actualizar); return guardada; }   // se ve al instante y se renueva por detrás
  return (await actualizar) || Response.error();
}

async function recortar(cache) {
  const claves = await cache.keys();
  if (claves.length > MAX_IMAGENES) {
    await Promise.all(claves.slice(0, claves.length - MAX_IMAGENES).map((k) => cache.delete(k)));
  }
}
