<?php

namespace WizardingCode\WebhookOwlery\Exceptions;

use Throwable;

class EndpointException extends OwleryException
{
    protected ?string $endpointId;

    protected ?string $url;

    /**
     * Create a new endpoint exception instance.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Webhook endpoint error',
        ?string $endpointId = null,
        ?string $url = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->endpointId = $endpointId;
        $this->url = $url;

        $fullMessage = $message;

        if ($endpointId) {
            $fullMessage .= " (endpoint ID: {$endpointId})";
        }

        if ($url) {
            $fullMessage .= " for URL '{$url}'";
        }

        parent::__construct($fullMessage, $code, $previous);
    }

    /**
     * Get the endpoint ID.
     */
    final public function getEndpointId(): ?string
    {
        return $this->endpointId;
    }

    /**
     * Get the URL.
     */
    final public function getUrl(): ?string
    {
        return $this->url;
    }
}
