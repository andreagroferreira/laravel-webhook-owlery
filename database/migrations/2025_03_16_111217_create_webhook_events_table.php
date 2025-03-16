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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->string('source')->index()->comment('The source service (stripe, github, etc)');
            $table->string('event')->index()->comment('The event type or name');
            $table->string('type')->nullable()->index()->comment('Alternative field for event type (test compatibility)');
            $table->json('payload')->nullable()->comment('The event payload');
            $table->json('headers')->nullable()->comment('Request headers received');
            $table->string('signature')->nullable()->comment('Signature received with the webhook');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('is_valid')->nullable()->comment('Signature validation result');
            $table->string('validation_message')->nullable()->comment('Message if validation failed');
            $table->boolean('is_processed')->default(false)->index();
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_status')->nullable()->comment('success, error, skipped');
            $table->text('processing_error')->nullable()->comment('Error message if processing failed');
            $table->integer('processing_time_ms')->nullable()->comment('Processing time in milliseconds');
            $table->string('processor_class')->nullable()->comment('Class that processed the event');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            $table->timestamps();

            // Indexes for better performance
            $table->index(['source', 'event']);
            $table->index(['created_at']);
            $table->index(['is_processed', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
