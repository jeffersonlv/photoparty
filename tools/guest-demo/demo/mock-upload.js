// Envio simulado para a demo. Usa o uploadPhoto REAL (retry, backoff, classificacao de
// status) trocando so a camada de rede por uma funcao que finge o servidor.

import { uploadPhoto } from '../resources/js/guest/upload.js';

const params = new URLSearchParams(location.search);
const fail = params.get('fail'); // 500 | 410 | 429 | 422

let attempts = 0;

function fakeSend(_url, formData, { onProgress }) {
  attempts += 1;
  return new Promise((resolve) => {
    let loaded = 0;
    const timer = setInterval(() => {
      loaded += 0.2;
      onProgress?.(Math.min(1, loaded));
      if (loaded < 1) return;
      clearInterval(timer);
      if (fail === '500' && attempts <= 2) return resolve({ status: 503, body: null });
      if (fail === '410') return resolve({ status: 410, body: { message: 'ended' } });
      if (fail === '429') return resolve({ status: 429, body: null, retryAfter: 30 });
      if (fail === '422') return resolve({ status: 422, body: { errors: { photo: ['Arquivo de imagem invalido (demo).'] } } });
      // sucesso: reporta o que o servidor receberia
      window.dispatchEvent(new CustomEvent('demo:upload', { detail: describe(formData) }));
      return resolve({ status: 201, body: { id: 'demo' } });
    }, 250);
  });
}

function describe(fd) {
  const photo = fd.get('photo');
  const thumb = fd.get('thumb');
  return {
    photo, thumb,
    fields: Object.fromEntries(['frame_key', 'filter', 'orientation', 'guest_name', 'guest_texts', '_token'].map((k) => [k, fd.get(k)])),
  };
}

export function mockUpload(url, payload, opts = {}) {
  attempts = 0;
  // espera de retry curta na demo (800 ms/1,6 s reais seriam lentos para testar)
  return uploadPhoto(url, payload, { ...opts, send: fakeSend, sleep: (ms) => new Promise((r) => setTimeout(r, Math.min(ms, 600))) });
}
