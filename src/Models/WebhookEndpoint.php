<?php

namespace WizardingCode\WebhookOwlery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'description',
        'url',
        'source',
        'events',
        'secret',
        'signature_algorithm',
        'is_active',
        'timeout',
        'retry_limit',
        'retry_interval',
        'headers',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'timeout' => 'integer',
        'retry_limit' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function ($model) {
            // Always set a UUID
            $model->uuid = (string) Str::uuid();

            // Set default values for required fields if not provided
            if (empty($model->name)) {
                $model->name = 'Endpoint ' . Str::random(8);
            }

            if (empty($model->source)) {
                $model->source = 'system';
            }

            if (empty($model->events)) {
                $model->events = [];
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    final public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the subscriptions for this endpoint.
     */
    final public function subscriptions(): HasMany
    {
        return $this->hasMany(WebhookSubscription::class);
    }

    /**
     * Get the deliveries for this endpoint.
     */
    final public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Parse the retry intervals into an array.
     */
    final public function getRetryIntervalsAttribute(): array
    {
        return array_map('intval', explode(',', $this->retry_interval));
    }

    /**
     * Get the retry interval for a specific attempt.
     */
    final public function getRetryIntervalForAttempt(int $attempt): int
    {
        $intervals = $this->retry_intervals;
        $index = $attempt - 1;

        return $intervals[$index] ?? (end($intervals) ?: 30); // Default to 30 seconds if no intervals defined
    }

    /**
     * Determine if the endpoint supports a specific event type.
     */
    final public function supportsEvent(string $eventType): bool
    {
        // An empty events array means all events are supported
        if (empty($this->events)) {
            return true;
        }

        // Check for exact match
        if (in_array($eventType, $this->events, true)) {
            return true;
        }

        // Check for wildcard matches (e.g., "payment.*" would match "payment.succeeded")
        foreach ($this->events as $event) {
            if (str_ends_with($event, '*') && str_starts_with($eventType, substr($event, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope a query to only include active endpoints.
     */
    final public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to endpoints for a specific source.
     */
    final public function scopeForSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Scope a query to endpoints that support a specific event type.
     */
    final public function scopeForEvent(Builder $query, string $eventType): Builder
    {
        return $query->where(function ($query) use ($eventType) {
            // Endpoints with empty events array (all events)
            $query->whereJsonLength('events', 0)
                // Endpoints with exact event match
                ->orWhereJsonContains('events', $eventType)
                // Endpoints with wildcard patterns (more complex, may need custom logic)
                ->orWhere(function ($q) use ($eventType) {
                    // This is a simplified approach, may need refinement for production
                    $parts = explode('.', $eventType);
                    if (count($parts) > 1) {
                        $prefix = $parts[0];
                        $q->whereJsonContains('events', $prefix . '.*');
                    }
                });
        });
    }
}
