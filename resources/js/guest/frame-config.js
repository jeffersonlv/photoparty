// Contrato de configuracao de moldura (frame-config.md): constantes, validacao e
// helpers de texto. Sem acesso a DOM: tudo aqui e puro e testavel em Node.

export const ORIENTATIONS = Object.freeze({
  portrait: Object.freeze({ width: 1080, height: 1350 }),
  landscape: Object.freeze({ width: 1920, height: 1080 }),
});

export const ALLOWED_FONTS = Object.freeze(['Anton', 'Playfair Display', 'Rye', 'Great Vibes', 'Inter']);
export const EFFECT_TYPES = Object.freeze(['led', 'sepia']);
export const ALIGNS = Object.freeze(['left', 'center', 'right']);
export const BASELINES = Object.freeze(['top', 'middle', 'alphabetic', 'bottom']);
export const TRANSFORMS = Object.freeze(['uppercase']);
export const FILLED_BY = Object.freeze(['organizer', 'guest']);
export const GUEST_MAX_CHARS = 40;

const isObj = (v) => v !== null && typeof v === 'object' && !Array.isArray(v);
const isNum = (v) => typeof v === 'number' && Number.isFinite(v);

/**
 * Caminho de imagem do config: relativo a pasta do modelo. Rejeita URLs absolutas,
 * esquemas (http:, data:, //host), barra inicial e ".." para nunca sair da pasta
 * nem fazer requisicao externa.
 */
export function isSafeRelativePath(p) {
  if (typeof p !== 'string' || p === '' || p.length > 200) return false;
  if (/^[a-z][a-z0-9+.-]*:/i.test(p)) return false;
  if (p.startsWith('/') || p.startsWith('\\') || p.includes('\\')) return false;
  if (p.split('/').some((seg) => seg === '..' || seg === '')) return false;
  return /\.png$/i.test(p);
}

function validateText(t, path, errors) {
  if (!isObj(t)) return errors.push(`${path}: deve ser objeto`);
  if (typeof t.id !== 'string' || !t.id) errors.push(`${path}.id: obrigatorio`);
  for (const k of ['x', 'y']) if (!isNum(t[k])) errors.push(`${path}.${k}: numero obrigatorio`);
  if (!isNum(t.maxWidth) || t.maxWidth <= 0) errors.push(`${path}.maxWidth: numero > 0`);
  if (!ALLOWED_FONTS.includes(t.font)) errors.push(`${path}.font: fonte fora da lista permitida`);
  if (!isNum(t.size) || t.size <= 0) errors.push(`${path}.size: numero > 0`);
  if (typeof t.color !== 'string' || !t.color) errors.push(`${path}.color: obrigatorio`);
  if (!ALIGNS.includes(t.align)) errors.push(`${path}.align: left|center|right`);
  if (!BASELINES.includes(t.baseline)) errors.push(`${path}.baseline: top|middle|alphabetic|bottom`);
  if (t.transform != null && !TRANSFORMS.includes(t.transform)) errors.push(`${path}.transform: uppercase|null`);
  if (!Number.isInteger(t.maxChars) || t.maxChars <= 0) errors.push(`${path}.maxChars: inteiro > 0`);
  if (!FILLED_BY.includes(t.filledBy)) errors.push(`${path}.filledBy: organizer|guest`);
  else if (t.filledBy === 'guest' && t.maxChars > GUEST_MAX_CHARS) {
    errors.push(`${path}.maxChars: texto de convidado tem no maximo ${GUEST_MAX_CHARS}`);
  }
  if (t.label != null && !isObj(t.label)) errors.push(`${path}.label: objeto de idiomas`);
  if (t.default != null && !isObj(t.default)) errors.push(`${path}.default: objeto de idiomas`);
  if (t.shadow != null) {
    const s = t.shadow;
    if (!isObj(s) || typeof s.color !== 'string' || !['blur', 'offsetX', 'offsetY'].every((k) => isNum(s[k]))) {
      errors.push(`${path}.shadow: {color, blur, offsetX, offsetY}`);
    }
  }
}

