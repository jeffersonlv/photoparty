import { describe, expect, it } from 'vitest';
import { msUntilFreshSwitch, PhotoQueue } from '../../resources/js/screen/queue.js';

const photo = (uuid, expiresAt = 1_000_000) => ({ uuid, url: `/u/${uuid}`, expiresAt });
const drain = (q, n, canUse) => Array.from({ length: n }, () => q.next(canUse)?.uuid ?? null);

describe('PhotoQueue rodízio', () => {
  it('gira em círculo pela carga inicial', () => {
    const q = new PhotoQueue();
    q.add([photo('a'), photo('b'), photo('c')]);
    expect(drain(q, 7)).toEqual(['a', 'b', 'c', 'a', 'b', 'c', 'a']);
  });

  it('devolve null com a fila vazia', () => {
    expect(new PhotoQueue().next()).toBeNull();
  });

  it('não duplica uuid repetido e só atualiza URL/validade', () => {
    const q = new PhotoQueue();
    q.add([photo('a', 100)]);
    q.add([{ uuid: 'a', url: '/novo', expiresAt: 900 }], { fresh: true });
    expect(q.size()).toBe(1);
    expect(q.freshCount()).toBe(0);
    expect(q.next()).toMatchObject({ uuid: 'a', url: '/novo', expiresAt: 900 });
  });
});

describe('prioridade às novas', () => {
  it('mostra as novas antes do rodízio e depois as coloca como últimas da volta', () => {
    const q = new PhotoQueue();
    q.add([photo('a'), photo('b'), photo('c')]);
    expect(drain(q, 1)).toEqual(['a']); // próxima do rodízio: b

    q.add([photo('n1')], { fresh: true });
    expect(q.freshCount()).toBe(1);
    expect(drain(q, 5)).toEqual(['n1', 'b', 'c', 'a', 'n1']);
  });

  it('várias novas saem em ordem de chegada, todas antes do rodízio', () => {
    const q = new PhotoQueue();
    q.add([photo('a'), photo('b')]);
    q.add([photo('n1'), photo('n2')], { fresh: true });
    expect(drain(q, 4)).toEqual(['n1', 'n2', 'a', 'b']);
  });

  it('com a fila vazia, a nova vira a única do rodízio', () => {
    const q = new PhotoQueue();
    q.add([photo('n')], { fresh: true });
    expect(drain(q, 3)).toEqual(['n', 'n', 'n']);
  });

  it('a foto atual fica no mínimo 2,5 s antes de ceder para a nova', () => {
    expect(msUntilFreshSwitch(0)).toBe(2500);
    expect(msUntilFreshSwitch(1000)).toBe(1500);
    expect(msUntilFreshSwitch(9000)).toBe(0);
  });
});

describe('remoção', () => {
  it('tira da fila e do rodízio sem pular nem repetir as outras', () => {
    const q = new PhotoQueue();
    q.add([photo('a'), photo('b'), photo('c'), photo('d')]);
    expect(drain(q, 2)).toEqual(['a', 'b']); // próxima: c

    q.remove(['a']); // antes do ponteiro
    expect(drain(q, 3)).toEqual(['c', 'd', 'b']);
  });

  it('remover a próxima da vez passa para a seguinte', () => {
    const q = new PhotoQueue();
    q.add([photo('a'), photo('b'), photo('c')]);
    drain(q, 1); // próxima: b
    q.remove(['b']);
    expect(drain(q, 3)).toEqual(['c', 'a', 'c']);
  });

  it('remove também uma nova que ainda não foi exibida', () => {
    const q = new PhotoQueue();
    q.add([photo('a')]);
    q.add([photo('n')], { fresh: true });
    q.remove(['n']);
    expect(q.freshCount()).toBe(0);
    expect(drain(q, 2)).toEqual(['a', 'a']);
  });

  it('ignora uuid desconhecido e esvazia sem quebrar', () => {
    const q = new PhotoQueue();
    q.add([photo('a')]);
    q.remove(['x', 'a']);
    expect(q.size()).toBe(0);
    expect(q.next()).toBeNull();
  });

  it('retainOnly mantém só o conjunto informado', () => {
    const q = new PhotoQueue();
    q.add([photo('a'), photo('b'), photo('c')]);
    q.retainOnly(new Set(['b']));
    expect(drain(q, 2)).toEqual(['b', 'b']);
  });
});

describe('URLs assinadas', () => {
  it('pede renovação quando alguma URL entra na margem antes de expirar', () => {
    const q = new PhotoQueue({ renewMarginMs: 90_000 });
    q.add([photo('a', 600_000), photo('b', 700_000)]);

    expect(q.needsRenewal(0)).toBe(false);
    expect(q.needsRenewal(509_999)).toBe(false);
    expect(q.needsRenewal(510_000)).toBe(true); // 600_000 - 90_000
  });

  it('depois de renovar (add com novo expiresAt) deixa de pedir', () => {
    const q = new PhotoQueue({ renewMarginMs: 90_000 });
    q.add([photo('a', 600_000)]);
    expect(q.needsRenewal(550_000)).toBe(true);

    q.add([photo('a', 1_150_000)]);
    expect(q.needsRenewal(550_000)).toBe(false);
  });

  it('fila vazia nunca pede renovação', () => {
    expect(new PhotoQueue().needsRenewal(Number.MAX_SAFE_INTEGER)).toBe(false);
  });

  it('pula fotos que não podem ser exibidas (URL vencida e fora do cache)', () => {
    const q = new PhotoQueue();
    q.add([photo('a', 50), photo('b', 5000), photo('c', 50)]);
    const now = 1000;
    const canUse = (item) => item.expiresAt > now;

    expect(drain(q, 3, canUse)).toEqual(['b', 'b', 'b']);
  });

  it('devolve null se nenhuma pode ser exibida e não perde as fotos', () => {
    const q = new PhotoQueue();
    q.add([photo('a', 50)]);
    expect(q.next(() => false)).toBeNull();
    expect(q.size()).toBe(1);
    expect(q.next()).toMatchObject({ uuid: 'a' });
  });

  it('peek mostra quem seria a próxima sem consumir', () => {
    const q = new PhotoQueue();
    q.add([photo('a'), photo('b')]);
    q.add([photo('n')], { fresh: true });
    expect(q.peek().uuid).toBe('n');
    expect(q.peek().uuid).toBe('n');
    expect(q.next().uuid).toBe('n');
    expect(q.peek().uuid).toBe('a');
  });
});
