// Exportacao: foto final (JPEG 0,85) e miniatura (lado maior 400 px, JPEG 0,8).

import { fitWithin } from './frame-engine.js';
import { makeCanvas } from './effects.js';

export const PHOTO_QUALITY = 0.85;
export const THUMB_QUALITY = 0.8;
export const THUMB_MAX_SIDE = 400;
/** Alvos de referencia (~400 KB / ~40 KB). Acima do teto, a qualidade e reduzida. */
export const PHOTO_TARGET_BYTES = 400 * 1024;
export const PHOTO_MAX_BYTES = 600 * 1024;
export const THUMB_TARGET_BYTES = 40 * 1024;
export const THUMB_MAX_BYTES = 100 * 1024;

/** Tamanho da miniatura: lado maior = 400 px, mantendo proporcao (nunca amplia). */
export function thumbnailSize(width, height, maxSide = THUMB_MAX_SIDE) {
  return fitWithin(width, height, maxSide);
}

/** Proxima qualidade quando o arquivo passou do teto; null = nao ha como reduzir mais. */
export function nextQuality(quality, { step = 0.05, min = 0.5 } = {}) {
  const next = Math.round((quality - step) * 100) / 100;
  return next < min ? null : next;
}

/**
 * Codifica reduzindo a qualidade ate caber em maxBytes (ou chegar no minimo).
 * `encode(quality)` devolve Promise<Blob>; injetado para testar sem Canvas.
 */
export async function encodeUnderBudget(encode, { quality, maxBytes, step = 0.05, min = 0.5 }) {
  let q = quality;
  let blob = await encode(q);
  while (blob.size > maxBytes) {
    const next = nextQuality(q, { step, min });
    if (next === null) break;
    q = next;
    blob = await encode(q);
  }
  return { blob, quality: q };
}

export function canvasToBlob(canvas, type = 'image/jpeg', quality = PHOTO_QUALITY) {
  return new Promise((resolve, reject) => {
    if (canvas.toBlob) {
      canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('encode-failed'))), type, quality);
    } else {
      // Fallback via dataURL (navegadores sem toBlob)
      try {
        const [head, b64] = canvas.toDataURL(type, quality).split(',');
        const bin = atob(b64);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i += 1) bytes[i] = bin.charCodeAt(i);
        resolve(new Blob([bytes], { type: /:(.*?);/.exec(head)[1] }));
      } catch (e) {
        reject(e);
      }
    }
  });
}

/** Reduz o canvas em passos de metade para a miniatura sair nitida. */
function downscale(canvas, width, height) {
  let cur = canvas;
  while (cur.width / 2 >= width && cur.height / 2 >= height) {
    const next = makeCanvas(Math.floor(cur.width / 2), Math.floor(cur.height / 2));
    const c = next.getContext('2d');
    c.imageSmoothingQuality = 'high';
    c.drawImage(cur, 0, 0, next.width, next.height);
    cur = next;
  }
  const out = makeCanvas(width, height);
  const c = out.getContext('2d');
  c.imageSmoothingQuality = 'high';
  c.drawImage(cur, 0, 0, width, height);
  return out;
}

/**
 * Gera os dois Blobs a partir do canvas final (ja no tamanho da orientacao).
 * @returns {Promise<{photo: Blob, thumb: Blob, width: number, height: number, photoQuality: number}>}
 */
export async function exportImages(canvas) {
  const photo = await encodeUnderBudget((q) => canvasToBlob(canvas, 'image/jpeg', q), {
    quality: PHOTO_QUALITY,
    maxBytes: PHOTO_MAX_BYTES,
  });
  const t = thumbnailSize(canvas.width, canvas.height);
  const small = downscale(canvas, t.width, t.height);
  const thumb = await encodeUnderBudget((q) => canvasToBlob(small, 'image/jpeg', q), {
    quality: THUMB_QUALITY,
    maxBytes: THUMB_MAX_BYTES,
  });
  return { photo: photo.blob, thumb: thumb.blob, width: canvas.width, height: canvas.height, photoQuality: photo.quality };
}
