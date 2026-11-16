# Contrato: configuração de moldura (frame config) — Photo Party

Fonte única de verdade compartilhada entre o motor de moldura (JS, navegador) e os assets dos modelos.

## Arquivo
Cada modelo pronto tem uma pasta `frames/<key>/` com:
- `config.json` (formato abaixo)
- imagens referenciadas pelo config (PNG; fundo e/ou camada de cima), uma por orientação

Caminhos de imagem no config são relativos à pasta do modelo. Em produção a pasta fica em `public/frames/<key>/` (assets do sistema são públicos; fotos de convidados NUNCA são).

## Formato de `config.json`
```json
{
  "version": 1,
  "key": "led_billboard",
  "name": { "pt": "Painel de LED", "en": "LED Billboard" },
  "orientations": {
    "portrait": {
      "width": 1080,
      "height": 1350,
      "background": "portrait-bg.png",
      "window": { "x": 120, "y": 260, "w": 840, "h": 620 },
      "overlay": "portrait-overlay.png",
      "texts": [
        {
          "id": "event_name",
          "x": 540, "y": 1180,
          "maxWidth": 900,
          "font": "Anton",
          "size": 72,
          "color": "#FFFFFF",
          "align": "center",
          "baseline": "middle",
          "transform": "uppercase",
          "maxChars": 40,
          "filledBy": "organizer",
          "label": { "pt": "Nome do evento", "en": "Event name" },
          "default": { "pt": "NOSSO EVENTO", "en": "OUR EVENT" },
          "shadow": { "color": "rgba(0,0,0,0.6)", "blur": 12, "offsetX": 0, "offsetY": 0 }
        }
      ],
      "effect": { "type": "led", "params": { "dotSize": 6, "gap": 2, "glow": 0.35 } }
    },
    "landscape": { "...": "mesmo formato, width 1920 height 1080" }
  }
}
```

## Regras
- Tamanhos fixos: `portrait` = 1080 × 1350 (4:5), `landscape` = 1920 × 1080 (16:9). Ambas as orientações obrigatórias nos modelos prontos.
- Ordem de desenho: `background` → foto (cobrindo a `window`, modo cover, com pan/zoom do usuário, recortada à janela) → efeito sobre a foto (aplicado só dentro da `window`) → `overlay` → `texts`.
- `background` e `overlay` são opcionais (`null`). O overlay pode ter transparência na área da janela.
- `effect.type` ∈ `"led"`, `"sepia"`, `null`. Parâmetros opcionais.
- `filledBy` ∈ `"organizer"`, `"guest"`. Textos de convidado têm `maxChars` ≤ 40.
- `transform` ∈ `"uppercase"`, `null`. `align` ∈ `left|center|right`. `baseline` ∈ `top|middle|alphabetic|bottom`.
- Texto que excede `maxWidth` é reduzido proporcionalmente (fonte menor), nunca cortado.
- Fontes: apenas fontes com licença OFL, servidas localmente em `public/fonts/` com arquivo `.woff2`; nome em `font` = family do `@font-face`. Lista de fontes permitidas no MVP: `Anton`, `Playfair Display`, `Rye`, `Great Vibes`, `Inter`.
- Moldura enviada pelo organizador (custom) usa o mesmo formato, gerado pelo servidor: `background: null`, `overlay: <png enviado>`, `window` = bounding box da área transparente, `texts: []`, `effect: null`.

## Modelos do MVP
| key | Visual | Textos | Efeito |
|---|---|---|---|
| `led_billboard` | Painel de LED gigante numa fachada urbana à noite, estilo praça de Nova York. Fundo genérico: prédios, luzes, SEM marcas, logos ou anúncios reais | `event_name` (organizer) | `led` |
| `magazine_cover` | Capa de revista inventada. Nome da revista é texto editável; nunca imitar revista real | `magazine_title` (organizer, default "PHOTO PARTY"), `headline_1`, `headline_2` (organizer), `issue_date` (organizer) | null |
| `wanted_poster` | Cartaz de procurado de faroeste, papel envelhecido | `wanted_title` (organizer, default pt "PROCURADO" / en "WANTED"), `guest_name` (guest), `reward` (organizer, default pt "RECOMPENSA: UM BRINDE" / en "REWARD: ONE TOAST") | `sepia` |
| `floral` | Flores delicadas nos cantos | `event_name`, `event_date` (organizer) | null |
