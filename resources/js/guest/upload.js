// Envio multipart com progresso (XMLHttpRequest), retry exponencial e erros tipados.

export const MAX_ATTEMPTS = 3;
const BASE_DELAY_MS = 800;
const MAX_DELAY_MS = 8000;

/** Erro de upload com `kind`: ended | rate | invalid | session | server | network | aborted. */
export class UploadError extends Error {
  constructor(kind, { status = 0, errors = null, message = kind, retryAfter = null } = {}) {
    super(message);
    this.name = 'UploadError';
    this.kind = kind;
    this.status = status;
    this.errors = errors;
    this.retryAfter = retryAfter;
  }
}

/** Espera exponencial: 800 ms, 1,6 s, 3,2 s... limitada a 8 s, com jitter opcional (0..1). */
export function backoffDelay(attempt, { base = BASE_DELAY_MS, cap = MAX_DELAY_MS, jitter = 0, rand = Math.random } = {}) {
  const exp = Math.min(cap, base * 2 ** Math.max(0, attempt - 1));
  return Math.round(exp * (1 + jitter * rand()));
}

/**
 * Classifica o status HTTP. Retry so em falha transitoria (rede/5xx).
 * 410 = evento encerrado, 429 = limite, 422 = validacao, 419 = sessao/CSRF expirada.
 */
export function classifyStatus(status) {
  if (status >= 200 && status < 300) return { ok: true, retry: false, kind: null };
  if (status === 410) return { ok: false, retry: false, kind: 'ended' };
  if (status === 429) return { ok: false, retry: false, kind: 'rate' };
  if (status === 422) return { ok: false, retry: false, kind: 'invalid' };
  if (status === 419 || status === 401 || status === 403) return { ok: false, retry: false, kind: 'session' };
  if (status === 0 || status >= 500) return { ok: false, retry: true, kind: status === 0 ? 'network' : 'server' };
  return { ok: false, retry: false, kind: 'server' };
}

/** Monta o multipart do contrato com o backend. */
export function buildFormData({ photo, thumb, frameKey, filter, orientation, guestName, guestTexts, consentVersion, csrfToken }) {
  const fd = new FormData();
  fd.append('photo', photo, 'photo.jpg');
  fd.append('thumb', thumb, 'thumb.jpg');
  fd.append('frame_key', frameKey ?? '');
  fd.append('filter', filter ?? 'none');
  fd.append('orientation', orientation);
  fd.append('guest_name', guestName ?? '');
  fd.append('guest_texts', JSON.stringify(guestTexts ?? {}));
  // consentimento LGPD/GDPR: o servidor recusa (422) sem consent=1 e a versao vigente
  if (consentVersion) {
    fd.append('consent', '1');
    fd.append('consent_version', consentVersion);
  }
  if (csrfToken) fd.append('_token', csrfToken);
  return fd;
}

/** Uma tentativa via XHR (fetch nao expoe progresso de upload). Resolve {status, body}. */
export function sendOnce(url, formData, { onProgress, signal, csrfToken, xhrFactory = () => new XMLHttpRequest() } = {}) {
  return new Promise((resolve, reject) => {
    const xhr = xhrFactory();
    xhr.open('POST', url);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    if (csrfToken) xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable && onProgress) onProgress(e.loaded / e.total);
    };
    xhr.onload = () => {
      let body = null;
      try {
        body = xhr.responseText ? JSON.parse(xhr.responseText) : null;
      } catch {
        body = null;
      }
      resolve({ status: xhr.status, body, retryAfter: Number(xhr.getResponseHeader?.('Retry-After')) || null });
    };
    xhr.onerror = () => resolve({ status: 0, body: null, retryAfter: null });
    xhr.ontimeout = () => resolve({ status: 0, body: null, retryAfter: null });
    xhr.onabort = () => reject(new UploadError('aborted'));
    if (signal) {
      if (signal.aborted) return reject(new UploadError('aborted'));
      signal.addEventListener('abort', () => xhr.abort(), { once: true });
    }
    xhr.timeout = 60000;
    xhr.send(formData);
  });
}

/** Primeira mensagem de erro de um corpo 422 no formato do Laravel ({errors:{campo:[msg]}}). */
export function firstValidationMessage(body) {
  const errors = body?.errors;
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat()[0];
    if (typeof first === 'string') return first;
  }
  return typeof body?.message === 'string' ? body.message : null;
}

/**
 * Envia com ate `maxAttempts` tentativas (retry so para rede/5xx).
 * `send` e `sleep` sao injetaveis para teste.
 * @returns {Promise<object|null>} corpo JSON da resposta de sucesso
 * @throws {UploadError}
 */
export async function uploadPhoto(url, payload, {
  onProgress,
  signal,
  maxAttempts = MAX_ATTEMPTS,
  send = sendOnce,
  sleep = (ms) => new Promise((r) => setTimeout(r, ms)),
  onRetry,
} = {}) {
  const csrfToken = payload.csrfToken;
  let last = null;
  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    // FormData novo a cada tentativa (o Blob continua o mesmo, nao e copiado)
    const fd = buildFormData(payload);
    onProgress?.(0);
    const res = await send(url, fd, { onProgress, signal, csrfToken });
    const cls = classifyStatus(res.status);
    if (cls.ok) {
      onProgress?.(1);
      return res.body;
    }
    last = new UploadError(cls.kind, {
      status: res.status,
      errors: res.body?.errors ?? null,
      message: firstValidationMessage(res.body) ?? cls.kind,
      retryAfter: res.retryAfter,
    });
    if (!cls.retry || attempt === maxAttempts) break;
    onRetry?.(attempt);
    await sleep(backoffDelay(attempt, { jitter: 0.25 }));
  }
  throw last;
}
