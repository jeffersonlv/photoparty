// Motor de moldura: geometria pura (testavel em Node) + composicao no Canvas.
// Ordem de desenho do contrato: background -> foto (cover + pan/zoom, recortada
// a janela) -> efeito (so na janela) -> overlay -> textos.

import { ORIENTATIONS, resolveAssetUrl, resolveText, validateFrameConfig } from './frame-config.js';
import { applyEffect, applyGuestFilter, makeCanvas } from './effects.js';

export { ORIENTATIONS };
export const MAX_ZOOM = 4;
const MIN_FONT_SIZE = 6;

/* ------------------------------------------------------------------ */
/* Geometria pura                                                      */
/* ------------------------------------------------------------------ */

/** Orientacao cuja proporcao esta mais perto da foto (distancia em escala log). */
export function chooseOrientation(photoW, photoH) {
  if (!(photoW > 0) || !(photoH > 0)) return 'portrait';
  const r = Math.log(photoW / photoH);
  const dist = (o) => Math.abs(r - Math.log(ORIENTATIONS[o].width / ORIENTATIONS[o].height));
  return dist('portrait') <= dist('landscape') ? 'portrait' : 'landscape';
}

/** Reduz (nunca amplia) mantendo proporcao para caber em maxSide no lado maior. */
export function fitWithin(w, h, maxSide) {
  const longest = Math.max(w, h);
  if (longest <= maxSide) return { width: Math.round(w), height: Math.round(h) };
  const k = maxSide / longest;
  return { width: Math.max(1, Math.round(w * k)), height: Math.max(1, Math.round(h * k)) };
}

export const clampZoom = (z, min = 1, max = MAX_ZOOM) => Math.min(max, Math.max(min, Number.isFinite(z) ? z : 1));

/** Escala minima para a foto cobrir a janela (modo cover). */
export function baseCoverScale(imgW, imgH, winW, winH) {
  return Math.max(winW / imgW, winH / imgH);
}

/**
 * Pan e o deslocamento (em px da janela) do centro da foto em relacao ao centro da janela.
 * Limita para que a foto sempre cubra a janela inteira.
 * @param {{zoom:number,panX:number,panY:number}} view
 * @param {{imgW:number,imgH:number,winW:number,winH:number}} g
 */
export function clampView(view, g) {
  const zoom = clampZoom(view.zoom);
  const s = baseCoverScale(g.imgW, g.imgH, g.winW, g.winH) * zoom;
  const maxX = Math.max(0, (g.imgW * s - g.winW) / 2);
  const maxY = Math.max(0, (g.imgH * s - g.winH) / 2);
  return {
    zoom,
    panX: Math.min(maxX, Math.max(-maxX, view.panX || 0)),
    panY: Math.min(maxY, Math.max(-maxY, view.panY || 0)),
  };
}

/** Retangulo da foto relativo ao canto superior esquerdo da janela. */
export function coverRect(g, view) {
  const v = clampView(view, g);
  const scale = baseCoverScale(g.imgW, g.imgH, g.winW, g.winH) * v.zoom;
  const w = g.imgW * scale;
  const h = g.imgH * scale;
  return { scale, w, h, x: (g.winW - w) / 2 + v.panX, y: (g.winH - h) / 2 + v.panY };
}

/**
 * Novo zoom mantendo fixo o ponto sob o foco (pinca/roda).
 * @param focal posicao do foco em px da janela, relativa ao CENTRO da janela
 */
export function zoomAround(view, g, newZoom, focal = { x: 0, y: 0 }) {
  const z0 = clampZoom(view.zoom);
  const z1 = clampZoom(newZoom);
  const k = z1 / z0;
  return clampView(
    { zoom: z1, panX: focal.x - (focal.x - view.panX) * k, panY: focal.y - (focal.y - view.panY) * k },
    g,
  );
}

/**
 * Tamanho de fonte para o texto caber em maxWidth: reduz proporcionalmente, nunca corta.
 * `measure(text, size)` devolve a largura (injetada para testar sem Canvas).
 */
export function fitFontSize({ text, size, maxWidth, measure, minSize = MIN_FONT_SIZE }) {
  if (!text) return size;
  let current = size;
  let width = measure(text, current);
  if (width <= maxWidth) return current;
  current = Math.max(minSize, Math.floor((current * maxWidth) / width * 100) / 100);
  // A largura nem sempre e linear no tamanho (hinting): refina com passos de 2%.
  for (let i = 0; i < 20 && current > minSize; i += 1) {
    width = measure(text, current);
    if (width <= maxWidth) break;
    current = Math.max(minSize, current * 0.98);
  }
  return current;
}

/* ------------------------------------------------------------------ */
/* Carregamento de config e imagens                                    */
/* ------------------------------------------------------------------ */

function loadImage(url) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.decoding = 'async';
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error(`Falha ao carregar imagem: ${url}`));
    img.src = url;
  });
}

/** Baixa e valida o config.json. Falha com erro legivel se violar o contrato. */
export async function loadFrameConfig(configUrl, { fetchFn = fetch } = {}) {
  const res = await fetchFn(configUrl, { credentials: 'same-origin' });
  if (!res.ok) throw new Error(`config HTTP ${res.status}`);
  const cfg = await res.json();
  const { valid, errors } = validateFrameConfig(cfg, { requireBoth: false });
  if (!valid) throw new Error(`config invalido: ${errors.slice(0, 3).join('; ')}`);
  return cfg;
}

