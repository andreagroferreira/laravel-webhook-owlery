<?php

namespace WizardingCode\WebhookOwlery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebhookSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    final public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    final public function rules(): array
    {
        $isUpdate = $this->route('subscription') !== null;

        return [
            'webhook_endpoint_id' => $isUpdate ? ['nullable', 'exists:webhook_endpoints,id'] : ['required', 'exists:webhook_endpoints,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'event_type' => $isUpdate ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'event_filters' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'max_deliveries' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
            'created_by' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    final public function attributes(): array
    {
        return [
            'webhook_endpoint_id' => 'endpoint',
            'event_type' => 'event type',
            'event_filters' => 'event filters',
            'max_deliveries' => 'maximum deliveries',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    final public function messages(): array
    {
        return [
            'webhook_endpoint_id.exists' => 'The selected endpoint does not exist.',
            'expires_at.after' => 'The expiration date must be in the future.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @throws \JsonException
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('is_active') && is_string($this->is_active)) {
            $this->merge([
                'is_active' => $this->is_active === 'true' || $this->is_active === '1',
            ]);
        }

        if ($this->has('event_filters') && is_string($this->event_filters)) {
            $this->merge([
                'event_filters' => json_decode($this->event_filters, true, 512, JSON_THROW_ON_ERROR) ?? [],
            ]);
        }

        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true, 512, JSON_THROW_ON_ERROR) ?? [],
            ]);
        }
    }
}
