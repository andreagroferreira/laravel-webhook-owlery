<?php

namespace WizardingCode\WebhookOwlery\Http\Requests\Providers;

use WizardingCode\WebhookOwlery\Http\Requests\WebhookReceiveRequest;

class GitHubWebhookRequest extends WebhookReceiveRequest
{
    /**
     * Get the source of the webhook.
     */
    public function getSource(): string
    {
        return 'github';
    }

    /**
     * Get the event type from the webhook payload.
     */
    final public function getEventType(): ?string
    {
        // GitHub sends the event type in the X-GitHub-Event header
        if ($this->header('X-GitHub-Event')) {
            $event = $this->header('X-GitHub-Event');

            // If we have an action in the payload, combine it with the event
            if ($this->has('action') && is_string($this->input('action'))) {
                return $event . '.' . $this->input('action');
            }

            return $event;
        }

        return parent::getEventType();
    }

    /**
     * Get the signature from the request.
     */
    final public function getSignature(): ?string
    {
        // GitHub uses X-Hub-Signature-256 or X-Hub-Signature
        if ($signature = $this->header('X-Hub-Signature-256')) {
            return $signature;
        }

        if ($signature = $this->header('X-Hub-Signature')) {
            return $signature;
        }

        return null;
    }

    /**
     * Get the delivery ID from the request.
     */
    final public function getDeliveryId(): ?string
    {
        return $this->header('X-GitHub-Delivery');
    }

    /**
     * Check if this is a ping event.
     */
    final public function isPing(): bool
    {
        return $this->header('X-GitHub-Event') === 'ping';
    }

    /**
     * Get the repository name from the payload.
     */
    final public function getRepository(): ?string
    {
        if ($this->has('repository.full_name')) {
            return $this->input('repository.full_name');
        }

        return null;
    }

    /**
     * Get the sender username from the payload.
     */
    final public function getSender(): ?string
    {
        if ($this->has('sender.login')) {
            return $this->input('sender.login');
        }

        return null;
    }

    /**
     * Check if the event should be processed.
     */
    final public function shouldProcess(): bool
    {
        // Special case for ping events
        if ($this->isPing()) {
            return true;
        }

        // For other events, verify we have required data
        return $this->getEventType() !== null && $this->getRepository() !== null;
    }
}
