// Sincronização com o servidor (lógica pura; o fetch é injetado). Testada em tests/js/screen-sync.test.js.
//
// poll():   busca as fotos novas a partir do cursor (páginas de 50) e aplica os removidos.
// resync(): refaz a busca desde o início (renova as URLs assinadas e descarta o que saiu do ar).

const MAX_PAGES = 40;

export class StoppedError extends Error {
  constructor(message = 'screen stopped') {
    super(message);
    this.name = 'StoppedError';
  }
}

export class ScreenSync {
  /**
   * @param {{ fetchPage: (cursor: string|null) => Promise<object>, queue: import('./queue.js').PhotoQueue, now?: () => number }} deps
   */
  constructor({ fetchPage, queue, now = () => Date.now() }) {
    this.fetchPage = fetchPage;
    this.queue = queue;
    this.now = now;
    this.cursor = null;
    this.loaded = false; // false até terminar a carga inicial: o que chega antes não é "novo"
    this.status = null;
  }

  async poll() {
    let pages = 0;

    for (;;) {
      const res = await this.fetchPage(this.cursor);
      this.#apply(res, { fresh: this.loaded });

      if (res.cursor) this.cursor = res.cursor;
      pages += 1;

      if (!res.has_more) {
        this.loaded = true;
        break;
      }

      if (pages >= MAX_PAGES) break; // continua no próximo ciclo, do cursor
    }

    return { status: this.status, pages };
  }

  async resync() {
    const seen = new Set();
    let cursor = null;
    let pages = 0;
    let complete = false;

    for (;;) {
      const res = await this.fetchPage(cursor);
      this.#apply(res, { fresh: this.loaded }, seen);

      if (res.cursor) cursor = res.cursor;
      pages += 1;

      if (!res.has_more) {
        complete = true;
        break;
      }

      if (pages >= MAX_PAGES) break;
    }

    if (complete) {
      this.queue.retainOnly(seen);
      this.cursor = cursor;
      this.loaded = true;
    }

    return { status: this.status, pages, complete };
  }

  #apply(res, { fresh }, seen = null) {
    if (res.status) this.status = res.status;

    this.queue.remove(res.removed ?? []);

    const ttlMs = (res.ttl ?? 600) * 1000;
    const expiresAt = this.now() + ttlMs;
    const photos = (res.photos ?? []).map((p) => ({
      uuid: p.uuid,
      url: p.url,
      orientation: p.orientation ?? null,
      expiresAt,
    }));

    if (seen) for (const p of photos) seen.add(p.uuid);

    this.queue.add(photos, { fresh });
  }
}
