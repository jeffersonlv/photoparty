// Camera (getUserMedia) e galeria. Quando a camera nao esta disponivel ou foi
// negada, o app cai para <input type="file" capture="environment">.

import { fitWithin } from './frame-engine.js';
import { makeCanvas } from './effects.js';

/** Lado maior maximo da foto de trabalho (economiza memoria no celular). */
export const WORKING_MAX_SIDE = 2400;
/** Limite de tamanho do arquivo escolhido na galeria. */
export const MAX_FILE_BYTES = 30 * 1024 * 1024;

export function isCameraSupported() {
  return typeof navigator !== 'undefined'
    && !!navigator.mediaDevices?.getUserMedia
    && (typeof isSecureContext === 'undefined' || isSecureContext);
}

/** Classifica o erro do getUserMedia para escolher a mensagem certa. */
export function classifyCameraError(err) {
  const name = err?.name ?? '';
  if (name === 'NotAllowedError' || name === 'SecurityError' || name === 'PermissionDeniedError') return 'denied';
  if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || name === 'OverconstrainedError') return 'notfound';
  if (name === 'NotReadableError' || name === 'AbortError') return 'busy';
  return 'unknown';
}

/** Restricoes do getUserMedia: traseira por padrao, frontal quando facing = 'user'. */
export function buildConstraints(facing = 'environment') {
  return {
    audio: false,
    video: { facingMode: { ideal: facing }, width: { ideal: 1920 }, height: { ideal: 1920 } },
  };
}

/** Frontal e espelhada (como um espelho), traseira nao. */
export const shouldMirror = (facing) => facing === 'user';

export async function startCamera(videoEl, facing = 'environment') {
  const stream = await navigator.mediaDevices.getUserMedia(buildConstraints(facing));
  videoEl.srcObject = stream;
  videoEl.muted = true;
  videoEl.setAttribute('playsinline', ''); // iOS: evita abrir em tela cheia
  await videoEl.play();
  return stream;
}

export function stopCamera(stream, videoEl) {
  if (stream) for (const track of stream.getTracks()) track.stop();
  if (videoEl) videoEl.srcObject = null;
}

/** Captura o frame atual do video num canvas (espelhado quando for a frontal). */
export function captureFrame(videoEl, { mirror = false, maxSide = WORKING_MAX_SIDE } = {}) {
  const vw = videoEl.videoWidth;
  const vh = videoEl.videoHeight;
  if (!vw || !vh) throw new Error('video-not-ready');
  const { width, height } = fitWithin(vw, vh, maxSide);
  const canvas = makeCanvas(width, height);
  const ctx = canvas.getContext('2d');
  if (mirror) {
    ctx.translate(width, 0);
    ctx.scale(-1, 1);
  }
  ctx.drawImage(videoEl, 0, 0, width, height);
  return canvas;
}

/** Decodifica respeitando a orientacao EXIF. Navegadores sem a opcao usam <img>, que ja aplica EXIF. */
async function decodeFile(file) {
  if (typeof createImageBitmap === 'function') {
    try {
      return await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
      // Safari antigo rejeita a opcao: tenta sem ela e cai para <img> abaixo
    }
  }
  const url = URL.createObjectURL(file);
  try {
    return await new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('decode-failed'));
      img.src = url;
    });
  } finally {
    URL.revokeObjectURL(url);
  }
}

/**
 * Le um arquivo de imagem (galeria ou captura nativa) e devolve um canvas com a
 * orientacao EXIF ja corrigida e lado maior limitado a maxSide.
 * Rejeita com Error('not-image' | 'too-large' | 'decode-failed').
 */
export async function readImageFile(file, { maxSide = WORKING_MAX_SIDE } = {}) {
  if (!file || !/^image\//i.test(file.type || '')) throw new Error('not-image');
  if (file.size > MAX_FILE_BYTES) throw new Error('too-large');
  let source;
  try {
    source = await decodeFile(file);
  } catch {
    throw new Error('decode-failed');
  }
  const sw = source.naturalWidth || source.width;
  const sh = source.naturalHeight || source.height;
  const { width, height } = fitWithin(sw, sh, maxSide);
  const canvas = makeCanvas(width, height);
  const ctx = canvas.getContext('2d');
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(source, 0, 0, width, height);
  if (typeof source.close === 'function') source.close();
  return canvas;
}
