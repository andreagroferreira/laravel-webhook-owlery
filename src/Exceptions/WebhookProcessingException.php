<?php

namespace WizardingCode\WebhookOwlery\Exceptions;

use Throwable;

class WebhookProcessingException extends OwleryException
{
    protected string $source;

    protected string $event;

    protected ?string $eventId;

    protected mixed $payload;

    /**
     * Create a new webhook processing exception instance.
     *
     * @return void
     */
    public function __construct(
        string $source,
        string $event,
        ?string $eventId = null,
        mixed $payload = null,
        string $message = 'Error processing webhook',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->source = $source;
        $this->event = $event;
        $this->eventId = $eventId;
        $this->payload = $payload;

        $fullMessage = "{$message} ({$source}.{$event})";

        if ($eventId) {
            $fullMessage .= " [ID: {$eventId}]";
        }

        parent::__construct($fullMessage, $code, $previous);
    }

    /**
     * Get the source of the webhook.
     */
    final public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Get the event type.
     */
    final public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * Get the event ID.
     */
    final public function getEventId(): ?string
    {
        return $this->eventId;
    }

    /**
     * Get the payload.
     */
    final public function getPayload(): mixed
    {
        return $this->payload;
    }

    /**
     * Report the exception.
     */
    public function report(): ?bool
    {
        // We might want to log these errors differently in production
        return true;
    }
}
