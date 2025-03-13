<?php

namespace WizardingCode\WebhookOwlery\Http\Requests\Providers;

use WizardingCode\WebhookOwlery\Http\Requests\WebhookReceiveRequest;

class StripeWebhookRequest extends WebhookReceiveRequest
{
    /**
     * Get the source of the webhook.
     */
    final public function getSource(): string
    {
        return 'stripe';
    }

    /**
     * Get the event type from the webhook payload.
     */
    final public function getEventType(): ?string
    {
        // Stripe uses 'type' for event names
        if ($this->has('type') && is_string($this->input('type'))) {
            return $this->input('type');
        }

        return parent::getEventType();
    }

    /**
     * Get the signature from the request.
     */
    final public function getSignature(): ?string
    {
        // Stripe uses 'Stripe-Signature' header
        return $this->header('Stripe-Signature');
    }

    /**
     * Get the timestamp from the signature.
     */
    final public function getTimestamp(): ?int
    {
        $signature = $this->getSignature();

        if (! $signature) {
            return null;
        }

        // Stripe signature format: t=1492774577,v1=5257a869e7ecebeda32affa62cdca3fa51cad7e77a0e56ff536d0ce8e108d8bd
        if (preg_match('/t=(\d+)/', $signature, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Check if the timestamp is valid (not too old).
     *
     * @param int $tolerance Tolerance in seconds
     */
    final public function isTimestampValid(int $tolerance = 300): bool
    {
        $timestamp = $this->getTimestamp();

        if (! $timestamp) {
            return false;
        }

        // Check if the timestamp is not too old
        $now = time();

        return ($now - $timestamp) <= $tolerance;
    }

    /**
     * Extract the object ID from the Stripe event.
     */
    final public function getObjectId(): ?string
    {
        if (! $this->has('data.object.id')) {
            return null;
        }

        return $this->input('data.object.id');
    }

    /**
     * Extract the object type from the Stripe event.
     */
    final public function getObjectType(): ?string
    {
        if (! $this->has('data.object.object')) {
            return null;
        }

        return $this->input('data.object.object');
    }
}
