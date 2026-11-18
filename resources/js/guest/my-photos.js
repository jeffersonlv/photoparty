// "Minhas fotos" do convidado: lista (so deste aparelho), baixar e excluir.
// Logica pura e injetavel (fetch/confirm) para os testes; o componente Alpine so chama estas funcoes.

/** Status do servidor -> chave de traducao (lang/{pt_BR,en}/guest.php). */
export const STATUS_KEYS = {
  pending: 'mine_status_pending',
  approved: 'mine_status_approved',
  rejected: 'mine_status_rejected',
};

/** Classe CSS do selo de status. */
export function statusClass(status) {
  return STATUS_KEYS[status] ? `gp-badge gp-badge--${status}` : 'gp-badge';
}

export function statusKey(status) {
  return STATUS_KEYS[status] ?? 'mine_status_pending';
}

/** URL relativa da mesma origem (nunca segue URL absoluta vinda do servidor/terceiros). */
export function isSameOriginPath(url) {
  return typeof url === 'string' && url.startsWith('/') && !url.startsWith('//');
}

/** Normaliza o JSON de GET /e/{slug}/my-photos, descartando itens invalidos. */
export function normalizeMyPhotos(body) {
  const list = Array.isArray(body?.photos) ? body.photos : [];
  return list
    .filter((p) => p && typeof p.uuid === 'string' && STATUS_KEYS[p.status]
      && isSameOriginPath(p.thumbUrl) && isSameOriginPath(p.downloadUrl) && isSameOriginPath(p.deleteUrl))
    .map((p) => ({
      uuid: p.uuid,
      status: p.status,
      orientation: p.orientation === 'landscape' ? 'landscape' : 'portrait',
      thumbUrl: p.thumbUrl,
      downloadUrl: p.downloadUrl,
      deleteUrl: p.deleteUrl,
    }));
}

/** Remove uma foto da lista (imutavel). */
export function withoutPhoto(list, uuid) {
  return (list ?? []).filter((p) => p.uuid !== uuid);
}

/**
 * Busca a lista. Resolve {ok, photos, gone}: gone=true quando o servidor responde 404
 * (evento nao esta mais ao vivo: a lista some).
 */
export async function fetchMyPhotos(url, { fetchImpl = globalThis.fetch } = {}) {
  if (!isSameOriginPath(url)) return { ok: false, photos: [], gone: false };
  try {
    const res = await fetchImpl(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      cache: 'no-store',
    });
    if (res.status === 404) return { ok: false, photos: [], gone: true };
    if (!res.ok) return { ok: false, photos: [], gone: false };
    return { ok: true, photos: normalizeMyPhotos(await res.json()), gone: false };
  } catch {
    return { ok: false, photos: [], gone: false };
  }
}

/** Exclui uma foto. Resolve 'deleted' | 'gone' (404: ja nao existe/evento encerrado) | 'error'. */
export async function deleteMyPhoto(url, csrfToken, { fetchImpl = globalThis.fetch } = {}) {
  if (!isSameOriginPath(url)) return 'error';
  try {
    const res = await fetchImpl(url, {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      credentials: 'same-origin',
    });
    if (res.status === 204 || res.ok) return 'deleted';
    if (res.status === 404) return 'gone';
    return 'error';
  } catch {
    return 'error';
  }
}
