<?php

namespace WizardingCode\WebhookOwlery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookEndpointRequest extends FormRequest
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
        $isUpdate = $this->route('endpoint') !== null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:1000'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
            'retry_limit' => ['nullable', 'integer', 'min:0', 'max:10'],
            'retry_interval' => ['nullable', 'integer', 'min:5', 'max:86400'],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['string', 'max:1000'],
            'secret' => ['nullable', 'string', 'max:255'],
            'signature_algorithm' => ['nullable', 'string', Rule::in(['sha256', 'sha512', 'md5'])],
            'metadata' => ['nullable', 'array'],
        ];

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     */
    final public function attributes(): array
    {
        return [
            'events.*' => 'event',
            'headers.*' => 'header value',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    final public function messages(): array
    {
        return [
            'url.url' => 'The endpoint URL must be a valid URL with http:// or https:// protocol.',
            'signature_algorithm.in' => 'The signature algorithm must be one of: sha256, sha512, md5.',
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

        if ($this->has('events') && is_string($this->events)) {
            $this->merge([
                'events' => json_decode($this->events, true, 512, JSON_THROW_ON_ERROR) ?? [],
            ]);
        }

        if ($this->has('headers') && is_string($this->headers)) {
            $this->merge([
                'headers' => json_decode($this->headers, true, 512, JSON_THROW_ON_ERROR) ?? [],
            ]);
        }

        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true, 512, JSON_THROW_ON_ERROR) ?? [],
            ]);
        }
    }
}
