<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id(); $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160); $table->string('slug', 190)->unique(); $table->text('summary'); $table->longText('story');
            $table->unsignedBigInteger('goal_minor'); $table->dateTime('deadline'); $table->string('image_path')->nullable();
            $table->boolean('allows_anonymous')->default(true); $table->string('status', 24)->default('pending_review'); $table->timestamps();
            $table->index(['status', 'deadline']); $table->index(['owner_id', 'created_at']);
        });
        Schema::create('donations', function (Blueprint $table) {
            $table->id(); $table->string('reference', 24)->unique(); $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('idempotency_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 100)->nullable(); $table->unsignedBigInteger('amount_minor'); $table->string('status', 20)->default('pending');
            $table->boolean('is_anonymous')->default(true); $table->string('public_name', 80)->nullable(); $table->boolean('show_amount')->default(false);
            $table->string('payment_method', 20)->default('manual'); $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable(); $table->timestamp('confirmed_at')->nullable(); $table->timestamps();
            $table->unique(['idempotency_user_id', 'idempotency_key']); $table->index(['campaign_id', 'status', 'created_at']); $table->index(['user_id', 'created_at']);
        });
        Schema::create('campaign_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('campaign_id')->constrained()->restrictOnDelete(); $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 24)->nullable(); $table->string('to_status', 24); $table->text('note')->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['campaign_id', 'created_at']);
        });
        Schema::create('donation_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('donation_id')->constrained()->restrictOnDelete(); $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20)->nullable(); $table->string('to_status', 20); $table->text('note')->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['donation_id', 'created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('donation_events'); Schema::dropIfExists('campaign_events'); Schema::dropIfExists('donations'); Schema::dropIfExists('campaigns');
    }
};
