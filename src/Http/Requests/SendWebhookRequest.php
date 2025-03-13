<?php

namespace WizardingCode\WebhookOwlery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendWebhookRequest extends FormRequest
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
        return [
            'url' => ['required_without:endpoint_id', 'nullable', 'url', 'max:2048'],
            'endpoint_id' => ['required_without:url', 'nullable', 'exists:webhook_endpoints,id'],
            'event' => ['required', 'string', 'max:255'],
            'payload' => ['required', 'array'],
            'options' => ['nullable', 'array'],
            'options.queue' => ['nullable', 'boolean'],
            'options.headers' => ['nullable', 'array'],
            'options.timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
            'options.max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'options.sign' => ['nullable', 'boolean'],
            'options.secret' => ['nullable', 'string'],
            'options.algorithm' => ['nullable', 'string', 'in:sha256,sha512,md5'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    final public function attributes(): array
    {
        return [
            'url' => 'destination URL',
            'endpoint_id' => 'webhook endpoint ID',
            'options.queue' => 'queue option',
            'options.headers' => 'custom headers',
            'options.timeout' => 'timeout',
            'options.max_attempts' => 'maximum attempts',
            'options.sign' => 'signature option',
            'options.secret' => 'secret key',
            'options.algorithm' => 'signature algorithm',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    final public function messages(): array
    {
        return [
            'url.required_without' => 'Either a destination URL or endpoint ID is required.',
            'endpoint_id.required_without' => 'Either a destination URL or endpoint ID is required.',
            'payload.required' => 'The webhook payload is required.',
            'options.algorithm.in' => 'The signature algorithm must be one of: sha256, sha512, md5.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @throws \JsonException
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('payload') && is_string($this->payload)) {
            $this->merge([
                'payload' => json_decode($this->payload, true, 512, JSON_THROW_ON_ERROR) ?? [],
            ]);
        }

        if ($this->has('options') && is_string($this->options)) {
            $this->merge([
                'options' => json_decode($this->options, true, 512, JSON_THROW_ON_ERROR) ?? [],
            ]);
        }

        // Convert string boolean values to actual booleans
        if ($this->has('options.queue') && is_string($this->input('options.queue'))) {
            $options = $this->input('options');
            $options['queue'] = $options['queue'] === 'true' || $options['queue'] === '1';
            $this->merge(['options' => $options]);
        }

        if ($this->has('options.sign') && is_string($this->input('options.sign'))) {
            $options = $this->input('options');
            $options['sign'] = $options['sign'] === 'true' || $options['sign'] === '1';
            $this->merge(['options' => $options]);
        }
    }
}
