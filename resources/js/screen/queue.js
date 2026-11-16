// Fila local do telão (lógica pura, sem DOM). Testada em tests/js/screen-queue.test.js.
//
// - "fresh": fotos recém-chegadas, mostradas antes do rodízio normal (prioridade às novas).
// - "ring": rodízio circular das demais; a foto que acabou de ser mostrada vira a última da volta.
// - Cada item guarda quando a URL assinada expira, para renová-la antes (needsRenewal).

export const MIN_DWELL_MS = 2500;

/**
 * Quanto tempo falta, no máximo, para trocar a foto atual quando chega uma nova:
 * a foto atual fica no mínimo MIN_DWELL_MS na tela.
 */
export function msUntilFreshSwitch(shownMs, minDwellMs = MIN_DWELL_MS) {
  return Math.max(0, minDwellMs - shownMs);
}

export class PhotoQueue {
  constructor({ renewMarginMs = 90_000 } = {}) {
    this.renewMarginMs = renewMarginMs;
    this.items = new Map(); // uuid -> { uuid, url, expiresAt, orientation }
    this.fresh = []; // uuids
    this.ring = []; // uuids
    this.idx = 0; // posição da próxima foto do rodízio
  }

  size() {
    return this.items.size;
  }

  has(uuid) {
    return this.items.has(uuid);
  }

  freshCount() {
    return this.fresh.length;
  }

  /**
   * Adiciona fotos. Uuid já conhecido só atualiza URL/validade (mantém a posição).
   * Uuid novo entra como "fresh" (prioridade) ou direto no rodízio (carga inicial).
   */
  add(photos, { fresh = false } = {}) {
    for (const photo of photos) {
      if (!photo || !photo.uuid) continue;

      const known = this.items.get(photo.uuid);

      if (known) {
        known.url = photo.url;
        known.expiresAt = photo.expiresAt;
        continue;
      }

      this.items.set(photo.uuid, {
        uuid: photo.uuid,
        url: photo.url,
        expiresAt: photo.expiresAt,
        orientation: photo.orientation ?? null,
      });

      if (fresh) this.fresh.push(photo.uuid);
      else this.ring.push(photo.uuid);
    }
  }

  remove(uuids) {
    for (const uuid of uuids) {
      if (!this.items.delete(uuid)) continue;

      const f = this.fresh.indexOf(uuid);
      if (f !== -1) this.fresh.splice(f, 1);

      const r = this.ring.indexOf(uuid);
      if (r !== -1) {
        this.ring.splice(r, 1);
        if (r < this.idx) this.idx -= 1;
      }
    }

    if (this.idx >= this.ring.length) this.idx = 0;
  }

  /** Mantém só os uuids do conjunto (usado após refazer a busca completa). */
  retainOnly(keep) {
    const drop = [];
    for (const uuid of this.items.keys()) {
      if (!keep.has(uuid)) drop.push(uuid);
    }
    this.remove(drop);
  }

  /**
   * Próxima foto a exibir (consome). canUse(item) diz se dá para exibir agora
   * (ex.: imagem em cache ou URL ainda válida); itens inutilizáveis são pulados.
   */
  next(canUse = () => true) {
    const f = this.fresh.findIndex((uuid) => canUse(this.items.get(uuid)));

    if (f !== -1) {
      const [uuid] = this.fresh.splice(f, 1);
      // Entra no rodízio "como se tivesse acabado de ser mostrada".
      this.ring.splice(this.idx, 0, uuid);
      this.idx = (this.idx + 1) % this.ring.length;
      return this.items.get(uuid);
    }

    const n = this.ring.length;

    for (let k = 0; k < n; k += 1) {
      const pos = (this.idx + k) % n;
      const item = this.items.get(this.ring[pos]);

      if (canUse(item)) {
        this.idx = (pos + 1) % n;
        return item;
      }
    }

    return null;
  }

  /** Quem next() devolveria, sem consumir (para pré-carregar a imagem). */
  peek(canUse = () => true) {
    const fresh = this.fresh.map((uuid) => this.items.get(uuid)).find((item) => canUse(item));
    if (fresh) return fresh;

    const n = this.ring.length;

    for (let k = 0; k < n; k += 1) {
      const item = this.items.get(this.ring[(this.idx + k) % n]);
      if (canUse(item)) return item;
    }

    return null;
  }

  /** Alguma URL expira dentro da margem? Então é hora de refazer a busca. */
  needsRenewal(now) {
    for (const item of this.items.values()) {
      if (item.expiresAt - this.renewMarginMs <= now) return true;
    }

    return false;
  }
}
