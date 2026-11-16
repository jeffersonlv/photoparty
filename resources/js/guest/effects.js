// Efeitos da moldura (led, sepia) e filtros do convidado (P&B, vintage, ...).
// Os filtros usam ctx.filter quando o navegador realmente suporta e, caso
// contrario (Safari antigo), aplicam as mesmas matrizes de cor pixel a pixel.

/* ------------------------------------------------------------------ */
/* Canvas utilitario                                                   */
/* ------------------------------------------------------------------ */

export function makeCanvas(width, height) {
  if (typeof document !== 'undefined') {
    const c = document.createElement('canvas');
    c.width = width;
    c.height = height;
    return c;
  }
  return new OffscreenCanvas(width, height);
}

/* ------------------------------------------------------------------ */
/* Filtros: definicao unica, usada tanto por ctx.filter quanto pelo fallback */
/* ------------------------------------------------------------------ */

/** Cada filtro e uma lista de operacoes equivalentes as funcoes CSS de mesmo nome. */
export const FILTERS = Object.freeze({
  none: [],
  bw: [{ fn: 'grayscale', v: 1 }, { fn: 'contrast', v: 1.1 }],
  vintage: [{ fn: 'sepia', v: 0.4 }, { fn: 'contrast', v: 0.95 }, { fn: 'saturate', v: 1.2 }, { fn: 'brightness', v: 1.05 }],
  vivid: [{ fn: 'saturate', v: 1.5 }, { fn: 'contrast', v: 1.1 }],
  cool: [{ fn: 'hue-rotate', v: -12 }, { fn: 'saturate', v: 1.1 }, { fn: 'brightness', v: 1.04 }],
  warm: [{ fn: 'sepia', v: 0.25 }, { fn: 'saturate', v: 1.25 }, { fn: 'brightness', v: 1.03 }],
});

export const FILTER_KEYS = Object.freeze(Object.keys(FILTERS));

const UNITS = { 'hue-rotate': 'deg' };

/** Converte operacoes na string de ctx.filter, ex.: "sepia(0.4) contrast(0.95)". */
export function opsToCss(ops) {
  return ops.map((o) => `${o.fn}(${o.v}${UNITS[o.fn] ?? ''})`).join(' ');
}

const clamp01 = (v) => Math.min(1, Math.max(0, v));

/** Matriz 3x3 (linhas RGB) das funcoes de cor, conforme a especificacao Filter Effects. */
export function opMatrix(op) {
  switch (op.fn) {
    case 'grayscale': {
      const a = 1 - clamp01(op.v);
      return [
        0.2126 + 0.7874 * a, 0.7152 - 0.7152 * a, 0.0722 - 0.0722 * a,
        0.2126 - 0.2126 * a, 0.7152 + 0.2848 * a, 0.0722 - 0.0722 * a,
        0.2126 - 0.2126 * a, 0.7152 - 0.7152 * a, 0.0722 + 0.9278 * a,
      ];
    }
    case 'sepia': {
      const a = 1 - clamp01(op.v);
      return [
        0.393 + 0.607 * a, 0.769 - 0.769 * a, 0.189 - 0.189 * a,
        0.349 - 0.349 * a, 0.686 + 0.314 * a, 0.168 - 0.168 * a,
        0.272 - 0.272 * a, 0.534 - 0.534 * a, 0.131 + 0.869 * a,
      ];
    }
    case 'saturate': {
      const s = Math.max(0, op.v);
      return [
        0.213 + 0.787 * s, 0.715 - 0.715 * s, 0.072 - 0.072 * s,
        0.213 - 0.213 * s, 0.715 + 0.285 * s, 0.072 - 0.072 * s,
        0.213 - 0.213 * s, 0.715 - 0.715 * s, 0.072 + 0.928 * s,
      ];
    }
    case 'hue-rotate': {
      const r = (op.v * Math.PI) / 180;
      const c = Math.cos(r);
      const s = Math.sin(r);
      return [
        0.213 + c * 0.787 - s * 0.213, 0.715 - c * 0.715 - s * 0.715, 0.072 - c * 0.072 + s * 0.928,
        0.213 - c * 0.213 + s * 0.143, 0.715 + c * 0.285 + s * 0.140, 0.072 - c * 0.072 - s * 0.283,
        0.213 - c * 0.213 - s * 0.787, 0.715 - c * 0.715 + s * 0.715, 0.072 + c * 0.928 + s * 0.072,
      ];
    }
    default:
      return null; // contrast/brightness sao lineares por canal, tratadas a parte
  }
}

