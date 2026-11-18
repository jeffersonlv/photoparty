// "Minhas fotos" e aviso de privacidade do convidado (logica pura).
import { describe, expect, it, vi } from 'vitest';
import { resolve } from 'node:path';
import { parsePhpLang } from '../../tools/guest-demo/scripts/lang.mjs';
import {
  deleteMyPhoto, fetchMyPhotos, isSameOriginPath, normalizeMyPhotos, statusClass, statusKey, STATUS_KEYS, withoutPhoto,
} from '../../resources/js/guest/my-photos.js';
import { consentStorageKey, guestApp, normalizeEventConfig, normalizePrivacy } from '../../resources/js/guest/guest-app.js';
import { buildFormData } from '../../resources/js/guest/upload.js';

const root = resolve(import.meta.dirname, '../..');
const pt = parsePhpLang(resolve(root, 'lang/pt_BR/guest.php'));
const en = parsePhpLang(resolve(root, 'lang/en/guest.php'));

const item = (over = {}) => ({
  uuid: 'u1', status: 'approved', orientation: 'portrait',
  thumbUrl: '/e/x/my-photos/u1/thumb?t=a', downloadUrl: '/e/x/my-photos/u1/download?t=a', deleteUrl: '/e/x/photos/u1?t=a',
  ...over,
});
const res = (status, body = null) => ({ status, ok: status >= 200 && status < 300, json: async () => body });

describe('my-photos: normalizacao', () => {
  it('aceita so itens validos com URLs da mesma origem', () => {
    const list = normalizeMyPhotos({
      photos: [
        item(),
        item({ uuid: 'u2', status: 'deleted' }),
        item({ uuid: 'u3', thumbUrl: 'https://evil.example/x.jpg' }),
        item({ uuid: 'u4', downloadUrl: '//evil.example/x' }),
        item({ uuid: 'u5', orientation: 'landscape', status: 'pending' }),
        null,
      ],
    });
    expect(list.map((p) => p.uuid)).toEqual(['u1', 'u5']);
    expect(list[1].orientation).toBe('landscape');
    expect(normalizeMyPhotos(null)).toEqual([]);
  });

  it('isSameOriginPath', () => {
    expect(isSameOriginPath('/a')).toBe(true);
    expect(isSameOriginPath('//a')).toBe(false);
    expect(isSameOriginPath('http://a')).toBe(false);
    expect(isSameOriginPath(null)).toBe(false);
  });

  it('status -> chave de traducao e classe; chaves existem nos dois idiomas', () => {
    expect(statusKey('approved')).toBe('mine_status_approved');
    expect(statusKey('weird')).toBe('mine_status_pending');
    expect(statusClass('rejected')).toBe('gp-badge gp-badge--rejected');
    expect(statusClass('x')).toBe('gp-badge');
    for (const key of Object.values(STATUS_KEYS)) {
      expect(pt[key], key).toBeTypeOf('string');
      expect(en[key], key).toBeTypeOf('string');
    }
  });

  it('withoutPhoto remove sem mutar', () => {
    const list = [item(), item({ uuid: 'u2' })];
    expect(withoutPhoto(list, 'u1').map((p) => p.uuid)).toEqual(['u2']);
    expect(list).toHaveLength(2);
  });
});

