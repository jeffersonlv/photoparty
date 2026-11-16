import { describe, expect, it, vi } from 'vitest';
import {
  backoffDelay, buildFormData, classifyStatus, firstValidationMessage, uploadPhoto, UploadError,
} from '../../resources/js/guest/upload.js';

const payload = () => ({
  photo: new Blob(['a'], { type: 'image/jpeg' }),
  thumb: new Blob(['b'], { type: 'image/jpeg' }),
  frameKey: 'led_billboard',
  filter: 'bw',
  orientation: 'portrait',
  guestName: 'Ana',
  guestTexts: { guest_name: 'ANA' },
  csrfToken: 'tok',
});
const noSleep = vi.fn(async () => {});

describe('backoffDelay', () => {
  it('dobra a cada tentativa e respeita o teto', () => {
    expect(backoffDelay(1)).toBe(800);
    expect(backoffDelay(2)).toBe(1600);
    expect(backoffDelay(3)).toBe(3200);
    expect(backoffDelay(10)).toBe(8000);
  });
  it('jitter soma ate a fracao informada', () => {
    expect(backoffDelay(1, { jitter: 0.5, rand: () => 1 })).toBe(1200);
    expect(backoffDelay(1, { jitter: 0.5, rand: () => 0 })).toBe(800);
  });
});

describe('classifyStatus', () => {
  it('mapeia os status do backend', () => {
    expect(classifyStatus(201).ok).toBe(true);
    expect(classifyStatus(410)).toMatchObject({ kind: 'ended', retry: false });
    expect(classifyStatus(429)).toMatchObject({ kind: 'rate', retry: false });
    expect(classifyStatus(422)).toMatchObject({ kind: 'invalid', retry: false });
    expect(classifyStatus(419)).toMatchObject({ kind: 'session', retry: false });
    expect(classifyStatus(503)).toMatchObject({ kind: 'server', retry: true });
    expect(classifyStatus(0)).toMatchObject({ kind: 'network', retry: true });
    expect(classifyStatus(404)).toMatchObject({ kind: 'server', retry: false });
  });
});

describe('buildFormData', () => {
  it('envia todos os campos do contrato', () => {
    const fd = buildFormData(payload());
    expect(fd.get('photo').name).toBe('photo.jpg');
    expect(fd.get('thumb').name).toBe('thumb.jpg');
    expect(fd.get('frame_key')).toBe('led_billboard');
    expect(fd.get('filter')).toBe('bw');
    expect(fd.get('orientation')).toBe('portrait');
    expect(fd.get('guest_name')).toBe('Ana');
    expect(JSON.parse(fd.get('guest_texts'))).toEqual({ guest_name: 'ANA' });
    expect(fd.get('_token')).toBe('tok');
  });
  it('sem moldura envia frame_key vazio e sem token omite _token', () => {
    const fd = buildFormData({ ...payload(), frameKey: '', csrfToken: null });
    expect(fd.get('frame_key')).toBe('');
    expect(fd.has('_token')).toBe(false);
  });
});

describe('firstValidationMessage', () => {
  it('le o formato 422 do Laravel', () => {
    expect(firstValidationMessage({ errors: { photo: ['Muito grande'] } })).toBe('Muito grande');
    expect(firstValidationMessage({ message: 'Oops' })).toBe('Oops');
    expect(firstValidationMessage(null)).toBeNull();
  });
});

describe('uploadPhoto (retry)', () => {
  it('sucesso na primeira tentativa', async () => {
    const send = vi.fn(async () => ({ status: 201, body: { id: 1 } }));
    const onProgress = vi.fn();
    await expect(uploadPhoto('/u', payload(), { send, sleep: noSleep, onProgress })).resolves.toEqual({ id: 1 });
    expect(send).toHaveBeenCalledTimes(1);
    expect(onProgress).toHaveBeenLastCalledWith(1);
  });

  it('tenta de novo em 5xx/rede com backoff e se recupera', async () => {
    const send = vi.fn()
      .mockResolvedValueOnce({ status: 0, body: null })
      .mockResolvedValueOnce({ status: 503, body: null })
      .mockResolvedValueOnce({ status: 201, body: { ok: true } });
    const sleep = vi.fn(async () => {});
    const onRetry = vi.fn();
    await expect(uploadPhoto('/u', payload(), { send, sleep, onRetry })).resolves.toEqual({ ok: true });
    expect(send).toHaveBeenCalledTimes(3);
    expect(sleep).toHaveBeenCalledTimes(2);
    expect(sleep.mock.calls[0][0]).toBeGreaterThanOrEqual(800);
    expect(sleep.mock.calls[1][0]).toBeGreaterThanOrEqual(1600);
    expect(onRetry).toHaveBeenCalledTimes(2);
  });

  it('desiste apos 3 tentativas', async () => {
    const send = vi.fn(async () => ({ status: 500, body: null }));
    await expect(uploadPhoto('/u', payload(), { send, sleep: noSleep })).rejects.toMatchObject({ kind: 'server', status: 500 });
    expect(send).toHaveBeenCalledTimes(3);
  });

  it('410 = evento encerrado, sem retry', async () => {
    const send = vi.fn(async () => ({ status: 410, body: null }));
    const err = await uploadPhoto('/u', payload(), { send, sleep: noSleep }).catch((e) => e);
    expect(err).toBeInstanceOf(UploadError);
    expect(err.kind).toBe('ended');
    expect(send).toHaveBeenCalledTimes(1);
  });

  it('429 = limite, sem retry, repassa Retry-After', async () => {
    const send = vi.fn(async () => ({ status: 429, body: null, retryAfter: 30 }));
    const err = await uploadPhoto('/u', payload(), { send, sleep: noSleep }).catch((e) => e);
    expect(err).toMatchObject({ kind: 'rate', retryAfter: 30 });
    expect(send).toHaveBeenCalledTimes(1);
  });

  it('422 = validacao, sem retry, com a mensagem do servidor', async () => {
    const send = vi.fn(async () => ({ status: 422, body: { errors: { photo: ['Imagem invalida'] } } }));
    const err = await uploadPhoto('/u', payload(), { send, sleep: noSleep }).catch((e) => e);
    expect(err).toMatchObject({ kind: 'invalid', message: 'Imagem invalida' });
    expect(send).toHaveBeenCalledTimes(1);
  });
});
