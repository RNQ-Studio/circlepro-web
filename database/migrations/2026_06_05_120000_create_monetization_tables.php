<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('audience'); // PlanAudience (user / organization)
            $table->bigInteger('price'); // price in IDR
            $table->string('interval')->default('monthly'); // PlanInterval
            $table->jsonb('features')->nullable();
            $table->jsonb('limits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('subscriber_type'); // user or organization
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->string('status')->default('active'); // SubscriptionStatus
            $table->string('provider'); // PaymentProvider
            $table->string('provider_subscription_id')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'current_period_end']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('payment_number')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('payable_type');
            $table->ulid('payable_id');
            $table->string('provider'); // PaymentProvider
            $table->string('method')->nullable();
            $table->bigInteger('amount');
            $table->bigInteger('fee')->default(0);
            $table->char('currency', 3)->default('IDR');
            $table->string('status')->default('pending'); // PaymentStatus
            $table->string('provider_ref')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index('status');
            $table->index('provider_ref');
        });

        Schema::create('subscription_invoices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignUlid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->bigInteger('amount');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('status')->default('pending'); // PaymentStatus
            $table->timestamps();
        });

        Schema::create('ad_campaigns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertiser_org_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('name');
            $table->bigInteger('budget')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status')->default('draft'); // AdStatus
            $table->jsonb('targeting')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('ads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->string('placement'); // feed, event_list, banner
            $table->text('image_url')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->text('click_url')->nullable();
            $table->integer('impression_count')->default(0);
            $table->integer('click_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
