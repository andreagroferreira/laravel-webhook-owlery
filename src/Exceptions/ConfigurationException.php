<?php

namespace WizardingCode\WebhookOwlery\Exceptions;

use Throwable;

class ConfigurationException extends OwleryException
{
    protected ?string $configKey;

    /**
     * Create a new configuration exception instance.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Invalid webhook configuration',
        ?string $configKey = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->configKey = $configKey;

        $fullMessage = $message;

        if ($configKey) {
            $fullMessage .= " for configuration key '{$configKey}'";
        }

        parent::__construct($fullMessage, $code, $previous);
    }

    /**
     * Get the configuration key.
     */
    final public function getConfigKey(): ?string
    {
        return $this->configKey;
    }

    /**
     * Create a new exception for a missing configuration.
     *
     * @return static
     */
    public static function missingConfig(string $configKey, int $code = 0, ?Throwable $previous = null): self
    {
        return new static('Missing required configuration', $configKey, $code, $previous);
    }

    /**
     * Create a new exception for an invalid configuration value.
     *
     * @return static
     */
    public static function invalidValue(string $configKey, ?string $details = null, int $code = 0, ?Throwable $previous = null): self
    {
        $message = 'Invalid configuration value';
        if ($details) {
            $message .= ": {$details}";
        }

        return new static($message, $configKey, $code, $previous);
    }
}
