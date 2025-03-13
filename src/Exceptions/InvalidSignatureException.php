<?php

namespace WizardingCode\WebhookOwlery\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class InvalidSignatureException extends OwleryException
{
    protected string $source;

    protected ?string $signature;

    /**
     * Create a new invalid signature exception instance.
     *
     * @return void
     */
    public function __construct(
        string $source,
        ?string $signature = null,
        string $message = 'Invalid webhook signature',
        int $code = 401,
        ?Throwable $previous = null
    ) {
        $this->source = $source;
        $this->signature = $signature;

        $fullMessage = $message;
        if ($source) {
            $fullMessage .= " for source '{$source}'";
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
     * Get the signature that was provided.
     */
    final public function getSignature(): ?string
    {
        return $this->signature;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'error' => 'invalid_signature',
                'source' => $this->source,
                'status' => 'error',
                'code' => $this->getCode(),
            ], $this->getCode());
        }

        return response($this->getMessage(), $this->getCode());
    }
}
