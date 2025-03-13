<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('webhook_endpoint_id')
                ->constrained('webhook_endpoints')
                ->cascadeOnDelete();
            $table->foreignId('endpoint_id')
                ->nullable()
                ->comment('Alias for webhook_endpoint_id for test compatibility');
            $table->string('description')->nullable()->comment('Description of this subscription');
            $table->string('event_type')->comment('Event type or pattern to subscribe to');
            $table->json('event_types')->nullable()->comment('Array of event types (for test compatibility)');
            $table->json('event_filters')->nullable()->comment('Filters to apply to events');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index()->comment('When this subscription expires');
            $table->integer('max_deliveries')->nullable()->comment('Maximum number of deliveries');
            $table->integer('delivery_count')->default(0)->comment('Current delivery count');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            $table->string('created_by')->nullable()->comment('User or system that created this subscription');
            $table->string('updated_by')->nullable()->comment('User or system that last updated this subscription');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['webhook_endpoint_id', 'is_active']);
            $table->index(['event_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