/** Aplica as operacoes em um buffer RGBA (Uint8ClampedArray), ignorando o alfa. */
export function applyOpsToPixels(data, ops) {
  for (const op of ops) {
    const m = opMatrix(op);
    if (m) {
      for (let i = 0; i < data.length; i += 4) {
        const r = data[i];
        const g = data[i + 1];
        const b = data[i + 2];
        data[i] = m[0] * r + m[1] * g + m[2] * b;
        data[i + 1] = m[3] * r + m[4] * g + m[5] * b;
        data[i + 2] = m[6] * r + m[7] * g + m[8] * b;
      }
    } else if (op.fn === 'contrast') {
      const c = op.v;
      const off = (-0.5 * c + 0.5) * 255;
      for (let i = 0; i < data.length; i += 4) {
        data[i] = c * data[i] + off;
        data[i + 1] = c * data[i + 1] + off;
        data[i + 2] = c * data[i + 2] + off;
      }
    } else if (op.fn === 'brightness') {
      for (let i = 0; i < data.length; i += 4) {
        data[i] *= op.v;
        data[i + 1] *= op.v;
        data[i + 2] *= op.v;
      }
    }
  }
  return data;
}

let ctxFilterSupport = null;

/**
 * Detecta se ctx.filter realmente funciona (Safari antigo nao tem a propriedade ou a
 * ignora). Teste funcional: vermelho + grayscale deve virar cinza (R === G).
 */
export function supportsCtxFilter() {
  if (ctxFilterSupport !== null) return ctxFilterSupport;
  try {
    const c = makeCanvas(2, 2);
    const ctx = c.getContext('2d', { willReadFrequently: true });
    if (!ctx || !('filter' in ctx)) return (ctxFilterSupport = false);
    ctx.filter = 'grayscale(1)';
    ctx.fillStyle = '#ff0000';
    ctx.fillRect(0, 0, 2, 2);
    const d = ctx.getImageData(0, 0, 1, 1).data;
    ctxFilterSupport = d[0] === d[1] && d[0] > 0;
  } catch {
    ctxFilterSupport = false;
  }
  return ctxFilterSupport;
}

/** Aplica uma lista de operacoes no canvas inteiro (in-place). */
export function applyOps(canvas, ops) {
  if (!ops.length) return canvas;
  const ctx = canvas.getContext('2d', { willReadFrequently: !supportsCtxFilter() });
  if (supportsCtxFilter()) {
    const copy = makeCanvas(canvas.width, canvas.height);
    copy.getContext('2d').drawImage(canvas, 0, 0);
    ctx.save();
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.filter = opsToCss(ops);
    ctx.drawImage(copy, 0, 0);
    ctx.restore();
  } else {
    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    applyOpsToPixels(img.data, ops);
    ctx.putImageData(img, 0, 0);
  }
  return canvas;
}

/** Filtro escolhido pelo convidado (chave de FILTERS). Chave desconhecida = nenhum. */
export function applyGuestFilter(canvas, key) {
  return applyOps(canvas, FILTERS[key] ?? []);
}

/* ------------------------------------------------------------------ */
/* Efeito "sepia" da moldura                                           */
/* ------------------------------------------------------------------ */

export function sepiaOps(params = {}) {
  const amount = typeof params.amount === 'number' ? params.amount : 0.9;
  return [{ fn: 'sepia', v: clamp01(amount) }, { fn: 'contrast', v: 1.05 }];
}

