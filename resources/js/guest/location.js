// "Envio so no local do evento": leitura da posicao (Geolocation API) e conferencia no servidor.
// Logica pura e injetavel (testada no vitest). A posicao so vai ao endpoint de conferencia;
// nada e guardado no aparelho alem da validade do passe (em memoria).

/** Opcoes pedidas: alta precisao, ate 15 s, sem leitura em cache. */
export const GEO_OPTIONS = Object.freeze({ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });

/** Mensagem (chave de traducao) para cada codigo de erro/recusa. */
const MESSAGE_KEYS = {
  denied: 'geo_denied',
  timeout: 'geo_timeout',
  unavailable: 'geo_unavailable',
  unsupported: 'geo_unsupported',
  outside: 'geo_outside',
  inaccurate: 'geo_inaccurate',
  stale: 'geo_stale',
  rate: 'geo_rate',
  expired: 'geo_expired',
  network: 'error_network',
  session: 'error_session',
  ended: 'error_ended',
};

export function locationMessageKey(code) {
  return MESSAGE_KEYS[code] ?? 'geo_error';
}

/** Erro da Geolocation API => denied | unavailable | timeout | unsupported | error. */
export function classifyGeoError(err) {
  switch (err?.code) {
    case 1: return 'denied'; // PERMISSION_DENIED
    case 2: return 'unavailable'; // POSITION_UNAVAILABLE
    case 3: return 'timeout'; // TIMEOUT
    default: return err?.message === 'unsupported' ? 'unsupported' : 'unavailable';
  }
}

/**
 * Corpo enviado ao servidor: {latitude, longitude, accuracy, timestamp(ms)}.
 * Devolve null se a posicao nao tiver numeros validos.
 */
export function buildLocationPayload(position) {
  const c = position?.coords;
  const latitude = Number(c?.latitude);
  const longitude = Number(c?.longitude);
  const accuracy = Number(c?.accuracy);
  const timestamp = Math.round(Number(position?.timestamp));
  if (![latitude, longitude, accuracy, timestamp].every(Number.isFinite)) return null;
  if (Math.abs(latitude) > 90 || Math.abs(longitude) > 180 || accuracy < 0) return null;
  return { latitude, longitude, accuracy, timestamp };
}

/** "340 m" / "1,2 km" (pt) / "1.2 km" (en): mesmo formato do servidor. */
export function formatDistance(meters, lang = 'pt_BR') {
  const m = Math.max(0, Math.round(Number(meters) || 0));
  if (m < 1000) return `${m} m`;
  const km = (m / 1000).toFixed(1);
  return `${String(lang).startsWith('en') ? km : km.replace('.', ',')} km`;
}

/** Promessa sobre getCurrentPosition. Sem API => rejeita com {message:'unsupported'}. */
export function getPosition(geo = (typeof navigator !== 'undefined' ? navigator.geolocation : null)) {
  return new Promise((resolve, reject) => {
    if (!geo || typeof geo.getCurrentPosition !== 'function') {
      reject(Object.assign(new Error('unsupported'), { code: 0 }));
      return;
    }
    geo.getCurrentPosition(resolve, reject, { ...GEO_OPTIONS });
  });
}

/**
 * Envia a leitura ao servidor.
 * @returns {Promise<{ok:boolean, code?:string, distance?:number|null, expiresIn?:number}>}
 */
export async function sendLocation(url, payload, { fetchImpl, csrfToken } = {}) {
  let res;
  try {
    res = await fetchImpl(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      body: JSON.stringify(payload),
    });
  } catch {
    return { ok: false, code: 'network' };
  }
  let body = null;
  try {
    body = await res.json();
  } catch {
    body = null;
  }
  if (res.status >= 200 && res.status < 300 && body?.ok) {
    const ttl = Number(body.expires_in);
    return { ok: true, required: body.required !== false, expiresIn: Number.isFinite(ttl) && ttl > 0 ? ttl : 0 };
  }
  if (res.status === 410) return { ok: false, code: 'ended' };
  if (res.status === 429) return { ok: false, code: 'rate' };
  if (res.status === 419) return { ok: false, code: 'session' };
  if (res.status === 422 && typeof body?.code === 'string') {
    const distance = Number(body.distance_m);
    return { ok: false, code: body.code, distance: Number.isFinite(distance) ? distance : null };
  }
  return { ok: false, code: res.status >= 500 ? 'server' : 'error' };
}

/**
 * Fluxo completo: le a posicao e confere no servidor.
 * `deps.getPosition`/`deps.fetchImpl` sao injetaveis (teste).
 */
export async function checkLocation(url, { fetchImpl, csrfToken, getPositionImpl = getPosition } = {}) {
  let position;
  try {
    position = await getPositionImpl();
  } catch (err) {
    return { ok: false, code: classifyGeoError(err) };
  }
  const payload = buildLocationPayload(position);
  if (!payload) return { ok: false, code: 'unavailable' };
  return sendLocation(url, payload, { fetchImpl, csrfToken });
}

/** Texto para o convidado a partir do resultado (`t` = tradutor). */
export function locationMessage(result, t, lang) {
  if (!result || result.ok) return '';
  const key = locationMessageKey(result.code);
  if (result.code === 'outside') {
    return t(key, { distance: formatDistance(result.distance ?? 0, lang) });
  }
  return t(key);
}

/** O passe ainda vale? (margem de 30 s para nao enviar com o passe vencendo no caminho). */
export function passValid(expiresAt, now = Date.now(), marginMs = 30000) {
  return Number.isFinite(expiresAt) && expiresAt - marginMs > now;
}
