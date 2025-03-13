<?php

namespace WizardingCode\WebhookOwlery\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class OwleryException extends Exception
{
    /**
     * Create a new Owlery exception instance.
     *
     * @return void
     */
    public function __construct(string $message = 'An error occurred in the Webhook Owlery', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report the exception.
     */
    public function report(): ?bool
    {
        return true;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'error' => 'owlery_error',
                'status' => 'error',
                'code' => $this->getCode() ?: 500,
            ], $this->getCode() ?: 500);
        }

        return response()->view('errors.500', ['exception' => $this], 500);
    }
}
