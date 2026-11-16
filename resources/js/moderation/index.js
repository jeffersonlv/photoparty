// Moderação ao vivo: abas, grade de miniaturas, seleção múltipla, ações individuais/em lote
// e atualização automática (polling de 5 s). Sem HTML cru: tudo via createElement/textContent.

const ACTIONS_BY_TAB = {
  pending: [
    ['approve', 'approve', 'bg-emerald-600 hover:bg-emerald-700'],
    ['reject', 'reject', 'bg-red-600 hover:bg-red-700'],
  ],
  approved: [['reject', 'remove', 'bg-red-600 hover:bg-red-700']],
  rejected: [['approve', 'restore', 'bg-emerald-600 hover:bg-emerald-700']],
};

function readJson(id) {
  try {
    return JSON.parse(document.getElementById(id)?.textContent ?? '{}') ?? {};
  } catch {
    return {};
  }
}

function boot() {
  const root = document.querySelector('[data-mod-root]');
  if (!root) return;

  const cfg = readJson('pp-moderation');
  const t = readJson('pp-moderation-i18n');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

  const grid = document.getElementById('mod-grid');
  const emptyEl = document.getElementById('mod-empty');
  const moreEl = document.getElementById('mod-more');
  const errorEl = document.getElementById('mod-error');
  const selectedEl = root.querySelector('[data-selected]');
  const batchButtons = root.querySelectorAll('[data-batch]');
  const tabButtons = root.querySelectorAll('[data-tab]');

  let tab = cfg.defaultTab || 'pending';
  let busy = false;
  let timer = null;
  let current = []; // uuids na ordem exibida
  const selected = new Set();
  const cards = new Map(); // uuid -> <li>

  const label = (key) => t[key] ?? key;

  // ------------------------------------------------------------------ rede

  async function request(url, options = {}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...options,
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(options.headers ?? {}),
      },
    });

    if (!res.ok) throw new Error(`http ${res.status}`);

    return res.json();
  }

  async function refresh() {
    if (busy) return;
    busy = true;

    const asked = tab;

    try {
      const data = await request(`${cfg.listUrl}?status=${encodeURIComponent(asked)}`);
      errorEl.hidden = true;

      for (const [status, total] of Object.entries(data.counts ?? {})) {
        const badge = root.querySelector(`[data-count="${status}"]`);
        if (badge) badge.textContent = String(total);
      }

      if (asked === tab) render(data);
    } catch {
      errorEl.hidden = false;
    } finally {
      busy = false;
    }
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      await refresh();
      schedule();
    }, cfg.pollMs || 5000);
  }

  async function act(action, uuids) {
    if (uuids.length === 0) return;

    try {
      if (uuids.length === 1) {
        await request(`${cfg.photoBase}/${encodeURIComponent(uuids[0])}/${action}`, { method: 'POST', body: '{}' });
      } else {
        await request(cfg.batchUrl, { method: 'POST', body: JSON.stringify({ action, photos: uuids }) });
      }

      uuids.forEach((uuid) => selected.delete(uuid));
      errorEl.hidden = true;
    } catch {
      errorEl.hidden = false;
    }

    busy = false;
    await refresh();
  }

  // ---------------------------------------------------------------- render

  function button(text, classes, onClick) {
    const el = document.createElement('button');
    el.type = 'button';
    el.className = `rounded px-2 py-1 text-xs font-semibold text-white ${classes}`;
    el.textContent = text;
    el.addEventListener('click', onClick);
    return el;
  }

  function buildCard(photo) {
    const li = document.createElement('li');
    li.dataset.uuid = photo.uuid;
    li.className = 'overflow-hidden rounded-lg bg-slate-100 ring-2 ring-transparent';

    const pick = document.createElement('button');
    pick.type = 'button';
    pick.className = 'block aspect-[4/5] w-full bg-slate-200';
    pick.addEventListener('click', () => toggle(photo.uuid));

    const img = document.createElement('img');
    img.src = photo.thumb;
    img.alt = '';
    img.loading = 'lazy';
    img.className = 'h-full w-full object-cover';
    pick.appendChild(img);
    li.appendChild(pick);

    const bar = document.createElement('div');
    bar.className = 'flex flex-wrap items-center gap-1 p-2';
    li.appendChild(bar);

    li.bar = bar;
    return li;
  }

  function paintCard(li, photo) {
    const on = selected.has(photo.uuid);
    li.classList.toggle('ring-indigo-500', on);
    li.classList.toggle('ring-transparent', !on);

    const bar = li.bar;
    bar.replaceChildren();

    for (const [action, key, classes] of ACTIONS_BY_TAB[tab] ?? []) {
      bar.appendChild(button(label(key), classes, () => act(action, [photo.uuid])));
    }

    const view = document.createElement('a');
    view.href = photo.full;
    view.target = '_blank';
    view.rel = 'noopener noreferrer';
    view.className = 'ml-auto text-xs text-indigo-700 underline';
    view.textContent = label('view');
    bar.appendChild(view);

    if (photo.guest_name) {
      const name = document.createElement('span');
      name.className = 'w-full truncate text-xs text-slate-600';
      name.textContent = photo.guest_name;
      bar.appendChild(name);
    }
  }

  function render(data) {
    const photos = data.photos ?? [];
    const keep = new Set(photos.map((p) => p.uuid));

    for (const [uuid, li] of cards) {
      if (!keep.has(uuid)) {
        li.remove();
        cards.delete(uuid);
        selected.delete(uuid);
      }
    }

    photos.forEach((photo, i) => {
      let li = cards.get(photo.uuid);

      if (!li) {
        li = buildCard(photo);
        cards.set(photo.uuid, li);
      }

      paintCard(li, photo);

      if (grid.children[i] !== li) grid.insertBefore(li, grid.children[i] ?? null);
    });

    current = photos.map((p) => p.uuid);
    emptyEl.hidden = photos.length > 0;

    moreEl.hidden = !data.has_more;
    if (data.has_more) moreEl.textContent = label('more').replace(':limit', String(cfg.limit));

    updateToolbar();
  }

  function updateToolbar() {
    const n = selected.size;
    selectedEl.textContent = n > 0 ? label('selected').replace(':count', String(n)) : '';

    batchButtons.forEach((el) => {
      el.disabled = n === 0 || !(ACTIONS_BY_TAB[tab] ?? []).some(([action]) => action === el.dataset.batch);
      el.hidden = !(ACTIONS_BY_TAB[tab] ?? []).some(([action]) => action === el.dataset.batch);
      const [, key] = (ACTIONS_BY_TAB[tab] ?? []).find(([action]) => action === el.dataset.batch) ?? [];
      el.textContent = key ? label(key) : '';
    });

    for (const [uuid, li] of cards) {
      const on = selected.has(uuid);
      li.classList.toggle('ring-indigo-500', on);
      li.classList.toggle('ring-transparent', !on);
    }
  }

  function toggle(uuid) {
    if (selected.has(uuid)) selected.delete(uuid);
    else selected.add(uuid);
    updateToolbar();
  }

  function setTab(next) {
    tab = next;
    selected.clear();
    cards.forEach((li) => li.remove());
    cards.clear();
    current = [];

    tabButtons.forEach((el) => el.setAttribute('aria-selected', String(el.dataset.tab === tab)));
    updateToolbar();
    busy = false;
    refresh();
  }

  // ---------------------------------------------------------------- eventos

  tabButtons.forEach((el) => el.addEventListener('click', () => setTab(el.dataset.tab)));
  root.querySelector('[data-select-all]').addEventListener('click', () => {
    current.forEach((uuid) => selected.add(uuid));
    updateToolbar();
  });
  root.querySelector('[data-clear]').addEventListener('click', () => {
    selected.clear();
    updateToolbar();
  });
  batchButtons.forEach((el) => el.addEventListener('click', () => act(el.dataset.batch, [...selected])));
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) refresh();
  });

  setTab(tab);
  schedule();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
