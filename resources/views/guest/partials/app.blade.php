{{--
    Marcacao do app do convidado. Somente textos via __() e diretivas x-* do Alpine
    (x-on:/x-bind: em vez de @ e : para nao conflitar com o Blade).
    Compativel com CSP estrita (@alpinejs/csp, sem unsafe-eval): so identificadores, membros
    e chamadas simples. Ternarios, negacoes, comparacoes e atribuicoes ficam no componente.
    A demo estatica (demo/index.html) injeta este mesmo arquivo trocando os __() pelo pt_BR.

    Layout de tela unica (celular): .gp tem a altura da janela (100dvh / visualViewport) e cada
    passo cabe inteiro, sem rolar a pagina: cabecalho compacto, area central flexivel (camera,
    previa ou conteudo com rolagem interna) e barra de acoes inferior (.gp-bar).
--}}
<main class="gp" x-data="guestApp" x-cloak x-bind:data-step="step">
    <header class="gp-header">
        <button type="button" class="gp-iconbtn" x-show="canGoBack" x-on:click="back()" aria-label="{{ __('guest.back') }}">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <p class="gp-event" x-text="cfg.name"></p>
        <button type="button" class="gp-linkbtn" x-show="showMineLink" x-on:click="openMine()">{{ __('guest.mine_open') }}</button>
    </header>

    {{-- anuncia mudancas importantes a leitores de tela --}}
    <p class="gp-sr" role="status" aria-live="polite" x-text="liveMessage"></p>

    {{-- 1. boas-vindas + consentimento (resumo; politica completa no link) --}}
    <section class="gp-step gp-step--full" data-step="welcome" x-show="isStep_welcome">
        <div class="gp-scroll">
            <div class="gp-welcome-body">
            <h1 class="gp-title" tabindex="-1" data-step-heading>{{ __('guest.consent_title') }}</h1>
            <p class="gp-lead" x-text="welcomeText"></p>
            <div class="gp-card">
                <p class="gp-small" x-text="consentText"></p>
                <p class="gp-small gp-links" x-show="hasPrivacyLinks">
                    <a class="gp-link" x-bind:href="privacyPolicyUrl" target="_blank" rel="noopener">{{ __('guest.consent_policy_link') }}</a>
                    <a class="gp-link" x-bind:href="privacyRequestUrl" target="_blank" rel="noopener">{{ __('guest.consent_request_link') }}</a>
                </p>
            </div>
            </div>
        </div>
        <div class="gp-bar">
            <label class="gp-check">
                <input type="checkbox" x-model="consent" required>
                <span>{{ __('guest.consent_check') }}</span>
            </label>
            <button type="button" class="gp-btn gp-btn--primary" x-on:click="accept()" x-bind:disabled="cannotAccept">{{ __('guest.start') }}</button>
        </div>
    </section>

    {{-- 2. camera ou galeria --}}
    <section class="gp-step gp-step--full" data-step="source" x-show="isStep_source">
        <h1 class="gp-title gp-title--sm" tabindex="-1" data-step-heading>{{ __('guest.source_title') }}</h1>

        <div class="gp-camera">
            <video x-ref="video" class="gp-video" x-show="showCamera" x-bind:class="videoClass" playsinline muted aria-label="{{ __('guest.camera_preview') }}"></video>
            <p class="gp-camera-msg" x-show="isCameraStarting" x-text="cameraMessage"></p>
            <div class="gp-camera-off" x-show="isCameraUnavailable">
                <svg viewBox="0 0 24 24" width="56" height="56" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="13" r="3.6" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                <p class="gp-small" x-show="showCameraAlert" x-text="cameraMessage"></p>
            </div>
        </div>

        <p class="gp-alert" role="alert" x-show="fileError" x-text="fileError"></p>

        <div class="gp-bar gp-bar--row gp-bar--stack-sm">
            <button type="button" class="gp-btn gp-btn--primary gp-btn--shutter" x-show="isCameraLive" x-on:click="capture()">{{ __('guest.take_photo') }}</button>
            <button type="button" class="gp-btn" x-show="isCameraLive" x-on:click="switchCamera()">{{ __('guest.switch_camera') }}</button>
            {{-- fallback: camera nativa do celular quando getUserMedia nao esta disponivel --}}
            <button type="button" class="gp-btn gp-btn--primary" x-show="isCameraUnavailable" x-on:click="pickFile('capture')">{{ __('guest.take_with_phone') }}</button>
            <button type="button" class="gp-btn" x-on:click="pickFile('gallery')">{{ __('guest.from_gallery') }}</button>
        </div>
        <input type="file" accept="image/*" capture="environment" class="gp-hidden" x-ref="fileCapture" x-on:change="onFile($event)" tabindex="-1">
        <input type="file" accept="image/*" class="gp-hidden" x-ref="fileGallery" x-on:change="onFile($event)" tabindex="-1">
    </section>

    {{-- palco compartilhado (moldura, filtro, ajuste, textos e previa): ocupa o espaco livre --}}
    <div class="gp-stage" x-show="showStage" x-bind:data-orientation="orientation">
        <div class="gp-stage__fit">
            <canvas class="gp-canvas" x-ref="stage" role="img" aria-label="{{ __('guest.stage_label') }}" x-bind:tabindex="stageTabindex"></canvas>
        </div>
        <p class="gp-stage-busy" x-show="frameLoading">{{ __('guest.loading') }}</p>
    </div>

    {{-- 3. moldura: tira horizontal de miniaturas --}}
    <section class="gp-step gp-step--panel" data-step="frame" x-show="isStep_frame">
        <h1 class="gp-title gp-title--sm" tabindex="-1" data-step-heading>{{ __('guest.frame_title') }}</h1>
        <p class="gp-alert gp-alert--sm" role="alert" x-show="frameError" x-text="frameError"></p>
        <div class="gp-strip" role="radiogroup" aria-label="{{ __('guest.frame_title') }}">
            <button type="button" class="gp-tile" role="radio" x-bind:aria-checked="noFrameSelected" x-bind:class="noFrameClass" x-on:click="chooseNoFrame()">
                <canvas class="gp-tile__img" data-thumb-frame="" aria-hidden="true"></canvas>
                <span class="gp-tile__label">{{ __('guest.no_frame') }}</span>
            </button>
            <template x-for="f in frameOptions" x-bind:key="f.key">
                <button type="button" class="gp-tile" role="radio" x-bind:aria-checked="isFrameOn(f)" x-bind:class="frameClass(f)" x-on:click="chooseFrame(f)">
                    <canvas class="gp-tile__img" x-bind:data-thumb-frame="f.key" aria-hidden="true"></canvas>
                    <span class="gp-tile__label" x-text="f.name"></span>
                </button>
            </template>
        </div>
        <div class="gp-bar">
            <button type="button" class="gp-btn gp-btn--primary" x-on:click="next()" x-bind:disabled="frameLoading">{{ __('guest.next') }}</button>
        </div>
    </section>

    {{-- 4. filtro: tira horizontal de miniaturas --}}
    <section class="gp-step gp-step--panel" data-step="filter" x-show="isStep_filter">
        <h1 class="gp-title gp-title--sm" tabindex="-1" data-step-heading>{{ __('guest.filter_title') }}</h1>
        <div class="gp-strip" role="radiogroup" aria-label="{{ __('guest.filter_title') }}">
            <template x-for="f in filterOptions" x-bind:key="f.key">
                <button type="button" class="gp-tile" role="radio" x-bind:aria-checked="isFilterOn(f)" x-bind:class="filterClass(f)" x-on:click="chooseFilter(f)">
                    <canvas class="gp-tile__img" x-bind:data-thumb-filter="f.key" aria-hidden="true"></canvas>
                    <span class="gp-tile__label" x-text="f.label"></span>
                </button>
            </template>
        </div>
        <div class="gp-bar">
            <button type="button" class="gp-btn gp-btn--primary" x-on:click="next()">{{ __('guest.next') }}</button>
        </div>
    </section>

    {{-- 5. ajuste (arrastar / pinca / zoom) --}}
    <section class="gp-step gp-step--panel" data-step="adjust" x-show="isStep_adjust">
        <h1 class="gp-title gp-title--sm" tabindex="-1" data-step-heading>{{ __('guest.adjust_title') }}</h1>
        <p class="gp-small gp-oneline">{{ __('guest.adjust_hint') }}</p>
        <div class="gp-adjust">
            <label class="gp-range">
                <span>{{ __('guest.zoom') }}</span>
                <input type="range" min="1" max="4" step="0.01" x-bind:value="view.zoom" x-on:input="onZoomInput($event)">
            </label>
            <button type="button" class="gp-btn gp-btn--sm gp-btn--auto" x-on:click="flipOrientation()">
                <span x-text="orientationLabel"></span>
                <span class="gp-sr">{{ __('guest.flip_orientation') }}</span>
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.7-5.6M20 12a8 8 0 0 1-13.7 5.6M17 3v4h-4M7 21v-4h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>
        <div class="gp-bar">
            <button type="button" class="gp-btn gp-btn--primary" x-on:click="next()">{{ __('guest.next') }}</button>
        </div>
    </section>

    {{-- 6. textos do convidado (com teclado aberto a previa encolhe e a barra continua visivel) --}}
    <section class="gp-step gp-step--panel" data-step="texts" x-show="isStep_texts">
        <h1 class="gp-title gp-title--sm" tabindex="-1" data-step-heading>{{ __('guest.texts_title') }}</h1>
        <div class="gp-fields">
            <template x-if="cfg.askGuestName">
                <label class="gp-field">
                    <span>{{ __('guest.guest_name_label') }} <small>{{ __('guest.guest_name_optional') }}</small></span>
                    <input type="text" maxlength="60" autocomplete="name" enterkeyhint="done" x-bind:value="guestName" x-on:input="onNameInput($event)">
                </label>
            </template>
            <template x-for="field in extraFields" x-bind:key="field.id">
                <label class="gp-field">
                    <span><span x-text="field.label"></span> <small x-text="charsLeftText(field)"></small></span>
                    <input type="text" autocomplete="off" enterkeyhint="done" x-bind:maxlength="field.maxChars" x-bind:placeholder="field.placeholder" x-bind:value="fieldValue(field)" x-on:input="onFieldInput($event, field)">
                </label>
            </template>
        </div>
        <div class="gp-bar">
            <button type="button" class="gp-btn gp-btn--primary" x-on:click="next()">{{ __('guest.next') }}</button>
        </div>
    </section>

    {{-- 7. previa + envio --}}
    <section class="gp-step gp-step--panel" data-step="preview" x-show="isStep_preview">
        <h1 class="gp-title gp-title--sm" tabindex="-1" data-step-heading>{{ __('guest.preview_title') }}</h1>
        <p class="gp-alert gp-alert--sm" role="alert" x-show="error" x-text="error"></p>
        <div class="gp-progress" x-show="sending">
            <progress max="100" x-bind:value="progress" aria-label="{{ __('guest.progress_label') }}"></progress>
            <p class="gp-small" x-text="progressText"></p>
        </div>
        <div class="gp-bar">
            <button type="button" class="gp-btn gp-btn--primary" x-on:click="submit()" x-bind:disabled="sending">
                <span x-show="showSendLabel">{{ __('guest.send') }}</span>
                <span x-show="showRetryLabel">{{ __('guest.try_again') }}</span>
                <span x-show="sending">{{ __('guest.sending') }}</span>
            </button>
        </div>
    </section>

    {{-- 8. sucesso --}}
    <section class="gp-step gp-step--full" data-step="done" x-show="isStep_done">
        <div class="gp-scroll gp-scroll--center">
            <svg class="gp-check-icon" viewBox="0 0 24 24" width="72" height="72" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor" opacity=".18"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <h1 class="gp-title" tabindex="-1" data-step-heading>{{ __('guest.done_title') }}</h1>
            <p class="gp-lead" x-text="doneMessage"></p>
        </div>
        <div class="gp-bar">
            <button type="button" class="gp-btn gp-btn--primary" x-on:click="another()">{{ __('guest.another') }}</button>
            <button type="button" class="gp-btn" x-show="showMineLink" x-on:click="openMine()">{{ __('guest.mine_open') }}</button>
        </div>
    </section>

    {{-- minhas fotos: so as deste aparelho, enquanto o evento esta ao vivo (grade com rolagem interna) --}}
    <section class="gp-step gp-step--full" data-step="mine" x-show="isStep_mine">
        <h1 class="gp-title gp-title--sm" tabindex="-1" data-step-heading>{{ __('guest.mine_title') }}</h1>
        <p class="gp-small">{{ __('guest.mine_hint') }}</p>
        <div class="gp-scroll">
            <p class="gp-small" x-show="mineLoading">{{ __('guest.loading') }}</p>
            <p class="gp-alert gp-alert--sm" role="alert" x-show="mineError" x-text="mineError"></p>
            <p class="gp-small gp-empty" x-show="mineEmpty">{{ __('guest.mine_empty') }}</p>
            <ul class="gp-mine" aria-label="{{ __('guest.mine_title') }}">
                <template x-for="p in mine" x-bind:key="p.uuid">
                    <li class="gp-mine__item">
                        <div class="gp-mine__frame">
                            <img class="gp-mine__img" loading="lazy" decoding="async" x-bind:src="p.thumbUrl" x-bind:alt="thumbAlt(p)">
                            <span x-bind:class="statusClassOf(p)" x-text="statusText(p)"></span>
                        </div>
                        <div class="gp-mine__actions">
                            <a class="gp-btn gp-btn--sm" x-bind:href="p.downloadUrl" download>{{ __('guest.mine_download') }}</a>
                            <button type="button" class="gp-btn gp-btn--sm gp-btn--danger" x-on:click="removeMine(p)" x-bind:disabled="isDeleting(p)">{{ __('guest.mine_delete') }}</button>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
        <div class="gp-bar gp-bar--row">
            <button type="button" class="gp-btn" x-on:click="loadMine()" x-bind:disabled="mineLoading">{{ __('guest.mine_refresh') }}</button>
            <button type="button" class="gp-btn gp-btn--primary" x-on:click="closeMine()">{{ __('guest.back') }}</button>
        </div>
    </section>

    {{-- evento encerrado (resposta HTTP 410 no envio) --}}
    <section class="gp-step gp-step--full" data-step="ended" x-show="isStep_ended">
        <div class="gp-scroll gp-scroll--center">
            <h1 class="gp-title" tabindex="-1" data-step-heading>{{ __('guest.error_ended') }}</h1>
        </div>
    </section>
</main>
