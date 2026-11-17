// Telão: carrossel com fila local, polling de 5 s e renovação das URLs assinadas.
// Sem script inline (CSP): a configuração vem de <script type="application/json" id="pp-screen">.

import { parseConfig } from './config.js';
import { ImageCache } from './image-cache.js';
import { msUntilFreshSwitch, PhotoQueue } from './queue.js';
import { ScreenSync, StoppedError } from './sync.js';

const URL_SAFETY_MS = 5000;
const IDLE_MS = 3000;
const PHRASE_MS = 6000;

function boot() {
  const block = document.getElementById('pp-screen');
  const root = document.getElementById('sc-root');

  if (!block || !root) return;

  const cfg = parseConfig(block.textContent);
  const layers = [document.getElementById('sc-layer-a'), document.getElementById('sc-layer-b')];
  const emptyEl = document.getElementById('sc-empty');
  const endedEl = document.getElementById('sc-ended');
  const offlineEl = document.getElementById('sc-offline');
  const stoppedEl = document.getElementById('sc-stopped');
  const fsButton = document.getElementById('sc-fullscreen');

  const queue = new PhotoQueue();
  const cache = new ImageCache();
  let stopped = false;
  let shownAny = false;
  let active = 1; // índice da camada visível (a outra recebe a próxima foto)
  let forceResync = false;
  let currentUuid = null; // foto na tela agora

  const canUse = (item) => cache.has(item.uuid) || item.expiresAt - URL_SAFETY_MS > Date.now();

  async function fetchPage(cursor) {
    const url = cursor ? `${cfg.pollUrl}?after=${encodeURIComponent(cursor)}` : cfg.pollUrl;
    const res = await fetch(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
    });

    if (res.status === 404 || res.status === 410) throw new StoppedError();
    if (!res.ok) throw new Error(`poll ${res.status}`);

    return res.json();
  }

  const sync = new ScreenSync({ fetchPage, queue });

  // ---------------------------------------------------------------- interface

  function setEmpty(show) {
    emptyEl.hidden = !show;
    if (show) startPhrases();
    else stopPhrases();
  }

  // Sem fotos: tira a última imagem da tela e volta ao estado inicial.
  function clearStage() {
    layers.forEach((layer) => {
      layer.classList.remove('is-active');
      layer.removeAttribute('src');
    });
    currentUuid = null;
    shownAny = false;
  }

  // ------------------------------------------------- frases enquanto não há fotos

  const phraseEl = document.getElementById('sc-phrase');
  const phrases = [...document.querySelectorAll('#sc-phrases li')].map((li) => li.textContent.trim()).filter(Boolean);
  let phraseIdx = 0;
  let phraseTimer = null;

  function startPhrases() {
    if (phraseTimer || !phraseEl || phrases.length < 2) return;

    phraseTimer = setInterval(() => {
      phraseEl.classList.add('is-fading');
      setTimeout(() => {
        phraseIdx = (phraseIdx + 1) % phrases.length;
        phraseEl.textContent = phrases[phraseIdx];
        phraseEl.classList.remove('is-fading');
      }, 600);
    }, PHRASE_MS);
  }

  function stopPhrases() {
    clearInterval(phraseTimer);
    phraseTimer = null;
  }

  function applyStatus(status) {
    if (!status) return;
    endedEl.hidden = status !== 'ended';
  }

  function stopAll() {
    stopped = true;
    stoppedEl.hidden = false;
    cancelDwell();
  }

  // ------------------------------------------------------------------ dwell

  let dwellTimer = null;
  let dwellResolve = null;
  let dwellEnd = 0;
  let shownAt = 0;

  function dwell(ms) {
    return new Promise((resolve) => {
      dwellResolve = resolve;
      dwellEnd = Date.now() + ms;
      dwellTimer = setTimeout(finishDwell, ms);
    });
  }

  function finishDwell() {
    clearTimeout(dwellTimer);
    const resolve = dwellResolve;
    dwellResolve = null;
    if (resolve) resolve();
  }

  function cancelDwell() {
    finishDwell();
  }

  // Chegou foto nova: a atual fica no mínimo MIN_DWELL na tela e depois troca.
  function cutShort() {
    if (!dwellResolve) return;

    const target = Date.now() + msUntilFreshSwitch(Date.now() - shownAt);
    if (target >= dwellEnd) return;

    clearTimeout(dwellTimer);
    dwellEnd = target;
    dwellTimer = setTimeout(finishDwell, Math.max(0, target - Date.now()));
  }

  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  // --------------------------------------------------------------- carrossel

  async function present(item) {
    const src = await cache.get(item);
    const next = layers[1 - active];
    const prev = layers[active];

    next.src = src;

    try {
      await next.decode();
    } catch {
      // decode() pode rejeitar em navegadores antigos; o load normal segue.
    }

    next.classList.add('is-active');
    prev.classList.remove('is-active');
    active = 1 - active;
    shownAny = true;
    currentUuid = item.uuid;
    shownAt = Date.now();
  }

  async function carousel() {
    while (!stopped) {
      const item = queue.next(canUse);

      if (!item) {
        // Fila vazia (ex.: todas as fotos foram rejeitadas): limpa a tela e mostra as frases.
        if (queue.size() === 0 && shownAny) clearStage();
        setEmpty(!shownAny);
        await sleep(1000);
        continue;
      }

      setEmpty(false);

      try {
        await present(item);
      } catch {
        // URL expirada, arquivo removido ou rede fora: tenta renovar e segue para a próxima.
        forceResync = true;
        await sleep(300);
        continue;
      }

      // Pré-carrega a próxima enquanto esta fica na tela.
      const upcoming = queue.peek(canUse);
      if (upcoming && !cache.has(upcoming.uuid)) cache.get(upcoming).catch(() => {});

      await dwell(cfg.secondsPerPhoto * 1000);
    }
  }

  // ----------------------------------------------------------------- polling

  async function tick() {
    if (stopped) return;

    try {
      const { status } = await sync.poll();
      offlineEl.hidden = true;
      applyStatus(status);

      if (forceResync || queue.needsRenewal(Date.now())) {
        forceResync = false;
        await sync.resync();
      }

      // A foto que está na tela foi rejeitada/removida: troca na hora.
      if (currentUuid && !queue.has(currentUuid)) cancelDwell();

      if (queue.freshCount() > 0) cutShort();
    } catch (error) {
      if (error instanceof StoppedError) {
        stopAll();
        return;
      }

      offlineEl.hidden = false; // segue girando com o que já está na fila/cache
    }

    setTimeout(tick, cfg.pollMs);
  }

  // ------------------------------------------------- tela cheia e mouse parado

  fsButton.addEventListener('click', () => {
    if (document.fullscreenElement) document.exitFullscreen();
    else document.documentElement.requestFullscreen?.();
  });

  document.addEventListener('fullscreenchange', () => {
    fsButton.textContent = document.fullscreenElement ? fsButton.dataset.exit : fsButton.dataset.enter;
  });

  let idleTimer = null;
  const wake = () => {
    root.classList.remove('is-idle');
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => root.classList.add('is-idle'), IDLE_MS);
  };
  ['mousemove', 'keydown', 'touchstart'].forEach((name) => document.addEventListener(name, wake, { passive: true }));
  wake();

  root.style.setProperty('--sc-fade', cfg.transition === 'none' ? '0ms' : '1000ms');
  applyStatus(cfg.status);
  setEmpty(true);

  tick();
  carousel();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
