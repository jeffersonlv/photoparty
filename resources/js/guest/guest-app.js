// Componente Alpine da pagina do convidado.
// Objetos "pesados" (canvas, stream, imagens) ficam em variaveis de closure e NAO no
// estado do Alpine: o proxy reativo quebra metodos nativos de canvas/MediaStream.

import {
  chooseOrientation, clampView, loadFrameConfig, loadOrientationAssets, plainOrientation,
  renderComposition, zoomAround, ORIENTATIONS,
} from './frame-engine.js';
import { guestTextFields, limitChars } from './frame-config.js';
import { FILTER_KEYS, makeCanvas } from './effects.js';
import {
  captureFrame, classifyCameraError, isCameraSupported, readImageFile, shouldMirror, startCamera, stopCamera,
} from './camera.js';
import { attachGestures } from './gestures.js';
import { exportImages } from './export.js';
import { uploadPhoto } from './upload.js';
import { createTranslator } from './i18n.js';
import {
  deleteMyPhoto, fetchMyPhotos, statusClass, statusKey, withoutPhoto,
} from './my-photos.js';

const STAGE_STEPS = ['frame', 'filter', 'adjust', 'texts', 'preview'];
const CONSENT_KEY = 'pp_consent_v1';
// etapas a partir das quais o convidado pode abrir "Minhas fotos"
const MINE_FROM = ['welcome', 'source', 'done'];

/** Chave do consentimento lembrado no aparelho: muda com a versao do texto (novo aceite). */
export function consentStorageKey(cfg) {
  return `${CONSENT_KEY}:${cfg.consentVersion || 'none'}:${cfg.name}`;
}

/** Dados do aviso de privacidade (controlador = organizador do evento). */
export function normalizePrivacy(raw = {}) {
  const sameOrigin = (u) => (typeof u === 'string' && u.startsWith('/') && !u.startsWith('//') ? u : '');
  const days = Number.parseInt(raw?.retentionDays, 10);
  return {
    controller: String(raw?.controller ?? ''),
    retentionDays: Number.isFinite(days) && days > 0 ? days : 0,
    policyUrl: sameOrigin(raw?.policyUrl),
    requestUrl: sameOrigin(raw?.requestUrl),
  };
}

/** Normaliza o objeto do evento vindo do Laravel, com padroes seguros. */
export function normalizeEventConfig(raw = {}) {
  const frames = Array.isArray(raw.frames) ? raw.frames : [];
  return {
    name: String(raw.name ?? ''),
    welcome: String(raw.welcome ?? ''),
    moderation: !!raw.moderation,
    uploadUrl: String(raw.uploadUrl ?? ''),
    myPhotosUrl: String(raw.myPhotosUrl ?? ''),
    consentVersion: String(raw.consentVersion ?? ''),
    privacy: normalizePrivacy(raw.privacy),
    language: String(raw.language ?? 'pt_BR'),
    askGuestName: !!raw.askGuestName,
    csrfToken: raw.csrfToken ?? null,
    frames: frames
      .filter((f) => f && typeof f.key === 'string' && typeof f.configUrl === 'string')
      .map((f) => ({
        key: f.key,
        name: String(f.name ?? f.key),
        configUrl: f.configUrl,
        // orientacoes que a moldura oferece (custom pode ter so uma); padrao: as duas
        orientations: Array.isArray(f.orientations) && f.orientations.some((o) => ORIENTATIONS[o])
          ? f.orientations.filter((o) => ORIENTATIONS[o])
          : Object.keys(ORIENTATIONS),
        // valores dos textos do organizador: { event_name: "Casamento ..." }
        texts: f.texts && typeof f.texts === 'object' ? f.texts : {},
      })),
  };
}

/** Molduras disponiveis para uma orientacao (retrato/paisagem). */
export function framesForOrientation(frames, orientation) {
  return (frames ?? []).filter((f) => (f.orientations ?? Object.keys(ORIENTATIONS)).includes(orientation));
}

const readCsrf = (cfg) => cfg.csrfToken
  ?? (typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.content : null)
  ?? null;

/**
 * @param {object} rawConfig window.PHOTO_PARTY_EVENT
 * @param {{uploadPhoto?: Function}} [deps] injecao (a demo usa um envio simulado)
 */
