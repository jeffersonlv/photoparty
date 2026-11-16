<?php

namespace App\Http\Requests;

use App\Models\Event;
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
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ask_guest_name' => $this->boolean('ask_guest_name'),
        ]);
    }

    public function rules(): array
    {
        return [
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
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('app.fields.event_name'),
            'starts_at' => __('app.fields.starts_at'),
            'ends_at' => __('app.fields.ends_at'),
            'moderation' => __('app.fields.moderation'),
            'welcome_message' => __('app.fields.welcome_message'),
            'max_photos_per_guest' => __('app.fields.max_photos_per_guest'),
            'enabled_filters' => __('app.fields.enabled_filters'),
            'language' => __('app.fields.language'),
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
        });
    }
}
