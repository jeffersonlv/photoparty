import { describe, expect, it } from 'vitest';
import { mergePhotos, nextIndex, parseDemoConfig, ROTATE_MS } from '../../resources/js/home/demo.js';

const photo = (uuid, url = `/media/signed/${uuid}/thumb?sig=1`) => ({ uuid, url, orientation: 'portrait' });

describe('parseDemoConfig', () => {
  it('le o bloco JSON com limites seguros', () => {
    expect(parseDemoConfig('{"pollUrl":"/demo/photos","pollMs":5000,"max":8,"alt":"Foto"}')).toEqual({
      pollUrl: '/demo/photos', pollMs: 5000, max: 8, alt: 'Foto',
    });
    expect(parseDemoConfig('{"pollUrl":"/x","pollMs":1,"max":999}')).toMatchObject({ pollMs: 2000, max: 20 });
  });

  it('recusa URL externa e JSON invalido', () => {
    expect(parseDemoConfig('{"pollUrl":"https://evil.test/p"}').pollUrl).toBe('');
    expect(parseDemoConfig('{"pollUrl":"//evil.test/p"}').pollUrl).toBe('');
    expect(parseDemoConfig('nao e json')).toEqual({ pollUrl: '', pollMs: 5000, max: 8, alt: '' });
  });
});

describe('mergePhotos', () => {
  it('mantem a URL das fotos ja conhecidas e informa novas e removidas', () => {
    const current = [photo('a', '/media/signed/a/thumb?sig=old'), photo('b')];
    const { list, added, removed } = mergePhotos(current, [photo('c'), photo('a', '/media/signed/a/thumb?sig=new')], 8);

    expect(list.map((p) => p.uuid)).toEqual(['c', 'a']);
    expect(list[1].url).toBe('/media/signed/a/thumb?sig=old');
    expect(added).toEqual(['c']);
    expect(removed).toEqual(['b']);
  });

  it('limita ao maximo, ignora duplicadas e entradas invalidas ou de outra origem', () => {
    const incoming = [photo('1'), photo('1'), null, { uuid: 2 }, photo('x', 'https://evil.test/a.jpg'), photo('y', '//evil.test/a.jpg'), photo('3'), photo('4')];
    const { list } = mergePhotos([], incoming, 2);

    expect(list.map((p) => p.uuid)).toEqual(['1', '3']);
  });

  it('lista vazia ou ausente limpa a tela', () => {
    expect(mergePhotos([photo('a')], undefined).removed).toEqual(['a']);
    expect(mergePhotos([], []).list).toEqual([]);
  });
});

describe('carrossel', () => {
  it('avanca em ciclo e tolera lista vazia', () => {
    expect(nextIndex(0, 3)).toBe(1);
    expect(nextIndex(2, 3)).toBe(0);
    expect(nextIndex(0, 0)).toBe(0);
    expect(ROTATE_MS).toBeGreaterThanOrEqual(2000);
  });
});