/** Fontes ainda nao usadas no documento so carregam sob demanda: forca antes de medir. */
export async function ensureFonts(orientationCfg) {
  if (typeof document === 'undefined' || !document.fonts?.load) return;
  const fonts = new Set((orientationCfg.texts ?? []).map((t) => t.font));
  await Promise.all([...fonts].map((f) => document.fonts.load(`40px "${f}"`).catch(() => [])));
}

/** Imagens (background/overlay) de uma orientacao, ja decodificadas. */
export async function loadOrientationAssets(configUrl, orientationCfg) {
  const [background, overlay] = await Promise.all([
    orientationCfg.background ? loadImage(resolveAssetUrl(configUrl, orientationCfg.background)) : null,
    orientationCfg.overlay ? loadImage(resolveAssetUrl(configUrl, orientationCfg.overlay)) : null,
    ensureFonts(orientationCfg),
  ]);
  return { background, overlay };
}

/** Configuracao sintetica para "sem moldura": a foto ocupa o quadro inteiro. */
export function plainOrientation(orientation) {
  const { width, height } = ORIENTATIONS[orientation];
  return { width, height, background: null, window: { x: 0, y: 0, w: width, h: height }, overlay: null, texts: [], effect: null };
}

/* ------------------------------------------------------------------ */
/* Composicao                                                          */
/* ------------------------------------------------------------------ */

function drawTexts(ctx, texts, values, lang, scale) {
  for (const def of texts) {
    const text = resolveText(def, values, lang);
    if (!text) continue;
    const family = `"${def.font}", sans-serif`;
    const measure = (t, size) => {
      ctx.font = `${size * scale}px ${family}`;
      return ctx.measureText(t).width / scale;
    };
    const size = fitFontSize({ text, size: def.size, maxWidth: def.maxWidth, measure });
    ctx.save();
    ctx.font = `${size * scale}px ${family}`;
    ctx.fillStyle = def.color;
    ctx.textAlign = def.align;
    ctx.textBaseline = def.baseline;
    if (def.shadow) {
      ctx.shadowColor = def.shadow.color;
      ctx.shadowBlur = def.shadow.blur * scale;
      ctx.shadowOffsetX = def.shadow.offsetX * scale;
      ctx.shadowOffsetY = def.shadow.offsetY * scale;
    }
    ctx.fillText(text, def.x * scale, def.y * scale);
    ctx.restore();
  }
}

/**
 * Compoe a imagem final em `canvas` (redimensiona o canvas para design * scale).
 * @param {HTMLCanvasElement} canvas
 * @param {object} o
 * @param {object} o.cfg configuracao da orientacao (ou plainOrientation)
 * @param {{background:?CanvasImageSource, overlay:?CanvasImageSource}} [o.assets]
 * @param {CanvasImageSource & {width:number,height:number}} o.photo foto ja orientada
 * @param {{zoom:number,panX:number,panY:number}} o.view
 * @param {string} [o.filter] chave do filtro do convidado
 * @param {Record<string,string>} [o.texts] valores por id
 * @param {string} [o.lang]
 * @param {number} [o.scale] 1 = tamanho final; menos para a previa
 */
export function renderComposition(canvas, o) {
  const { cfg, assets = {}, photo, view, filter = 'none', texts = {}, lang = 'pt', scale = 1 } = o;
  const cw = Math.round(cfg.width * scale);
  const ch = Math.round(cfg.height * scale);
  // So redimensiona quando muda (atribuir width realoca o buffer a cada quadro)
  if (canvas.width !== cw) canvas.width = cw;
  if (canvas.height !== ch) canvas.height = ch;
  const ctx = canvas.getContext('2d');
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  // 1) background
  if (assets.background) ctx.drawImage(assets.background, 0, 0, canvas.width, canvas.height);

  // 2) foto em cover, recortada a janela (desenhada num canvas do tamanho da janela:
  // o recorte e natural e o efeito abaixo so enxerga os pixels da janela)
  const win = cfg.window;
  const winW = Math.max(1, Math.round(win.w * scale));
  const winH = Math.max(1, Math.round(win.h * scale));
  const layer = makeCanvas(winW, winH);
  const lctx = layer.getContext('2d');
  const rect = coverRect(
    { imgW: photo.width, imgH: photo.height, winW: win.w, winH: win.h },
    view,
  );
  lctx.imageSmoothingQuality = 'high';
  lctx.drawImage(photo, rect.x * scale, rect.y * scale, rect.w * scale, rect.h * scale);

  // filtro do convidado primeiro; efeito da moldura depois (so na janela)
  applyGuestFilter(layer, filter);
  if (cfg.effect?.type) applyEffect(layer, cfg.effect.type, cfg.effect.params ?? {}, scale);
  ctx.drawImage(layer, Math.round(win.x * scale), Math.round(win.y * scale));

  // 3) overlay
  if (assets.overlay) ctx.drawImage(assets.overlay, 0, 0, canvas.width, canvas.height);

  // 4) textos
  drawTexts(ctx, cfg.texts ?? [], texts, lang, scale);
  return canvas;
}