export function guestApp(rawConfig, deps = {}) {
  const cfg = normalizeEventConfig(rawConfig);
  const lang = cfg.language;
  const t = createTranslator(typeof window !== 'undefined' ? window.PHOTO_PARTY_I18N : {});
  const send = deps.uploadPhoto ?? uploadPhoto;
  const fetchImpl = deps.fetch ?? (typeof fetch !== 'undefined' ? fetch.bind(globalThis) : null);
  const ask = deps.confirm ?? ((msg) => (typeof window !== 'undefined' ? window.confirm(msg) : true));

  // estado nao reativo
  let photo = null; // canvas com a foto ja orientada
  let stream = null;
  let orientCfg = plainOrientation('portrait');
  let assets = {};
  let detachGestures = null;
  let raf = 0;
  let applySeq = 0;
  let abort = null;
  let thumbSeq = 0;
  const frameCache = new Map(); // key -> config
  const assetCache = new Map(); // key:orientation -> {background, overlay}

  return {
    cfg,
    // so as molduras que existem na orientacao atual da foto
    get frameOptions() { return framesForOrientation(cfg.frames, this.orientation); },
    filterOptions: FILTER_KEYS.map((key) => ({ key, label: t(`filter_${key}`) })),

    step: 'welcome',
    consent: false,
    guestName: '',
    status: '',

    cameraState: 'idle', // idle | starting | live | unavailable
    cameraMessage: '',
    facing: 'environment',
    fileError: '',

    orientation: 'portrait',
    frameKey: '',
    frameLoading: false,
    frameError: '',
    filter: 'none',
    view: { zoom: 1, panX: 0, panY: 0 },
    guestFields: [],
    texts: {},

    sending: false,
    retrying: false,
    progress: 0,
    error: '',
    doneMessage: '',
    ended: false,

    // "Minhas fotos"
    mine: [],
    mineLoading: false,
    mineError: '',
    mineGone: false,
    mineBusy: '',
    stepBeforeMine: 'welcome',

    /* ---------------- ciclo de vida ---------------- */

    init() {
      try {
        this.consent = localStorage.getItem(consentStorageKey(cfg)) === '1';
      } catch {
        this.consent = false;
      }
      this.$watch('step', (step) => this.onStep(step));
      window.addEventListener('pagehide', () => this.releaseCamera());
      window.addEventListener('resize', () => this.render());
      window.visualViewport?.addEventListener('resize', () => this.render());
    },

    t,

    /* ---------------- getters/metodos usados pela marcacao (compativel com CSP) ---------------- */

    get isStep_welcome() { return this.step === 'welcome'; },
    get isStep_source() { return this.step === 'source'; },
    get isStep_frame() { return this.step === 'frame'; },
    get isStep_filter() { return this.step === 'filter'; },
    get isStep_adjust() { return this.step === 'adjust'; },
    get isStep_texts() { return this.step === 'texts'; },
    get isStep_preview() { return this.step === 'preview'; },
    get isStep_done() { return this.step === 'done'; },
    get isStep_ended() { return this.step === 'ended'; },
    get cannotAccept() { return !this.consent; },
    get liveMessage() {
      if (this.retrying) return t('retrying');
      return this.sending ? `${this.progress}%` : '';
    },
    get progressText() {
      return this.retrying ? t('retrying') : `${t('sending')} ${this.progress}%`;
    },
    get showSendLabel() { return !this.error && !this.sending; },
    get showRetryLabel() { return !!this.error && !this.sending; },

    get isCameraLive() { return this.cameraState === 'live'; },
    get isCameraStarting() { return this.cameraState === 'starting'; },
    get isCameraUnavailable() { return this.cameraState === 'unavailable'; },
    get showCamera() { return this.isCameraLive || this.isCameraStarting; },
    get showCameraAlert() { return this.isCameraUnavailable && !!this.cameraMessage; },
    get videoClass() { return this.mirrored ? 'gp-video is-mirrored' : 'gp-video'; },
    get stageTabindex() { return this.step === 'adjust' ? 0 : -1; },

    get noFrameSelected() { return this.frameKey === ''; },
    get noFrameClass() { return this.noFrameSelected ? 'gp-tile is-on' : 'gp-tile'; },
    isFrameOn(f) { return this.frameKey === f.key; },
    frameClass(f) { return this.isFrameOn(f) ? 'gp-tile is-on' : 'gp-tile'; },
    chooseNoFrame() { return this.selectFrame(''); },
    chooseFrame(f) { return this.selectFrame(f.key); },
    isFilterOn(f) { return this.filter === f.key; },
    filterClass(f) { return this.isFilterOn(f) ? 'gp-tile is-on' : 'gp-tile'; },
    chooseFilter(f) {
      this.filter = f.key;
      this.render();
    },

    onZoomInput(event) { this.setZoom(event.target.value); },
    /** Campos de convidado exceto o nome, que tem input proprio quando askGuestName. */
    get extraFields() {
      return this.guestFields.filter((f) => !(cfg.askGuestName && f.id === 'guest_name'));
    },
    fieldValue(field) { return this.texts[field.id] ?? ''; },
    onFieldInput(event, field) {
      this.texts[field.id] = event.target.value;
      this.render();
    },
    onNameInput(event) {
      this.guestName = event.target.value;
      this.texts.guest_name = this.guestName;
      this.render();
    },
    charsLeftText(field) { return t('chars_left', { count: this.charsLeft(field) }); },

    /* ---------------- navegacao ---------------- */

    get showStage() { return STAGE_STEPS.includes(this.step); },
    get needsTexts() { return this.guestFields.length > 0 || cfg.askGuestName; },
    get flow() {
      return ['welcome', 'source', 'frame', 'filter', 'adjust', ...(this.needsTexts ? ['texts'] : []), 'preview'];
    },
    get canGoBack() { return this.flow.indexOf(this.step) > 1 && !this.sending; },
    get orientationLabel() { return t(`orientation_${this.orientation}`); },

    accept() {
      if (!this.consent) return;
      try {
        localStorage.setItem(consentStorageKey(cfg), '1');
      } catch {
        // modo privativo: segue sem lembrar
      }
      this.go('source');
    },

    go(step) {
      this.error = '';
      this.step = step;
    },

    next() {
      const i = this.flow.indexOf(this.step);
      if (i >= 0 && i < this.flow.length - 1) this.go(this.flow[i + 1]);
    },

    back() {
      const i = this.flow.indexOf(this.step);
      if (i > 1) this.go(this.flow[i - 1]);
    },

    onStep(step) {
      detachGestures?.();
      detachGestures = null;
      if (step === 'source') this.$nextTick(() => this.openCamera());
      else this.releaseCamera();
      if (step === 'adjust') this.enableGestures();
      if (step === 'texts' && !this.texts.guest_name && this.guestName) this.texts.guest_name = this.guestName;
      if (STAGE_STEPS.includes(step)) this.$nextTick(() => this.render());
      if (step === 'frame' || step === 'filter') this.$nextTick(() => this.renderThumbs(step));
      // foco no titulo da etapa (leitores de tela e teclado)
      this.$nextTick(() => document.querySelector(`[data-step="${step}"] [data-step-heading]`)?.focus({ preventScroll: true }));
    },

    /* ---------------- camera e galeria ---------------- */

    async openCamera() {
      if (!isCameraSupported()) {
        this.cameraState = 'unavailable';
        this.cameraMessage = t('camera_unavailable');
        return;
      }
      this.releaseCamera();
      this.cameraState = 'starting';
      this.cameraMessage = t('camera_starting');
      try {
        stream = await startCamera(this.$refs.video, this.facing);
        this.cameraState = 'live';
        this.cameraMessage = '';
      } catch (err) {
        const kind = classifyCameraError(err);
        this.cameraState = 'unavailable';
        this.cameraMessage = t(kind === 'denied' ? 'camera_denied' : kind === 'busy' ? 'camera_busy' : 'camera_unavailable');
      }
    },

    releaseCamera() {
      stopCamera(stream, this.$refs?.video);
      stream = null;
      if (this.cameraState === 'live' || this.cameraState === 'starting') this.cameraState = 'idle';
    },

    async switchCamera() {
      this.facing = this.facing === 'environment' ? 'user' : 'environment';
      await this.openCamera();
    },

    get mirrored() { return shouldMirror(this.facing); },

    capture() {
      try {
        const canvas = captureFrame(this.$refs.video, { mirror: shouldMirror(this.facing) });
        this.usePhoto(canvas);
      } catch {
        this.cameraMessage = t('camera_unavailable');
        this.cameraState = 'unavailable';
      }
    },

    pickFile(kind) {
      this.fileError = '';
      this.$refs[kind === 'capture' ? 'fileCapture' : 'fileGallery'].click();
    },

    async onFile(event) {
      const input = event.target;
      const file = input.files?.[0];
      input.value = ''; // permite escolher o mesmo arquivo de novo
      if (!file) return;
      try {
        this.usePhoto(await readImageFile(file));
      } catch (err) {
        const map = { 'not-image': 'file_not_image', 'too-large': 'file_too_large' };
        this.fileError = t(map[err.message] ?? 'file_decode_failed');
      }
    },

    async usePhoto(canvas) {
      photo = canvas;
      this.releaseCamera();
      this.orientation = chooseOrientation(canvas.width, canvas.height);
      this.view = { zoom: 1, panX: 0, panY: 0 };
      this.filter = 'none';
      this.texts = {};
      await this.applyFrame(this.frameKey);
      this.go('frame');
    },

    /* ---------------- moldura ---------------- */

    async selectFrame(key) {
      this.frameKey = key;
      await this.applyFrame(key);
    },

    async applyFrame(key) {
      const seq = ++applySeq;
      this.frameLoading = true;
      this.frameError = '';
      let useKey = key;
      try {
        if (!key) {
          orientCfg = plainOrientation(this.orientation);
          assets = {};
        } else {
          const desc = cfg.frames.find((f) => f.key === key);
          if (!desc) throw new Error('frame-unknown');
          // moldura que nao existe nesta orientacao: foto fica sem moldura
          if (!desc.orientations.includes(this.orientation)) {
            if (seq !== applySeq) return;
            this.frameKey = '';
            orientCfg = plainOrientation(this.orientation);
            assets = {};
            this.guestFields = guestTextFields(orientCfg, lang);
            this.view = clampView(this.view, this.geometry());
            this.frameLoading = false;
            this.render();
            return;
          }
          let frame = frameCache.get(key);
          if (!frame) {
            frame = await loadFrameConfig(desc.configUrl);
            frameCache.set(key, frame);
          }
          // moldura sem a orientacao desejada: usa a que ela tem
          if (!frame.orientations[this.orientation]) {
            this.orientation = Object.keys(frame.orientations)[0];
          }
          const o = frame.orientations[this.orientation];
          const cacheKey = `${key}:${this.orientation}`;
          let a = assetCache.get(cacheKey);
          if (!a) {
            a = await loadOrientationAssets(desc.configUrl, o);
            assetCache.set(cacheKey, a);
          }
          if (seq !== applySeq) return; // outra escolha mais recente venceu
          orientCfg = o;
          assets = a;
        }
      } catch {
        if (seq !== applySeq) return;
        this.frameError = t('frame_load_error');
        useKey = '';
        orientCfg = plainOrientation(this.orientation);
        assets = {};
      }
      this.frameKey = useKey;
      this.guestFields = guestTextFields(orientCfg, lang);
      this.view = clampView(this.view, this.geometry());
      this.frameLoading = false;
      this.render();
    },

    flipOrientation() {
      this.orientation = this.orientation === 'portrait' ? 'landscape' : 'portrait';
      this.view = { zoom: 1, panX: 0, panY: 0 };
      return this.applyFrame(this.frameKey);
    },

    /* ---------------- ajuste e previa ---------------- */

    geometry() {
      return {
        imgW: photo?.width ?? 1,
        imgH: photo?.height ?? 1,
        winW: orientCfg.window.w,
        winH: orientCfg.window.h,
        winX: orientCfg.window.x,
        winY: orientCfg.window.y,
        designW: orientCfg.width,
        designH: orientCfg.height,
      };
    },

    enableGestures() {
      const el = this.$refs.stage;
      if (!el) return;
      detachGestures = attachGestures(el, {
        getGeometry: () => this.geometry(),
        getView: () => ({ ...this.view }),
        onChange: (v) => {
          this.view = v;
          this.render();
        },
      });
    },

    setZoom(value) {
      this.view = zoomAround(this.view, this.geometry(), Number(value));
      this.render();
    },

    /** Escala da previa: so o necessario para a area livre da tela (menos pixels = arrastar fluido). */
    previewScale() {
      const box = this.$refs.stage?.parentElement; // .gp-stage__fit (ocupa o espaco livre)
      const cssW = box?.clientWidth || 360;
      const cssH = box?.clientHeight || (typeof window !== 'undefined' ? window.innerHeight : 800) * 0.6;
      const ratio = orientCfg.width / orientCfg.height;
      const shownW = Math.min(cssW, cssH * ratio);
      const dpr = Math.min(2, (typeof window !== 'undefined' && window.devicePixelRatio) || 1);
      return Math.min(1, Math.max(0.2, (shownW * dpr) / orientCfg.width));
    },

    /** Valores dos textos: organizador (do evento) + convidado (digitados). */
    textValues() {
      const desc = cfg.frames.find((f) => f.key === this.frameKey);
      const values = { ...(desc?.texts ?? {}) };
      for (const f of this.guestFields) {
        const v = this.texts[f.id];
        if (typeof v === 'string' && v.trim() !== '') values[f.id] = limitChars(v, f.maxChars);
      }
      return values;
    },

    render() {
      if (raf) return;
      raf = requestAnimationFrame(() => {
        raf = 0;
        this.drawPreview();
      });
    },

    drawPreview() {
      const canvas = this.$refs.stage;
      if (!photo || !canvas || !this.showStage) return;
      renderComposition(canvas, {
        cfg: orientCfg, assets, photo, view: { ...this.view }, filter: this.filter,
        texts: this.textValues(), lang, scale: this.previewScale(),
      });
    },

    /* ---------------- miniaturas (tiras de moldura e filtro) ---------------- */

    /** Config + imagens de uma moldura numa orientacao (com cache); null se nao existir nela. */
    async frameFor(key, orientation) {
      const desc = cfg.frames.find((f) => f.key === key);
      if (!desc || !desc.orientations.includes(orientation)) return null;
      let frame = frameCache.get(key);
      if (!frame) {
        frame = await loadFrameConfig(desc.configUrl);
        frameCache.set(key, frame);
      }
      const o = frame.orientations[orientation];
      if (!o) return null;
      const cacheKey = `${key}:${orientation}`;
      let a = assetCache.get(cacheKey);
      if (!a) {
        a = await loadOrientationAssets(desc.configUrl, o);
        assetCache.set(cacheKey, a);
      }
      return { o, a, texts: desc.texts ?? {} };
    },

    /** Desenha as miniaturas reais (foto do convidado + moldura/filtro) na tira do passo. */
    async renderThumbs(kind) {
      if (!photo || typeof document === 'undefined') return;
      const seq = ++thumbSeq;
      const attr = kind === 'frame' ? 'data-thumb-frame' : 'data-thumb-filter';
      const nodes = [...(this.$root ?? document).querySelectorAll(`[${attr}]`)];
      for (const node of nodes) {
        if (seq !== thumbSeq) return;
        const value = node.getAttribute(attr) ?? '';
        let o = orientCfg;
        let a = assets;
        let texts = this.textValues();
        let filter = value;
        if (kind === 'frame') {
          filter = this.filter;
          if (value === '') {
            o = plainOrientation(this.orientation);
            a = {};
          } else {
            try {
              const r = await this.frameFor(value, this.orientation);
              if (!r) continue;
              ({ o, a, texts } = r);
            } catch {
              continue;
            }
          }
        }
        if (seq !== thumbSeq) return;
        const dpr = Math.min(2, (typeof window !== 'undefined' && window.devicePixelRatio) || 1);
        const targetH = (node.clientHeight || 72) * dpr;
        try {
          renderComposition(node, {
            cfg: o, assets: a, photo, view: kind === 'frame' ? { zoom: 1, panX: 0, panY: 0 } : { ...this.view },
            filter, texts, lang, scale: Math.min(0.3, targetH / o.height),
          });
        } catch {
          // miniatura e so decorativa
        }
      }
    },

    charsLeft(field) {
      return Math.max(0, field.maxChars - Array.from(this.texts[field.id] ?? '').length);
    },

    /* ---------------- envio ---------------- */

    async submit() {
      if (this.sending) return;
      this.sending = true;
      this.retrying = false;
      this.error = '';
      this.progress = 0;
      abort = new AbortController();
      try {
        // composicao final em tamanho cheio (1080x1350 ou 1920x1080)
        const out = makeCanvas(ORIENTATIONS[this.orientation].width, ORIENTATIONS[this.orientation].height);
        renderComposition(out, {
          cfg: orientCfg, assets, photo, view: { ...this.view }, filter: this.filter,
          texts: this.textValues(), lang, scale: 1,
        });
        const { photo: photoBlob, thumb } = await exportImages(out);

        const guestTexts = {};
        for (const f of this.guestFields) guestTexts[f.id] = limitChars(this.texts[f.id] ?? '', f.maxChars);

        await send(cfg.uploadUrl, {
          photo: photoBlob,
          thumb,
          frameKey: this.frameKey,
          filter: this.filter,
          orientation: this.orientation,
          guestName: limitChars(this.guestName, 60),
          guestTexts,
          consentVersion: cfg.consentVersion,
          csrfToken: readCsrf(cfg),
        }, {
          signal: abort.signal,
          onProgress: (p) => { this.progress = Math.round(p * 100); },
          onRetry: () => { this.retrying = true; },
        });
        this.doneMessage = cfg.moderation ? t('done_pending') : t('done_approved');
        this.go('done');
      } catch (err) {
        if (err?.kind === 'ended') {
          this.ended = true;
          this.mineGone = true;
          this.go('ended');
        } else {
          this.error = this.messageFor(err);
        }
      } finally {
        this.sending = false;
        this.retrying = false;
      }
    },

    messageFor(err) {
      switch (err?.kind) {
        case 'ended': return t('error_ended');
        case 'rate': return t('error_rate');
        case 'invalid': return err.message && err.message !== 'invalid'
          ? t('error_invalid', { message: err.message }) : t('error_invalid_generic');
        case 'session': return t('error_session');
        case 'server': return t('error_server');
        case 'network': return t('error_network');
        default: return t('error_generic');
      }
    },

    /** "Tirar outra": volta para a camera mantendo moldura escolhida e nome. */
    another() {
      photo = null;
      this.texts = {};
      this.filter = 'none';
      this.progress = 0;
      this.doneMessage = '';
      this.go('source');
    },

    get welcomeText() { return cfg.welcome || t('welcome_default'); },

    /* ---------------- aviso de privacidade ---------------- */

    get consentText() {
      const p = cfg.privacy;
      const base = t('consent_text', { controller: p.controller || t('consent_controller_fallback') });
      return p.retentionDays ? `${base} ${t('consent_retention', { days: p.retentionDays })}` : base;
    },
    get privacyPolicyUrl() { return cfg.privacy.policyUrl || '#'; },
    get privacyRequestUrl() { return cfg.privacy.requestUrl || '#'; },
    get hasPrivacyLinks() { return !!(cfg.privacy.policyUrl && cfg.privacy.requestUrl); },

    /* ---------------- minhas fotos ---------------- */

    get isStep_mine() { return this.step === 'mine'; },
    get showMineLink() { return !!cfg.myPhotosUrl && !this.mineGone && MINE_FROM.includes(this.step); },
    get mineEmpty() { return !this.mineLoading && !this.mineError && this.mine.length === 0; },
    get mineCountText() { return t('mine_count', { count: this.mine.length }); },
    statusText(p) { return t(statusKey(p.status)); },
    statusClassOf(p) { return statusClass(p.status); },
    thumbAlt(p) { return t('mine_photo_alt', { status: t(statusKey(p.status)) }); },
    isDeleting(p) { return this.mineBusy === p.uuid; },

    openMine() {
      if (!MINE_FROM.includes(this.step)) return;
      this.stepBeforeMine = this.step;
      this.go('mine');
      return this.loadMine();
    },

    closeMine() {
      this.go(this.stepBeforeMine || 'welcome');
    },

    async loadMine() {
      if (!fetchImpl) return;
      this.mineLoading = true;
      this.mineError = '';
      const res = await fetchMyPhotos(cfg.myPhotosUrl, { fetchImpl });
      this.mineLoading = false;
      if (res.gone) {
        this.mine = [];
        this.mineGone = true;
        this.go('ended');
        return;
      }
      if (!res.ok) {
        this.mineError = t('mine_load_error');
        return;
      }
      this.mine = res.photos;
    },

    async removeMine(p) {
      if (!fetchImpl || this.mineBusy || !ask(t('mine_delete_confirm'))) return;
      this.mineBusy = p.uuid;
      this.mineError = '';
      const result = await deleteMyPhoto(p.deleteUrl, readCsrf(cfg), { fetchImpl });
      this.mineBusy = '';
      if (result === 'deleted' || result === 'gone') {
        this.mine = withoutPhoto(this.mine, p.uuid);
        return;
      }
      this.mineError = t('mine_delete_error');
    },
  };
}

/** Registra o componente no Alpine; `deps` permite trocar o envio (demo). */
export function registerGuestApp(AlpineInstance, deps = {}) {
  AlpineInstance.data('guestApp', () => guestApp(window.PHOTO_PARTY_EVENT ?? {}, deps));
}
