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
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('url');
            $table->string('source')->comment('The service/app that owns this endpoint');
            $table->json('events')->comment('Array of event types this endpoint can receive');
            $table->string('secret')->nullable()->comment('Secret key for signing webhooks');
            $table->string('signature_algorithm')->default('sha256')->comment('Algorithm used for signatures');
            $table->boolean('is_active')->default(true);
            $table->integer('timeout')->default(30)->comment('Timeout in seconds');
            $table->integer('retry_limit')->default(3)->comment('Max retry attempts on failure');
            $table->string('retry_interval')->default('30,60,120')->comment('Retry intervals in seconds');
            $table->json('headers')->nullable()->comment('Custom headers to send');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['source', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
