<?php

namespace WizardingCode\WebhookOwlery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

class WebhookSubscription extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'uuid',
        'webhook_endpoint_id',
        'endpoint_id', // For test compatibility
        'description',
        'event_type',
        'event_types', // For test compatibility
        'event_filters',
        'is_active',
        'activated_at',
        'deactivated_at',
        'expires_at',
        'max_deliveries',
        'delivery_count',
        'metadata',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'event_filters' => 'array',
        'event_types' => 'array', // For test compatibility
        'metadata' => 'array',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'expires_at' => 'datetime',
        'max_deliveries' => 'integer',
        'delivery_count' => 'integer',
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

            if ($model->is_active && ! $model->activated_at) {
                $model->activated_at = now();
            }

            // Ensure both endpoint_id fields are in sync for test compatibility
            if (! empty($model->webhook_endpoint_id) && empty($model->endpoint_id)) {
                $model->endpoint_id = $model->webhook_endpoint_id;
            } elseif (! empty($model->endpoint_id) && empty($model->webhook_endpoint_id)) {
                $model->webhook_endpoint_id = $model->endpoint_id;
            }
        });

        static::created(static function ($model) {
            // Dispatch creation event
            event(new \WizardingCode\WebhookOwlery\Events\WebhookSubscriptionCreated($model));
        });

        static::updating(static function ($model) {
            // Track activation/deactivation
            if ($model->isDirty('is_active')) {
                if ($model->is_active) {
                    $model->activated_at = now();
                    $model->deactivated_at = null;
                } else {
                    $model->deactivated_at = now();
                }
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
     * Get the endpoint associated with this subscription.
     */
    final public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * Scope a query to only include active subscriptions.
     */
    final public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) {
                $query->whereNull('max_deliveries')
                    ->orWhereRaw('delivery_count < max_deliveries');
            });
    }

    /**
     * Scope a query to only include inactive subscriptions.
     */
    final public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to only include expired subscriptions.
     */
    final public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope a query to only include subscriptions for a specific event type.
     */
    final public function scopeForEventType(Builder $query, string $eventType): Builder
    {
        return $query->where(function ($query) use ($eventType) {
            // Exact matches
            $query->where('event_type', $eventType)
                // Wildcard patterns (e.g., "payment.*" would match "payment.succeeded")
                ->orWhere(function ($q) use ($eventType) {
                    $q->whereRaw("? LIKE REPLACE(event_type, '*', '%')", [$eventType])
                        ->where('event_type', 'LIKE', '%*%');
                });
        });
    }

    /**
     * Scope a query to only include subscriptions for a specific endpoint.
     */
    final public function scopeForEndpoint(Builder $query, string $endpointId): Builder
    {
        return $query->where('webhook_endpoint_id', $endpointId);
    }

    /**
     * Check if this subscription matches an event.
     */
    final public function matchesEvent(string $eventType, array $eventData = []): bool
    {
        // Check if subscription is active
        if (! $this->is_active) {
            return false;
        }

        // Check if subscription is expired
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Check if max deliveries reached
        if ($this->max_deliveries !== null && $this->delivery_count >= $this->max_deliveries) {
            return false;
        }

        // Check event type match
        $matches = false;

        // Exact match
        if ($this->event_type === $eventType) {
            $matches = true;
        }
        // Wildcard match (e.g., "payment.*" would match "payment.succeeded")
        elseif (str_ends_with($this->event_type, '*')) {
            $prefix = substr($this->event_type, 0, -1);
            if (str_starts_with($eventType, $prefix)) {
                $matches = true;
            }
        }

        // If event type doesn't match, no need to check filters
        if (! $matches) {
            return false;
        }

        // Check event filters if they exist
        if (! empty($this->event_filters) && ! empty($eventData)) {
            return $this->matchesFilters($eventData);
        }

        return true;
    }

    /**
     * Check if the event data matches the subscription filters.
     */
    private function matchesFilters(array $eventData): bool
    {
        // Simple implementation - can be expanded for more complex filter logic
        foreach ($this->event_filters as $key => $value) {
            // Skip if the key doesn't exist in the event data
            if (! array_key_exists($key, $eventData)) {
                return false;
            }

            // Check for equality
            if (is_scalar($value) && $eventData[$key] !== $value) {
                return false;
            }

            // Check for array contains
            if (is_array($value) && (! is_array($eventData[$key]) || ! in_array($eventData[$key], $value, true))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Increment the delivery count.
     */
    final public function incrementDeliveryCount(): self
    {
        $this->increment('delivery_count');

        return $this;
    }

    /**
     * Activate the subscription.
     */
    final public function activate(): self
    {
        if (! $this->is_active) {
            $this->is_active = true;
            $this->activated_at = now();
            $this->deactivated_at = null;
            $this->save();
        }

        return $this;
    }

    /**
     * Deactivate the subscription.
     */
    final public function deactivate(): self
    {
        if ($this->is_active) {
            $this->is_active = false;
            $this->deactivated_at = now();
            $this->save();
        }

        return $this;
    }

    /**
     * Set an expiration date for the subscription.
     */
    final public function expiresAt(\DateTime $date): self
    {
        $this->expires_at = $date;
        $this->save();

        return $this;
    }

    /**
     * Check if the subscription is expired.
     */
    final public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the subscription has reached its max deliveries.
     */
    final public function hasReachedMaxDeliveries(): bool
    {
        return $this->max_deliveries !== null && $this->delivery_count >= $this->max_deliveries;
    }
}
