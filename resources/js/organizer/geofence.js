// Formulario do evento: botao "Usar minha localizacao atual" (Geolocation API do navegador do
// organizador). Sem servico de mapas: so preenche latitude/longitude (6 casas) e mostra a precisao.
// O servidor valida e tambem interpreta o link/coordenadas colados.

/** Coordenada com 6 casas (~11 cm), como o servidor grava. */
export function formatCoord(value) {
  const n = Number(value);
  return Number.isFinite(n) ? n.toFixed(6) : '';
}

/** Chave do data-* com a mensagem para cada erro da Geolocation API. */
export function organizerGeoErrorKey(err) {
  if (err?.code === 1) return 'denied';
  return 'failed';
}

export function geofenceForm() {
  return {
    busy: false,
    message: '',
    locate() {
      const data = this.$root.dataset;
      const geo = typeof navigator !== 'undefined' ? navigator.geolocation : null;
      if (!geo) {
        this.message = data.unsupported;
        return;
      }
      this.busy = true;
      this.message = data.locating;
      geo.getCurrentPosition((pos) => {
        this.busy = false;
        this.$refs.lat.value = formatCoord(pos.coords.latitude);
        this.$refs.lng.value = formatCoord(pos.coords.longitude);
        // o campo colado tem prioridade no servidor: limpa para valer a posicao atual
        if (this.$refs.paste) this.$refs.paste.value = '';
        this.message = data.located.split(':accuracy').join(String(Math.round(pos.coords.accuracy)));
      }, (err) => {
        this.busy = false;
        this.message = data[organizerGeoErrorKey(err)];
      }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    },
  };
}
