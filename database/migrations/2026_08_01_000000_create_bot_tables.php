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
        // Channels Table
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id')->unique()->index();
            $table->string('title');
            $table->string('username')->nullable();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->json('settings')->nullable(); // default hashtags, auto_delete_hours, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Posts Table
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->onDelete('cascade');
            $table->text('draft_content');
            $table->text('final_content')->nullable();
            $table->string('status')->default('draft')->index(); // draft, scheduled, posted, failed
            $table->string('media_type')->default('none'); // none, photo, video
            $table->string('media_url')->nullable(); // Can be path or telegram file_id
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('posted_at')->nullable();
            $table->string('ai_provider')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->decimal('cost', 8, 4)->default(0.0000);
            $table->json('meta')->nullable(); // store raw prompt/response details
            $table->timestamps();
        });

        // AI Usage Logs Table
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('provider');
            $table->string('model');
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->decimal('cost', 8, 4)->default(0.0000);
            $table->string('action'); // e.g. post_generation, duplicate_check
            $table->timestamp('created_at')->nullable();
        });

        // Subscriptions Table
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('plan'); // free, premium, business
            $table->decimal('price', 8, 2)->default(0.00);
            $table->string('status')->default('pending')->index(); // active, expired, pending
            $table->string('payment_method'); // manual, stars
            $table->string('payment_id')->nullable(); // external ID or manual receipt reference
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Manual Payment Receipts Table
        Schema::create('payment_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('screenshot_path')->nullable();
            $table->decimal('amount', 8, 2)->default(0.00);
            $table->string('status')->default('pending')->index(); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        // Duplicate Check Table
        Schema::create('duplicate_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->onDelete('cascade');
            $table->foreignId('compared_post_id')->constrained('posts')->onDelete('cascade');
            $table->decimal('similarity_score', 5, 2); // 0.00 - 100.00
            $table->string('check_type')->default('fuzzy'); // fuzzy, embedding
            $table->timestamp('created_at')->nullable();
        });

        // Admin Activity Audit Log
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->string('action');
            $table->text('details')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('duplicate_checks');
        Schema::dropIfExists('payment_confirmations');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('channels');
    }
};