function validateOrientation(name, o, errors) {
  const path = `orientations.${name}`;
  if (!isObj(o)) return errors.push(`${path}: deve ser objeto`);
  const size = ORIENTATIONS[name];
  if (o.width !== size.width || o.height !== size.height) {
    errors.push(`${path}: tamanho deve ser ${size.width}x${size.height}`);
  }
  for (const k of ['background', 'overlay']) {
    if (o[k] != null && !isSafeRelativePath(o[k])) errors.push(`${path}.${k}: caminho PNG relativo invalido`);
  }
  const w = o.window;
  if (!isObj(w) || !['x', 'y', 'w', 'h'].every((k) => isNum(w[k]))) {
    errors.push(`${path}.window: {x,y,w,h} numericos`);
  } else {
    if (w.w <= 0 || w.h <= 0) errors.push(`${path}.window: w e h devem ser > 0`);
    if (w.x < 0 || w.y < 0 || w.x + w.w > size.width || w.y + w.h > size.height) {
      errors.push(`${path}.window: fora da area da moldura`);
    }
  }
  if (!Array.isArray(o.texts)) errors.push(`${path}.texts: array obrigatorio (pode ser vazio)`);
  else {
    const ids = new Set();
    o.texts.forEach((t, i) => {
      validateText(t, `${path}.texts[${i}]`, errors);
      if (isObj(t) && ids.has(t.id)) errors.push(`${path}.texts[${i}].id: duplicado`);
      if (isObj(t)) ids.add(t.id);
    });
  }
  if (o.effect != null) {
    if (!isObj(o.effect) || !EFFECT_TYPES.includes(o.effect.type)) errors.push(`${path}.effect.type: led|sepia|null`);
    else if (o.effect.params != null && !isObj(o.effect.params)) errors.push(`${path}.effect.params: objeto`);
  }
}

/**
 * Valida um config.json contra o contrato.
 * @param {unknown} cfg
 * @param {{requireBoth?: boolean}} [opts] requireBoth (padrao true): exige retrato e paisagem.
 * @returns {{valid: boolean, errors: string[]}}
 */
export function validateFrameConfig(cfg, { requireBoth = true } = {}) {
  const errors = [];
  if (!isObj(cfg)) return { valid: false, errors: ['config: deve ser objeto'] };
  if (cfg.version !== 1) errors.push('version: deve ser 1');
  if (typeof cfg.key !== 'string' || !/^[a-z0-9_-]{1,64}$/i.test(cfg.key)) errors.push('key: obrigatorio ([a-z0-9_-])');
  if (!isObj(cfg.name)) errors.push('name: objeto de idiomas obrigatorio');
  if (!isObj(cfg.orientations)) {
    errors.push('orientations: obrigatorio');
  } else {
    const names = Object.keys(cfg.orientations);
    for (const n of names) if (!ORIENTATIONS[n]) errors.push(`orientations.${n}: orientacao desconhecida`);
    if (requireBoth) {
      for (const n of Object.keys(ORIENTATIONS)) if (!cfg.orientations[n]) errors.push(`orientations.${n}: obrigatoria`);
    } else if (!names.some((n) => ORIENTATIONS[n])) {
      errors.push('orientations: ao menos uma orientacao');
    }
    for (const n of names) if (ORIENTATIONS[n]) validateOrientation(n, cfg.orientations[n], errors);
  }
  return { valid: errors.length === 0, errors };
}

/** Escolhe o texto no idioma pedido; cai para pt e depois para o primeiro disponivel. */
export function pickLang(obj, lang) {
  if (!isObj(obj)) return '';
  const short = String(lang || 'pt').split(/[-_]/)[0].toLowerCase();
  if (typeof obj[short] === 'string') return obj[short];
  if (typeof obj.pt === 'string') return obj.pt;
  const first = Object.values(obj).find((v) => typeof v === 'string');
  return first ?? '';
}

/** Corta por pontos de codigo (nao quebra emoji ao meio) e remove controles. */
export function limitChars(text, maxChars) {
  const clean = String(text ?? '').replace(/[\u0000-\u001F\u007F]/g, ' ').replace(/\s+/g, ' ').trim();
  const chars = Array.from(clean);
  return chars.length > maxChars ? chars.slice(0, maxChars).join('').trim() : clean;
}

export function applyTransform(text, transform) {
  return transform === 'uppercase' ? text.toLocaleUpperCase() : text;
}

/**
 * Texto final de um campo: valor informado (convidado/organizador) ou padrao do idioma,
 * limitado a maxChars e com transform aplicado.
 * @param {object} def definicao do texto no config
 * @param {Record<string,string>} values valores por id
 */
export function resolveText(def, values, lang) {
  const given = values?.[def.id];
  const raw = typeof given === 'string' && given.trim() !== '' ? given : pickLang(def.default, lang);
  return applyTransform(limitChars(raw, def.maxChars), def.transform);
}

/** Textos que o convidado preenche, na ordem do config. */
export function guestTextFields(orientationCfg, lang) {
  return (orientationCfg?.texts ?? [])
    .filter((t) => t.filledBy === 'guest')
    .map((t) => ({
      id: t.id,
      label: pickLang(t.label, lang) || t.id,
      maxChars: Math.min(t.maxChars, GUEST_MAX_CHARS),
      placeholder: pickLang(t.default, lang),
    }));
}

/**
 * Junta a pasta do config com um caminho relativo. A query string do config (ex.: ?t= do
 * token do evento nas molduras personalizadas) e repassada a imagem, que e autorizada
 * pela mesma rota do servidor.
 */
export function resolveAssetUrl(configUrl, relPath) {
  const [base, ...rest] = String(configUrl).split('?');
  const dir = base.slice(0, base.lastIndexOf('/') + 1);
  const query = rest.join('?');
  return dir + relPath + (query ? `?${query}` : '');
}
