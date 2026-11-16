import { describe, expect, it } from 'vitest';
import { PhotoQueue } from '../../resources/js/screen/queue.js';
import { ScreenSync } from '../../resources/js/screen/sync.js';

const p = (uuid) => ({ uuid, url: `/u/${uuid}?v=1`, orientation: 'portrait' });

function server(pages) {
  const calls = [];
  const fetchPage = async (cursor) => {
    calls.push(cursor);
    const next = pages.shift();
    if (next instanceof Error) throw next;
    return next;
  };
  return { fetchPage, calls };
}

const make = (pages) => {
  const { fetchPage, calls } = server(pages);
  const queue = new PhotoQueue();
  const sync = new ScreenSync({ fetchPage, queue, now: () => 1000 });
  return { sync, queue, calls };
};

describe('ScreenSync.poll', () => {
  it('carga inicial entra no rodízio (não é "nova") e guarda o cursor', async () => {
    const { sync, queue } = make([{ status: 'live', photos: [p('a'), p('b')], removed: [], cursor: 'c2', has_more: false, ttl: 600 }]);
    await sync.poll();

    expect(queue.freshCount()).toBe(0);
    expect(queue.size()).toBe(2);
    expect(sync.cursor).toBe('c2');
    expect(queue.items.get('a').expiresAt).toBe(1000 + 600_000);
  });

  it('as fotos seguintes entram como novas e usam o cursor anterior', async () => {
    const { sync, queue, calls } = make([
      { status: 'live', photos: [p('a')], removed: [], cursor: 'c1', has_more: false, ttl: 600 },
      { status: 'live', photos: [p('b')], removed: [], cursor: 'c2', has_more: false, ttl: 600 },
    ]);
    await sync.poll();
    await sync.poll();

    expect(calls).toEqual([null, 'c1']);
    expect(queue.freshCount()).toBe(1);
    expect(queue.next().uuid).toBe('b');
  });

  it('segue as páginas enquanto has_more é verdadeiro', async () => {
    const { sync, queue, calls } = make([
      { photos: [p('a')], removed: [], cursor: 'c1', has_more: true, ttl: 600 },
      { photos: [p('b')], removed: [], cursor: 'c2', has_more: false, ttl: 600 },
    ]);
    await sync.poll();

    expect(calls).toEqual([null, 'c1']);
    expect(queue.size()).toBe(2);
    expect(queue.freshCount()).toBe(0); // tudo ainda é carga inicial
  });

  it('aplica removidos e mantém o cursor quando nada mudou', async () => {
    const { sync, queue } = make([
      { photos: [p('a'), p('b')], removed: [], cursor: 'c1', has_more: false, ttl: 600 },
      { photos: [], removed: ['a', 'desconhecida'], cursor: 'c1', has_more: false, ttl: 600 },
    ]);
    await sync.poll();
    await sync.poll();

    expect(queue.has('a')).toBe(false);
    expect(queue.size()).toBe(1);
    expect(sync.cursor).toBe('c1');
  });

  it('se a rede falhar, o cursor não anda e a fila fica intacta', async () => {
    const { sync, queue, calls } = make([
      { photos: [p('a')], removed: [], cursor: 'c1', has_more: false, ttl: 600 },
      new Error('offline'),
      { photos: [p('b')], removed: [], cursor: 'c2', has_more: false, ttl: 600 },
    ]);
    await sync.poll();
    await expect(sync.poll()).rejects.toThrow('offline');
    expect(queue.size()).toBe(1);
    await sync.poll();

    expect(calls).toEqual([null, 'c1', 'c1']);
    expect(queue.size()).toBe(2);
  });
});

describe('ScreenSync.resync', () => {
  it('refaz a busca: renova URLs, mantém a posição e descarta o que saiu', async () => {
    const renewed = { ...p('a'), url: '/u/a?v=2' };
    const { sync, queue, calls } = make([
      { photos: [p('a'), p('b'), p('c')], removed: [], cursor: 'c3', has_more: false, ttl: 600 },
      { photos: [renewed, p('c')], removed: [], cursor: 'c3', has_more: false, ttl: 600 },
    ]);
    await sync.poll();
    await sync.resync();

    expect(calls).toEqual([null, null]);
    expect(queue.has('b')).toBe(false);
    expect(queue.items.get('a').url).toBe('/u/a?v=2');
    expect(queue.next().uuid).toBe('a');
    expect(sync.cursor).toBe('c3');
  });

  it('não descarta nada se a busca ficou incompleta', async () => {
    const queue = new PhotoQueue();
    queue.add([p('a')]);
    const sync = new ScreenSync({ fetchPage: async () => ({ photos: [], removed: [], cursor: 'x', has_more: true, ttl: 600 }), queue, now: () => 0 });

    const result = await sync.resync();
    expect(result.complete).toBe(false);
    expect(queue.has('a')).toBe(true);
  });

  it('serve fotos de várias páginas', async () => {
    const { sync, queue } = make([
      { photos: [p('a')], removed: [], cursor: 'c1', has_more: false, ttl: 600 },
      { photos: [p('a')], removed: [], cursor: 'c1', has_more: true, ttl: 600 },
      { photos: [p('z')], removed: [], cursor: 'c9', has_more: false, ttl: 600 },
    ]);
    await sync.poll();
    await sync.resync();

    expect(queue.size()).toBe(2);
    expect(sync.cursor).toBe('c9');
  });
});
