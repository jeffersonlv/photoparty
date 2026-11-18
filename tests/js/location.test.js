// "Envio so no local do evento" (geofence): logica pura do front do convidado.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { resolve } from 'node:path';
import { parsePhpLang } from '../../tools/guest-demo/scripts/lang.mjs';
import { createTranslator } from '../../resources/js/guest/i18n.js';
import {
  GEO_OPTIONS, buildLocationPayload, checkLocation, classifyGeoError, formatDistance, getPosition,
  locationMessage, locationMessageKey, passValid, sendLocation,
} from '../../resources/js/guest/location.js';
import { errorKind, uploadPhoto } from '../../resources/js/guest/upload.js';
import { guestApp, normalizeEventConfig, normalizeLocation } from '../../resources/js/guest/guest-app.js';

const root = resolve(import.meta.dirname, '../..');
const pt = parsePhpLang(resolve(root, 'lang/pt_BR/guest.php'));
const en = parsePhpLang(resolve(root, 'lang/en/guest.php'));

const position = (over = {}) => ({
  coords: { latitude: -23.561414, longitude: -46.655881, accuracy: 25, ...over },
  timestamp: 1763460000123.4,
});
const res = (status, body = null) => ({ status, ok: status >= 200 && status < 300, json: async () => body });

describe('leitura da posicao', () => {
  it('pede alta precisao, timeout de 15 s e sem cache', () => {
    expect(GEO_OPTIONS).toEqual({ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    const geo = { getCurrentPosition: vi.fn((ok) => ok(position())) };
    return getPosition(geo).then((p) => {
      expect(p.coords.accuracy).toBe(25);
      expect(geo.getCurrentPosition.mock.calls[0][2]).toEqual(GEO_OPTIONS);
    });
  });

  it('sem Geolocation API => unsupported', async () => {
    const err = await getPosition(null).catch((e) => e);
    expect(classifyGeoError(err)).toBe('unsupported');
  });

  it('monta o payload {latitude, longitude, accuracy, timestamp em ms inteiro}', () => {
    expect(buildLocationPayload(position())).toEqual({
      latitude: -23.561414, longitude: -46.655881, accuracy: 25, timestamp: 1763460000123,
    });
  });

  it('descarta posicoes invalidas', () => {
    expect(buildLocationPayload(null)).toBeNull();
    expect(buildLocationPayload(position({ latitude: Number.NaN }))).toBeNull();
    expect(buildLocationPayload(position({ latitude: 91 }))).toBeNull();
    expect(buildLocationPayload(position({ accuracy: -1 }))).toBeNull();
    expect(buildLocationPayload({ coords: position().coords })).toBeNull();
  });

  it('classifica os erros da API (permissao, indisponivel, timeout)', () => {
    expect(classifyGeoError({ code: 1 })).toBe('denied');
    expect(classifyGeoError({ code: 2 })).toBe('unavailable');
    expect(classifyGeoError({ code: 3 })).toBe('timeout');
    expect(classifyGeoError(new Error('x'))).toBe('unavailable');
  });
});

describe('mensagens', () => {
  const tpt = createTranslator(pt);
  const ten = createTranslator(en);

  it('cada codigo tem texto em pt_BR e en', () => {
    for (const code of ['denied', 'timeout', 'unavailable', 'unsupported', 'outside', 'inaccurate', 'stale', 'rate', 'expired', 'network', 'session', 'ended', 'invalid', 'server']) {
      const key = locationMessageKey(code);
      expect(pt[key], `${code} -> ${key}`).toBeTypeOf('string');
      expect(en[key], `${code} -> ${key}`).toBeTypeOf('string');
    }
  });

  it('permissao negada e timeout tem mensagens proprias', () => {
    expect(locationMessage({ ok: false, code: 'denied' }, tpt, 'pt_BR')).toBe(pt.geo_denied);
    expect(locationMessage({ ok: false, code: 'timeout' }, tpt, 'pt_BR')).toBe(pt.geo_timeout);
    expect(locationMessage({ ok: false, code: 'inaccurate' }, tpt, 'pt_BR')).toContain('janela');
    expect(locationMessage({ ok: true }, tpt, 'pt_BR')).toBe('');
  });

  it('fora do raio mostra a distancia aproximada no formato do idioma', () => {
    expect(locationMessage({ ok: false, code: 'outside', distance: 340 }, tpt, 'pt_BR')).toContain('340 m');
    expect(locationMessage({ ok: false, code: 'outside', distance: 1200 }, tpt, 'pt_BR')).toContain('1,2 km');
    expect(locationMessage({ ok: false, code: 'outside', distance: 1200 }, ten, 'en')).toContain('1.2 km');
    expect(formatDistance(999)).toBe('999 m');
  });
});

describe('conferencia no servidor', () => {
  it('envia JSON com CSRF e mesma origem; 200 => passe com validade', async () => {
    const fetchImpl = vi.fn(async () => res(200, { ok: true, required: true, expires_in: 1800 }));
    const r = await sendLocation('/e/x/location?t=a', { latitude: 1, longitude: 2, accuracy: 3, timestamp: 4 }, { fetchImpl, csrfToken: 'tok' });
    expect(r).toEqual({ ok: true, required: true, expiresIn: 1800 });
    const [url, init] = fetchImpl.mock.calls[0];
    expect(url).toBe('/e/x/location?t=a');
    expect(init.method).toBe('POST');
    expect(init.credentials).toBe('same-origin');
    expect(init.headers['X-CSRF-TOKEN']).toBe('tok');
    expect(JSON.parse(init.body)).toEqual({ latitude: 1, longitude: 2, accuracy: 3, timestamp: 4 });
  });

  it('mapeia recusas e falhas', async () => {
    const run = (status, body) => sendLocation('/l', {}, { fetchImpl: async () => res(status, body) });
    expect(await run(422, { ok: false, code: 'outside', distance_m: 340 })).toEqual({ ok: false, code: 'outside', distance: 340 });
    expect(await run(422, { ok: false, code: 'inaccurate' })).toEqual({ ok: false, code: 'inaccurate', distance: null });
    expect((await run(410, {})).code).toBe('ended');
    expect((await run(429, {})).code).toBe('rate');
    expect((await run(419, {})).code).toBe('session');
    expect((await run(500, null)).code).toBe('server');
    expect(await run(200, { ok: true, required: false })).toEqual({ ok: true, required: false, expiresIn: 0 });
    expect((await sendLocation('/l', {}, { fetchImpl: async () => { throw new TypeError('offline'); } })).code).toBe('network');
  });

  it('permissao negada nao chama o servidor', async () => {
    const fetchImpl = vi.fn();
    const r = await checkLocation('/l', { fetchImpl, getPositionImpl: async () => { throw { code: 1 }; } });
    expect(r).toEqual({ ok: false, code: 'denied' });
    expect(fetchImpl).not.toHaveBeenCalled();
  });

  it('passValid com margem de 30 s', () => {
    expect(passValid(100000, 0)).toBe(true);
    expect(passValid(20000, 0)).toBe(false);
    expect(passValid(0, 0)).toBe(false);
  });
});

describe('upload sem passe de local', () => {
  it('403 location_required vira kind "location" (sem retry)', async () => {
    expect(errorKind(403, { code: 'location_required' })).toBe('location');
    expect(errorKind(403, {})).toBe('session');
    const send = vi.fn(async () => ({ status: 403, body: { code: 'location_required', message: 'x' } }));
    const err = await uploadPhoto('/u', { photo: new Blob(['a']), thumb: new Blob(['b']), orientation: 'portrait' }, { send }).catch((e) => e);
    expect(err.kind).toBe('location');
    expect(send).toHaveBeenCalledTimes(1);
  });
});

describe('guestApp com geofence', () => {
  beforeEach(() => { globalThis.window = { PHOTO_PARTY_I18N: pt }; });
  afterEach(() => { delete globalThis.window; });

  const make = (deps = {}, location = { url: '/e/x/location?t=a' }) => {
    const app = guestApp({ name: 'F', location }, { now: () => 1000000, ...deps });
    app.$watch = () => {};
    app.$nextTick = (fn) => fn?.();
    return app;
  };

  it('normaliza o bloco location (so mesma origem)', () => {
    expect(normalizeLocation({ url: '/e/x/location?t=a', passExpiresIn: 60 })).toEqual({ url: '/e/x/location?t=a', passExpiresIn: 60 });
    expect(normalizeLocation({ url: 'https://evil.example/l' })).toBeNull();
    expect(normalizeLocation(null)).toBeNull();
    expect(normalizeEventConfig({}).location).toBeNull();
  });

  it('sem geofence o fluxo nao muda', () => {
    const app = make({}, null);
    expect(app.flow.slice(0, 2)).toEqual(['welcome', 'source']);
    app.consent = true;
    app.accept();
    expect(app.step).toBe('source');
    expect(app.consentText).not.toContain(pt.consent_geo);
  });

  it('com geofence: aviso menciona a localizacao e o passo vem antes da camera', () => {
    const app = make();
    expect(app.consentText).toContain(pt.consent_geo);
    expect(app.flow.slice(0, 3)).toEqual(['welcome', 'location', 'source']);
    app.consent = true;
    app.accept();
    expect(app.step).toBe('location');
    expect(app.showLocAllow).toBe(true);
    expect(app.canGoBack).toBe(false);
  });

  it('passe ainda valido (recarregou a pagina) pula o passo', () => {
    const app = make({}, { url: '/l', passExpiresIn: 600 });
    app.consent = true;
    app.accept();
    expect(app.step).toBe('source');
  });

  it('dentro do raio segue para a camera', async () => {
    const fetchImpl = vi.fn(async () => res(200, { ok: true, required: true, expires_in: 1800 }));
    const app = make({ fetch: fetchImpl, getPosition: async () => position() });
    app.step = 'location';
    await app.requestLocation();
    expect(app.step).toBe('source');
    expect(app.passExpiresAt).toBe(1000000 + 1800 * 1000);
  });

  it('permissao negada mostra instrucoes e "tentar de novo"', async () => {
    const app = make({ fetch: vi.fn(), getPosition: async () => { throw { code: 1 }; } });
    app.step = 'location';
    await app.requestLocation();
    expect(app.step).toBe('location');
    expect(app.showLocRetry).toBe(true);
    expect(app.showLocHelp).toBe(true);
    expect(app.locMessage).toBe(pt.geo_denied);
  });

  it('timeout mostra a mensagem propria, sem instrucoes de permissao', async () => {
    const app = make({ fetch: vi.fn(), getPosition: async () => { throw { code: 3 }; } });
    app.step = 'location';
    await app.requestLocation();
    expect(app.locMessage).toBe(pt.geo_timeout);
    expect(app.showLocHelp).toBe(false);
  });

  it('fora do raio fica no passo com a distancia', async () => {
    const app = make({ fetch: async () => res(422, { ok: false, code: 'outside', distance_m: 450 }), getPosition: async () => position() });
    app.step = 'location';
    await app.requestLocation();
    expect(app.step).toBe('location');
    expect(app.locMessage).toContain('450 m');
  });

  it('servidor deixou de exigir (conta perdeu o recurso): segue e nao pede mais', async () => {
    const app = make({ fetch: async () => res(200, { ok: true, required: false }), getPosition: async () => position() });
    app.step = 'location';
    await app.requestLocation();
    expect(app.step).toBe('source');
    expect(app.locationRequired).toBe(false);
    expect(app.needsLocationCheck).toBe(false);
  });

  it('passe expirado no envio: volta ao passo e depois retorna a previa', async () => {
    const app = make({ fetch: async () => res(200, { ok: true, required: true, expires_in: 1800 }), getPosition: async () => position() });
    app.askLocation('preview', true);
    expect(app.step).toBe('location');
    expect(app.locMessage).toBe(pt.geo_expired);
    await app.requestLocation();
    expect(app.step).toBe('preview');
  });
});
