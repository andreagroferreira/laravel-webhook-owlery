<?php

namespace WizardingCode\WebhookOwlery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebhookReceiveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    final public function authorize(): bool
    {
        // Authorization is handled by middleware or within the handler
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    final public function rules(): array
    {
        // For webhooks, we generally accept any input, as the structure
        // depends on the external provider. Validation happens during processing.
        return [];
    }

    /**
     * Get the source of the webhook.
     *
     * Can be overridden by provider-specific classes.
     */
    final public function getSource(): string
    {
        // Default to route parameter or a fallback
        return $this->route('source') ?? 'default';
    }

    /**
     * Get the event type from the webhook payload.
     *
     * Can be overridden by provider-specific classes.
     */
    final public function getEventType(): ?string
    {
        // Try to find the event type in common locations
        $eventFields = ['event', 'type', 'event_type', 'action', 'name'];

        foreach ($eventFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                return $this->input($field);
            }
        }

        // Check X-Webhook-Event header
        if ($this->header('X-Webhook-Event')) {
            return $this->header('X-Webhook-Event');
        }

        return null;
    }

    /**
     * Get the signature from the request.
     *
     * Can be overridden by provider-specific classes.
     */
    final public function getSignature(): ?string
    {
        // Check common signature header locations
        $signatureHeaders = [
            'X-Hub-Signature',
            'X-Webhook-Signature',
            'X-Signature',
            'X-Signature-256',
            'X-Stripe-Signature',
            'X-GitHub-Signature',
            'X-Shopify-Hmac-SHA256',
        ];

        foreach ($signatureHeaders as $header) {
            if ($this->header($header)) {
                return $this->header($header);
            }
        }

        return null;
    }

    /**
     * Check if the request has a signature.
     */
    final public function hasSignature(): bool
    {
        return $this->getSignature() !== null;
    }

    /**
     * Get the raw body of the request.
     *
     * This is important for signature verification which often uses the raw body.
     */
    final public function getRawBody(): string
    {
        return $this->getContent();
    }

    /**
     * Check if this request is a valid webhook.
     *
     * Basic checks before detailed validation.
     */
    final public function isValidWebhook(): bool
    {
        // Make sure there's either a JSON payload or form data
        return $this->isJson() || ! empty($this->all());
    }
}
