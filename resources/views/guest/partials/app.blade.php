{{--
    Marcacao do app do convidado. Somente textos via __() e diretivas x-* do Alpine
    (x-on:/x-bind: em vez de @ e : para nao conflitar com o Blade).
    Compativel com CSP estrita (@alpinejs/csp, sem unsafe-eval): so identificadores, membros
    e chamadas simples. Ternarios, negacoes, comparacoes e atribuicoes ficam no componente.
    A demo estatica (demo/index.html) injeta este mesmo arquivo trocando os __() pelo pt_BR.
--}}
<main class="gp" x-data="guestApp" x-cloak>
    <header class="gp-header">
        <button type="button" class="gp-iconbtn" x-show="canGoBack" x-on:click="back()" aria-label="{{ __('guest.back') }}">
            <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <p class="gp-event" x-show="showHeaderEvent" x-text="cfg.name"></p>
    </header>

    {{-- anuncia mudancas importantes a leitores de tela --}}
    <p class="gp-sr" role="status" aria-live="polite" x-text="liveMessage"></p>

    {{-- 1. boas-vindas + consentimento --}}
    <section class="gp-step" data-step="welcome" x-show="isStep_welcome">
        <h1 class="gp-title" tabindex="-1" data-step-heading x-text="cfg.name"></h1>
        <p class="gp-lead" x-text="welcomeText"></p>
        <div class="gp-card">
            <h2 class="gp-h2">{{ __('guest.consent_title') }}</h2>
            <p class="gp-small">{{ __('guest.consent_text') }}</p>
            <label class="gp-check">
                <input type="checkbox" x-model="consent">
                <span>{{ __('guest.consent_check') }}</span>
            </label>
        </div>
        <button type="button" class="gp-btn gp-btn--primary" x-on:click="accept()" x-bind:disabled="cannotAccept">{{ __('guest.start') }}</button>
    </section>

    {{-- 2. camera ou galeria --}}
    <section class="gp-step" data-step="source" x-show="isStep_source">
        <h1 class="gp-title" tabindex="-1" data-step-heading>{{ __('guest.source_title') }}</h1>

        <div class="gp-camera" x-show="showCamera">
            <video x-ref="video" class="gp-video" x-bind:class="videoClass" playsinline muted aria-label="{{ __('guest.camera_preview') }}"></video>
            <p class="gp-camera-msg" x-show="isCameraStarting" x-text="cameraMessage"></p>
        </div>

        <p class="gp-alert" role="alert" x-show="showCameraAlert" x-text="cameraMessage"></p>
        <p class="gp-alert" role="alert" x-show="fileError" x-text="fileError"></p>

        <div class="gp-actions">
            <button type="button" class="gp-btn gp-btn--primary gp-btn--shutter" x-show="isCameraLive" x-on:click="capture()">{{ __('guest.take_photo') }}</button>
            <button type="button" class="gp-btn" x-show="isCameraLive" x-on:click="switchCamera()">{{ __('guest.switch_camera') }}</button>
            {{-- fallback: camera nativa do celular quando getUserMedia nao esta disponivel --}}
            <button type="button" class="gp-btn gp-btn--primary" x-show="isCameraUnavailable" x-on:click="pickFile('capture')">{{ __('guest.take_with_phone') }}</button>
            <button type="button" class="gp-btn" x-on:click="pickFile('gallery')">{{ __('guest.from_gallery') }}</button>
        </div>
        <input type="file" accept="image/*" capture="environment" class="gp-hidden" x-ref="fileCapture" x-on:change="onFile($event)" tabindex="-1">
        <input type="file" accept="image/*" class="gp-hidden" x-ref="fileGallery" x-on:change="onFile($event)" tabindex="-1">
    </section>

    {{-- palco compartilhado (moldura, filtro, ajuste, textos e previa) --}}
    <div class="gp-stage" x-show="showStage" x-bind:data-orientation="orientation">
        <canvas class="gp-canvas" x-ref="stage" role="img" aria-label="{{ __('guest.stage_label') }}" x-bind:tabindex="stageTabindex"></canvas>
        <p class="gp-stage-busy" x-show="frameLoading">{{ __('guest.loading') }}</p>
    </div>

    {{-- 3. moldura --}}
    <section class="gp-step" data-step="frame" x-show="isStep_frame">
        <h1 class="gp-title" tabindex="-1" data-step-heading>{{ __('guest.frame_title') }}</h1>
        <p class="gp-alert" role="alert" x-show="frameError" x-text="frameError"></p>
        <div class="gp-chips" role="radiogroup" aria-label="{{ __('guest.frame_title') }}">
            <button type="button" class="gp-chip" role="radio" x-bind:aria-checked="noFrameSelected" x-bind:class="noFrameClass" x-on:click="chooseNoFrame()">{{ __('guest.no_frame') }}</button>
            <template x-for="f in frameOptions" x-bind:key="f.key">
                <button type="button" class="gp-chip" role="radio" x-bind:aria-checked="isFrameOn(f)" x-bind:class="frameClass(f)" x-on:click="chooseFrame(f)" x-text="f.name"></button>
            </template>
        </div>
        <button type="button" class="gp-btn gp-btn--primary" x-on:click="next()" x-bind:disabled="frameLoading">{{ __('guest.next') }}</button>
    </section>

    {{-- 4. filtro --}}
    <section class="gp-step" data-step="filter" x-show="isStep_filter">
        <h1 class="gp-title" tabindex="-1" data-step-heading>{{ __('guest.filter_title') }}</h1>
        <div class="gp-chips" role="radiogroup" aria-label="{{ __('guest.filter_title') }}">
            <template x-for="f in filterOptions" x-bind:key="f.key">
                <button type="button" class="gp-chip" role="radio" x-bind:aria-checked="isFilterOn(f)" x-bind:class="filterClass(f)" x-on:click="chooseFilter(f)" x-text="f.label"></button>
            </template>
        </div>
        <button type="button" class="gp-btn gp-btn--primary" x-on:click="next()">{{ __('guest.next') }}</button>
    </section>

    {{-- 5. ajuste (arrastar / pinca / zoom) --}}
    <section class="gp-step" data-step="adjust" x-show="isStep_adjust">
        <h1 class="gp-title" tabindex="-1" data-step-heading>{{ __('guest.adjust_title') }}</h1>
        <p class="gp-small">{{ __('guest.adjust_hint') }}</p>
        <label class="gp-range">
            <span>{{ __('guest.zoom') }}</span>
            <input type="range" min="1" max="4" step="0.01" x-bind:value="view.zoom" x-on:input="onZoomInput($event)">
        </label>
        <button type="button" class="gp-btn" x-on:click="flipOrientation()">
            {{ __('guest.flip_orientation') }}: <span x-text="orientationLabel"></span>
        </button>
        <button type="button" class="gp-btn gp-btn--primary" x-on:click="next()">{{ __('guest.next') }}</button>
    </section>

    {{-- 6. textos do convidado --}}
    <section class="gp-step" data-step="texts" x-show="isStep_texts">
        <h1 class="gp-title" tabindex="-1" data-step-heading>{{ __('guest.texts_title') }}</h1>
        <template x-if="cfg.askGuestName">
            <label class="gp-field">
                <span>{{ __('guest.guest_name_label') }} <small>{{ __('guest.guest_name_optional') }}</small></span>
                <input type="text" maxlength="60" autocomplete="name" x-bind:value="guestName" x-on:input="onNameInput($event)">
            </label>
        </template>
        <template x-for="field in extraFields" x-bind:key="field.id">
            <label class="gp-field">
                <span x-text="field.label"></span>
                <input type="text" autocomplete="off" x-bind:maxlength="field.maxChars" x-bind:placeholder="field.placeholder" x-bind:value="fieldValue(field)" x-on:input="onFieldInput($event, field)">
                <small x-text="charsLeftText(field)"></small>
            </label>
        </template>
        <button type="button" class="gp-btn gp-btn--primary" x-on:click="next()">{{ __('guest.next') }}</button>
    </section>

    {{-- 7. previa + envio --}}
    <section class="gp-step" data-step="preview" x-show="isStep_preview">
        <h1 class="gp-title" tabindex="-1" data-step-heading>{{ __('guest.preview_title') }}</h1>
        <p class="gp-alert" role="alert" x-show="error" x-text="error"></p>
        <div class="gp-progress" x-show="sending">
            <progress max="100" x-bind:value="progress" aria-label="{{ __('guest.progress_label') }}"></progress>
            <p class="gp-small" x-text="progressText"></p>
        </div>
        <button type="button" class="gp-btn gp-btn--primary" x-on:click="submit()" x-bind:disabled="sending">
            <span x-show="showSendLabel">{{ __('guest.send') }}</span>
            <span x-show="showRetryLabel">{{ __('guest.try_again') }}</span>
            <span x-show="sending">{{ __('guest.sending') }}</span>
        </button>
    </section>

    {{-- 8. sucesso --}}
    <section class="gp-step gp-step--center" data-step="done" x-show="isStep_done">
        <svg class="gp-check-icon" viewBox="0 0 24 24" width="72" height="72" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor" opacity=".18"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <h1 class="gp-title" tabindex="-1" data-step-heading>{{ __('guest.done_title') }}</h1>
        <p class="gp-lead" x-text="doneMessage"></p>
        <button type="button" class="gp-btn gp-btn--primary" x-on:click="another()">{{ __('guest.another') }}</button>
    </section>

    {{-- evento encerrado (resposta HTTP 410 no envio) --}}
    <section class="gp-step gp-step--center" data-step="ended" x-show="isStep_ended">
        <h1 class="gp-title" tabindex="-1" data-step-heading>{{ __('guest.error_ended') }}</h1>
    </section>
</main>