/* ------------------------------------------------------------------ */
/* Efeito "led": grade de pontos com brilho                            */
/* ------------------------------------------------------------------ */

export const LED_DEFAULTS = Object.freeze({ dotSize: 6, gap: 2, glow: 0.35, boost: 1.15, background: '#05060a' });

/** Grade do painel: passo = (dotSize + gap) * escala; colunas/linhas cobrem a janela. */
export function ledGrid(width, height, dotSize, gap, scale = 1) {
  const pitch = Math.max(2, (dotSize + gap) * scale);
  return { pitch, cols: Math.max(1, Math.ceil(width / pitch)), rows: Math.max(1, Math.ceil(height / pitch)) };
}

/** Reduz em passos de metade: drawImage direto de uma reducao grande amostra poucos pixels. */
function stepDown(src, tw, th) {
  let cur = src;
  while (cur.width / 2 >= tw && cur.height / 2 >= th) {
    const next = makeCanvas(Math.max(tw, Math.floor(cur.width / 2)), Math.max(th, Math.floor(cur.height / 2)));
    const nctx = next.getContext('2d');
    nctx.imageSmoothingQuality = 'high';
    nctx.drawImage(cur, 0, 0, next.width, next.height);
    cur = next;
  }
  const out = makeCanvas(tw, th);
  const octx = out.getContext('2d');
  octx.imageSmoothingQuality = 'high';
  octx.drawImage(cur, 0, 0, tw, th);
  return out;
}

/**
 * Transforma o canvas (so a janela) num painel de LED: cada celula vira um ponto
 * circular com a cor media da regiao, sobre fundo escuro, mais um brilho suave.
 * `scale` ajusta os parametros (em px do design) ao tamanho real do canvas.
 */
export function applyLed(canvas, params = {}, scale = 1) {
  const p = { ...LED_DEFAULTS, ...params };
  const w = canvas.width;
  const h = canvas.height;
  const { pitch, cols, rows } = ledGrid(w, h, p.dotSize, p.gap, scale);

  // 1 pixel por celula = cor media daquela regiao da foto
  const small = stepDown(canvas, cols, rows);
  const px = small.getContext('2d', { willReadFrequently: true }).getImageData(0, 0, cols, rows).data;

  const ctx = canvas.getContext('2d');
  ctx.save();
  ctx.globalCompositeOperation = 'source-over';
  ctx.globalAlpha = 1;
  ctx.fillStyle = p.background;
  ctx.fillRect(0, 0, w, h);
  const radius = Math.max(0.8, (p.dotSize * scale) / 2);
  for (let j = 0; j < rows; j += 1) {
    for (let i = 0; i < cols; i += 1) {
      const k = (j * cols + i) * 4;
      const r = Math.min(255, px[k] * p.boost);
      const g = Math.min(255, px[k + 1] * p.boost);
      const b = Math.min(255, px[k + 2] * p.boost);
      ctx.fillStyle = `rgb(${r | 0},${g | 0},${b | 0})`;
      ctx.beginPath();
      ctx.arc((i + 0.5) * pitch, (j + 0.5) * pitch, radius, 0, Math.PI * 2);
      ctx.fill();
    }
  }

  // brilho: copia borrada (reducao + ampliacao) somada com 'lighter'
  const glow = clamp01(p.glow);
  if (glow > 0) {
    const blur = stepDown(canvas, Math.max(2, Math.ceil(w / 10)), Math.max(2, Math.ceil(h / 10)));
    ctx.globalCompositeOperation = 'lighter';
    ctx.globalAlpha = glow;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(blur, 0, 0, w, h);
  }
  ctx.restore();
  return canvas;
}

/** Efeito da moldura por tipo (contrato: "led" | "sepia" | null). */
export function applyEffect(canvas, type, params = {}, scale = 1) {
  if (type === 'led') return applyLed(canvas, params, scale);
  if (type === 'sepia') return applyOps(canvas, sepiaOps(params));
  return canvas;
}
