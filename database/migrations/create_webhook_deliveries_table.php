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
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('webhook_endpoint_id')
                ->nullable()
                ->constrained('webhook_endpoints')
                ->nullOnDelete()
                ->comment('The endpoint this delivery was sent to');

            // Add these for test compatibility
            $table->foreignId('subscription_id')->nullable()->comment('For test compatibility');
            $table->foreignId('event_id')->nullable()->comment('For test compatibility');
            $table->string('destination')->comment('URL where webhook was sent');
            $table->string('event')->comment('Event type that triggered this webhook');
            $table->json('payload')->nullable()->comment('Webhook payload');
            $table->json('headers')->nullable()->comment('Headers sent with the webhook');
            $table->string('signature')->nullable()->comment('Signature sent with the webhook');
            $table->string('status')->default('pending')->index()->comment('pending, success, failed, retrying');
            $table->integer('attempt')->default(1)->comment('Current attempt number');
            $table->integer('max_attempts')->default(3)->comment('Maximum number of attempts');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->integer('status_code')->nullable()->comment('HTTP status code received');
            $table->integer('response_code')->nullable()->comment('Alias for status_code (test compatibility)');
            $table->text('response_body')->nullable()->comment('Response received');
            $table->json('response_headers')->nullable()->comment('Response headers received');
            $table->integer('response_time_ms')->nullable()->comment('Response time in milliseconds');
            $table->integer('response_time')->nullable()->comment('Alias for response_time_ms (test compatibility)');
            $table->text('error_message')->nullable()->comment('Error message if delivery failed');
            $table->text('error_detail')->nullable()->comment('Detailed error information');
            $table->boolean('success')->nullable()->comment('Whether delivery was successful');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            $table->timestamps();

            // Indexes for better performance
            $table->index(['status', 'next_attempt_at']);
            $table->index(['created_at']);
            $table->index(['webhook_endpoint_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
