<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Event;
use App\Services\Geofence\CoordinateParser;
use App\Services\Geofence\InvalidCoordinates;
use App\Services\EventScheduler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Criação/edição de evento. Nunca aceita account_id, status, is_trial ou tokens do request.
 * A autorização fica na policy (chamada no controller).
 */
class EventRequest extends FormRequest
{
    /** Campos validados que não vão para fill() (declaração e geolocalização têm tratamento próprio). */
    public const NON_FILLABLE = ['image_authorization', 'geofence_enabled', 'geofence_radius_m', 'geofence_lat', 'geofence_lng', 'geofence_location'];

    /** Local resolvido no after() (campo colado ou botão "usar minha localização"). */
    private ?array $geofencePoint = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ask_guest_name' => $this->boolean('ask_guest_name'),
            'geofence_enabled' => $this->boolean('geofence_enabled'),
        ]);
    }

    public function rules(): array
    {
        // Criação: declaração obrigatória do organizador (controlador) de que tem autorização
        // para coletar imagens dos participantes, inclusive de menores (LGPD art. 14 / GDPR art. 8).
        $creating = ! ($this->route('event') instanceof Event);

        return [
            ...($creating ? ['image_authorization' => ['accepted']] : []),
            'name' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'moderation' => ['required', Rule::in(['pre', 'post'])],
            'welcome_message' => ['nullable', 'string', 'max:500'],
            'ask_guest_name' => ['boolean'],
            'max_photos_per_guest' => ['nullable', 'integer', 'min:1', 'max:100'],
            'enabled_filters' => ['nullable', 'array'],
            'enabled_filters.*' => ['string', Rule::in(config('photoparty.filters'))],
            'language' => ['required', Rule::in(config('photoparty.locales'))],
            // Geolocalização: só para conta com o recurso; sem ele os campos são ignorados.
            ...($this->geofenceAllowed() ? [
                'geofence_enabled' => ['boolean'],
                'geofence_radius_m' => [
                    'nullable', 'integer',
                    'min:'.(int) config('photoparty.geofence.radius_min'),
                    'max:'.(int) config('photoparty.geofence.radius_max'),
                ],
                'geofence_lat' => ['nullable', 'numeric', 'between:-90,90'],
                'geofence_lng' => ['nullable', 'numeric', 'between:-180,180'],
                'geofence_location' => ['nullable', 'string', 'max:2000'],
            ] : []),
        ];
    }

    public function geofenceAllowed(): bool
    {
        return (bool) $this->eventAccount()?->canUseGeofence();
    }

    /**
     * Atributos de geofence a gravar no evento. Conta sem o recurso => [] (o servidor ignora
     * o que vier do formulário e não mexe na configuração existente).
     *
     * @return array<string, mixed>
     */
    public function geofenceAttributes(): array
    {
        if (! $this->geofenceAllowed()) {
            return [];
        }

        $attributes = [
            'geofence_enabled' => (bool) $this->validated('geofence_enabled', false),
            'geofence_radius_m' => (int) ($this->validated('geofence_radius_m') ?? config('photoparty.geofence.radius_default')),
        ];

        if ($this->geofencePoint !== null) {
            $attributes['geofence_lat'] = $this->geofencePoint['lat'];
            $attributes['geofence_lng'] = $this->geofencePoint['lng'];
        }

        return $attributes;
    }

    private function eventAccount(): ?Account
    {
        $event = $this->route('event');

        return $event instanceof Event ? $event->account : $this->user()?->account;
    }

    public function attributes(): array
    {
        return [
            'image_authorization' => __('app.fields.image_authorization'),
            'name' => __('app.fields.event_name'),
            'starts_at' => __('app.fields.starts_at'),
            'ends_at' => __('app.fields.ends_at'),
            'moderation' => __('app.fields.moderation'),
            'welcome_message' => __('app.fields.welcome_message'),
            'max_photos_per_guest' => __('app.fields.max_photos_per_guest'),
            'enabled_filters' => __('app.fields.enabled_filters'),
            'language' => __('app.fields.language'),
            'geofence_radius_m' => __('app.geofence.radius'),
            'geofence_lat' => __('app.geofence.latitude'),
            'geofence_lng' => __('app.geofence.longitude'),
            'geofence_location' => __('app.geofence.paste_label'),
        ];
    }

    /**
     * Regras de agenda e do teste grátis (2 h; janela de 3 h) validadas no servidor.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $event = $this->route('event');
            $account = $event instanceof Event ? $event->account : $this->user()->account;
            $isTrial = $event instanceof Event ? $event->is_trial : $account->isOnTrial();

            $errors = app(EventScheduler::class)->windowErrors(
                $account,
                $isTrial,
                Carbon::parse($this->input('starts_at')),
                Carbon::parse($this->input('ends_at')),
            );

            foreach ($errors as $field => $message) {
                $validator->errors()->add($field, $message);
            }

            $this->resolveGeofencePoint($validator);
        });
    }

    /**
     * Local do evento: o texto colado (link do Google Maps ou "lat, lng") tem prioridade; senão,
     * latitude/longitude do botão "Usar minha localização atual". Interpretado só no servidor,
     * sem chamadas externas (link curto => pede o link completo ou as coordenadas).
     */
    private function resolveGeofencePoint(Validator $validator): void
    {
        if (! $this->geofenceAllowed()) {
            return;
        }

        $parser = app(CoordinateParser::class);
        $pasted = trim((string) $this->input('geofence_location', ''));

        try {
            if ($pasted !== '') {
                $this->geofencePoint = $parser->parse($pasted);
            } elseif (filled($this->input('geofence_lat')) && filled($this->input('geofence_lng'))) {
                $this->geofencePoint = $parser->coordinates($this->input('geofence_lat'), $this->input('geofence_lng'));
            }
        } catch (InvalidCoordinates $e) {
            $validator->errors()->add('geofence_location', __('app.geofence.errors.'.$e->reason));

            return;
        }

        $event = $this->route('event');
        $hasSaved = $event instanceof Event && $event->hasGeofenceLocation();

        if ($this->boolean('geofence_enabled') && $this->geofencePoint === null && ! $hasSaved) {
            $validator->errors()->add('geofence_location', __('app.geofence.errors.required'));
        }
    }
}
