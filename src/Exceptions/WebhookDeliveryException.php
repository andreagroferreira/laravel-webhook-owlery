<?php

namespace WizardingCode\WebhookOwlery\Exceptions;

use Throwable;

class WebhookDeliveryException extends OwleryException
{
    protected ?string $destination;

    protected ?string $deliveryId;

    protected ?int $statusCode;

    protected ?string $responseBody;

    /**
     * Create a new webhook delivery exception instance.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Webhook delivery failed',
        ?string $destination = null,
        ?string $deliveryId = null,
        ?int $statusCode = null,
        ?string $responseBody = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->destination = $destination;
        $this->deliveryId = $deliveryId;
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;

        $fullMessage = $message;

        if ($destination) {
            $fullMessage .= " to '{$destination}'";
        }

        if ($statusCode) {
            $fullMessage .= " with status code {$statusCode}";
        }

        if ($deliveryId) {
            $fullMessage .= " (delivery ID: {$deliveryId})";
        }

        parent::__construct($fullMessage, $code, $previous);
    }

    /**
     * Get the destination URL.
     */
    final public function getDestination(): ?string
    {
        return $this->destination;
    }

    /**
     * Get the delivery ID.
     */
    final public function getDeliveryId(): ?string
    {
        return $this->deliveryId;
    }

    /**
     * Get the HTTP status code.
     */
    final public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Get the response body.
     */
    final public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
