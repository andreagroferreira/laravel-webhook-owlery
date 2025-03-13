<?php

namespace WizardingCode\WebhookOwlery\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use WizardingCode\WebhookOwlery\Models\WebhookSubscription;

class WebhookSubscriptionCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The webhook subscription that was created.
     */
    public WebhookSubscription $subscription;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(WebhookSubscription $subscription)
    {
        $this->subscription = $subscription;
    }
}
