// Traducao minima: dicionario plano vindo do Laravel (window.PHOTO_PARTY_I18N),
// com placeholders no estilo :nome.

export function createTranslator(dict = {}) {
  return (key, params = {}) => {
    let text = dict[key];
    if (typeof text !== 'string') return key;
    for (const [name, value] of Object.entries(params)) text = text.split(`:${name}`).join(String(value));
    return text;
  };
}
