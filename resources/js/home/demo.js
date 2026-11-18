// Mini-telao da demonstracao ao vivo (hero da pagina inicial).
// Sem script inline (CSP): a configuracao vem de <script type="application/json" id="pp-demo">.
// Polling a cada 5 s em GET /demo/photos (so fotos aprovadas do evento de demonstracao) e
// carrossel das ultimas fotos. A logica pura fica exportada para tests/js/home-demo.test.js.

export const ROTATE_MS = 4000;

export function parseDemoConfig(raw) {
  let data = {};

  try {
    data = JSON.parse(raw) ?? {};
  } catch {
    data = {};
  }

  const pollMs = Number.parseInt(data.pollMs, 10);
  const max = Number.parseInt(data.max, 10);

  return {
    // so caminho relativo da mesma origem (CSP connect-src 'self')
    pollUrl: typeof data.pollUrl === 'string' && data.pollUrl.startsWith('/') && !data.pollUrl.startsWith('//') ? data.pollUrl : '',
    pollMs: Number.isFinite(pollMs) ? Math.min(60000, Math.max(2000, pollMs)) : 5000,
    max: Number.isFinite(max) ? Math.min(20, Math.max(1, max)) : 8,
    alt: typeof data.alt === 'string' ? data.alt : '',
  };
}

/**
 * Junta a resposta do servidor (mais novas primeiro) com o que ja esta na tela.
 * Fotos ja conhecidas mantem a URL (a assinatura muda a cada resposta; trocar o src recarregaria a imagem).
 *
 * @param {{uuid: string, url: string}[]} current
 * @param {{uuid: string, url: string}[]} incoming
 * @returns {{list: {uuid: string, url: string}[], added: string[], removed: string[]}}
 */
export function mergePhotos(current, incoming, max = 8) {
  const known = new Map(current.map((p) => [p.uuid, p]));
  const list = [];
  const seen = new Set();

  for (const p of incoming ?? []) {
    if (!p || typeof p.uuid !== 'string' || typeof p.url !== 'string' || seen.has(p.uuid)) continue;
    // so URLs relativas da propria origem
    if (!p.url.startsWith('/') || p.url.startsWith('//')) continue;
    seen.add(p.uuid);
    list.push(known.get(p.uuid) ?? { uuid: p.uuid, url: p.url, orientation: p.orientation ?? null });
    if (list.length >= max) break;
  }

  return {
    list,
    added: list.filter((p) => !known.has(p.uuid)).map((p) => p.uuid),
    removed: current.filter((p) => !seen.has(p.uuid)).map((p) => p.uuid),
  };
}

export function nextIndex(index, length) {
  return length <= 0 ? 0 : (index + 1) % length;
}

function boot() {
  const block = document.getElementById('pp-demo');
  const stage = document.getElementById('demo-stage');
  const emptyText = document.getElementById('demo-empty-text');
  const backdrop = document.getElementById('demo-screen');

  if (!block || !stage) return;

  const cfg = parseDemoConfig(block.textContent);

  if (!cfg.pollUrl) return;

  let photos = [];
  let index = 0;
  let rotateTimer = 0;
  let pollTimer = 0;
  let failures = 0;
  let busy = false;
  const imgs = new Map(); // uuid -> <img>

  function show() {
    photos.forEach((p, i) => imgs.get(p.uuid)?.classList.toggle('is-active', i === index));
  }

  function render({ list, added, removed }) {
    for (const uuid of removed) {
      imgs.get(uuid)?.remove();
      imgs.delete(uuid);
    }

    for (const p of list) {
      if (imgs.has(p.uuid)) continue;
      const img = document.createElement('img');
      img.className = 'hm-demo__photo';
      img.alt = cfg.alt;
      img.decoding = 'async';
      img.src = p.url;
      stage.append(img);
      imgs.set(p.uuid, img);
    }

    photos = list;
    backdrop?.classList.toggle('has-photos', photos.length > 0);
    if (emptyText) emptyText.hidden = photos.length > 0;

    // foto nova: mostra a mais recente na hora
    if (added.length > 0) index = 0;
    if (index >= photos.length) index = 0;
    show();
  }

  function rotate() {
    if (document.hidden || photos.length < 2) return;
    index = nextIndex(index, photos.length);
    show();
  }

  async function poll() {
    if (busy) return;
    busy = true;

    try {
      const res = await fetch(cfg.pollUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });

      if (res.status === 404) { busy = false; return; } // demonstracao desligada: para de consultar
      if (!res.ok) throw new Error(`poll ${res.status}`);

      const data = await res.json();
      render(mergePhotos(photos, data.photos, cfg.max));
      failures = 0;
    } catch {
      failures += 1;
    }

    busy = false;

    // falhas seguidas: espera mais (ate 30 s)
    pollTimer = window.setTimeout(poll, Math.min(30000, cfg.pollMs * (1 + failures)));
  }

  rotateTimer = window.setInterval(rotate, ROTATE_MS);
  poll();

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      window.clearTimeout(pollTimer);
      poll();
    }
  });

  window.addEventListener('pagehide', () => {
    window.clearInterval(rotateTimer);
    window.clearTimeout(pollTimer);
  });
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
}