describe('my-photos: rede', () => {
  it('fetchMyPhotos: ok, 404 (evento nao esta mais ao vivo), erro e URL externa', async () => {
    const ok = vi.fn(async () => res(200, { photos: [item()] }));
    expect(await fetchMyPhotos('/e/x/my-photos?t=a', { fetchImpl: ok })).toEqual({ ok: true, photos: [item()], gone: false });
    expect(ok.mock.calls[0][1].credentials).toBe('same-origin');

    expect((await fetchMyPhotos('/a', { fetchImpl: async () => res(404) })).gone).toBe(true);
    expect((await fetchMyPhotos('/a', { fetchImpl: async () => res(500) })).ok).toBe(false);
    expect((await fetchMyPhotos('/a', { fetchImpl: async () => { throw new Error('net'); } })).ok).toBe(false);

    const never = vi.fn();
    expect((await fetchMyPhotos('https://evil.example', { fetchImpl: never })).ok).toBe(false);
    expect(never).not.toHaveBeenCalled();
  });

  it('deleteMyPhoto envia DELETE com CSRF e classifica a resposta', async () => {
    const f = vi.fn(async () => res(204));
    expect(await deleteMyPhoto('/e/x/photos/u1?t=a', 'tok', { fetchImpl: f })).toBe('deleted');
    expect(f.mock.calls[0][1].method).toBe('DELETE');
    expect(f.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('tok');
    expect(await deleteMyPhoto('/a', null, { fetchImpl: async () => res(404) })).toBe('gone');
    expect(await deleteMyPhoto('/a', null, { fetchImpl: async () => res(429) })).toBe('error');
    expect(await deleteMyPhoto('//evil', null, { fetchImpl: f })).toBe('error');
  });
});

describe('aviso de privacidade e consentimento', () => {
  it('normalizePrivacy descarta URLs externas e dias invalidos', () => {
    expect(normalizePrivacy({ controller: 'Ana', retentionDays: '30', policyUrl: '/privacidade', requestUrl: 'https://x' }))
      .toEqual({ controller: 'Ana', retentionDays: 30, policyUrl: '/privacidade', requestUrl: '' });
    expect(normalizePrivacy(undefined).retentionDays).toBe(0);
  });

  it('a chave do consentimento lembrado muda com a versao do texto', () => {
    const a = consentStorageKey(normalizeEventConfig({ name: 'Festa', consentVersion: '2026-11-18' }));
    const b = consentStorageKey(normalizeEventConfig({ name: 'Festa', consentVersion: '2027-01-01' }));
    expect(a).not.toBe(b);
  });

  it('buildFormData envia consent=1 e a versao', () => {
    const fd = buildFormData({ photo: new Blob(['a']), thumb: new Blob(['b']), orientation: 'portrait', consentVersion: '2026-11-18' });
    expect(fd.get('consent')).toBe('1');
    expect(fd.get('consent_version')).toBe('2026-11-18');
    expect(buildFormData({ photo: new Blob(['a']), thumb: new Blob(['b']), orientation: 'portrait' }).has('consent')).toBe(false);
  });

  it('consentText usa o controlador e o prazo de retencao', () => {
    globalThis.window = { PHOTO_PARTY_I18N: pt };
    const app = guestApp({ name: 'F', privacy: { controller: 'Studio X', retentionDays: 30 } });
    expect(app.consentText).toContain('Studio X');
    expect(app.consentText).toContain('30 dias');
    const anon = guestApp({ name: 'F' });
    expect(anon.consentText).toContain(pt.consent_controller_fallback);
    delete globalThis.window;
  });
});

describe('guestApp: Minhas fotos', () => {
  const make = (fetchImpl, confirm = () => true) => {
    const app = guestApp({ name: 'F', myPhotosUrl: '/e/x/my-photos?t=a' }, { fetch: fetchImpl, confirm });
    app.$watch = () => {};
    app.$nextTick = (fn) => fn?.();
    return app;
  };

  it('abre a partir das boas-vindas, carrega e volta', async () => {
    const app = make(async () => res(200, { photos: [item()] }));
    expect(app.showMineLink).toBe(true);
    await app.openMine();
    expect(app.step).toBe('mine');
    expect(app.mine).toHaveLength(1);
    expect(app.showMineLink).toBe(false);
    app.closeMine();
    expect(app.step).toBe('welcome');
  });

  it('evento encerrado (404): some a lista e mostra a tela de encerrado', async () => {
    const app = make(async () => res(404));
    await app.openMine();
    expect(app.mine).toEqual([]);
    expect(app.mineGone).toBe(true);
    expect(app.step).toBe('ended');
  });

  it('exclui so depois de confirmar', async () => {
    const calls = [];
    const fetchImpl = async (url, opts = {}) => { calls.push(opts.method ?? 'GET'); return opts.method === 'DELETE' ? res(204) : res(200, { photos: [item(), item({ uuid: 'u2' })] }); };
    const declined = make(fetchImpl, () => false);
    await declined.openMine();
    await declined.removeMine(declined.mine[0]);
    expect(declined.mine).toHaveLength(2);

    const app = make(fetchImpl);
    await app.openMine();
    await app.removeMine(app.mine[0]);
    expect(app.mine.map((p) => p.uuid)).toEqual(['u2']);
    expect(calls).toContain('DELETE');
  });

  it('erro ao excluir mantem a foto e mostra mensagem', async () => {
    const fetchImpl = async (url, opts = {}) => (opts.method === 'DELETE' ? res(500) : res(200, { photos: [item()] }));
    const app = make(fetchImpl);
    await app.openMine();
    await app.removeMine(app.mine[0]);
    expect(app.mine).toHaveLength(1);
    expect(app.mineError).not.toBe('');
  });
});
