// Cache de imagens do telão em memória (blob URLs), com limite (LRU).
// Foto já exibida continua disponível mesmo se a rede cair ou a URL assinada expirar.

export class ImageCache {
  constructor({ max = 40, fetchImpl = (url) => fetch(url, { credentials: 'omit' }) } = {}) {
    this.max = max;
    this.fetchImpl = fetchImpl;
    this.map = new Map(); // uuid -> blob URL (ordem = uso mais antigo primeiro)
    this.pending = new Map(); // uuid -> Promise
  }

  has(uuid) {
    return this.map.has(uuid);
  }

  async get(item) {
    const cached = this.map.get(item.uuid);

    if (cached) {
      this.map.delete(item.uuid);
      this.map.set(item.uuid, cached);
      return cached;
    }

    if (!this.pending.has(item.uuid)) {
      const job = this.#download(item).finally(() => this.pending.delete(item.uuid));
      this.pending.set(item.uuid, job);
    }

    return this.pending.get(item.uuid);
  }

  drop(uuid) {
    const url = this.map.get(uuid);
    if (url) URL.revokeObjectURL(url);
    this.map.delete(uuid);
  }

  async #download(item) {
    const res = await this.fetchImpl(item.url);
    if (!res.ok) throw new Error(`image ${res.status}`);

    const url = URL.createObjectURL(await res.blob());
    this.map.set(item.uuid, url);

    while (this.map.size > this.max) {
      const oldest = this.map.keys().next().value;
      this.drop(oldest);
    }

    return url;
  }
}
