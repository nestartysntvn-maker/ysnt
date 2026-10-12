<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_channels', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 20)->index();          // tiktok, shopee, lazada
            $t->string('name');                            // ten shop tren san
            $t->string('shop_id', 64)->nullable();
            $t->text('credentials')->nullable();           // encrypted JSON: app key/secret, tokens
            $t->timestamp('token_expires_at')->nullable();
            $t->boolean('sync_orders')->default(true);
            $t->boolean('sync_stock')->default(false);
            $t->string('status', 20)->default('draft');    // draft, active, paused, error
            $t->timestamp('last_order_sync_at')->nullable();
            $t->timestamp('last_stock_sync_at')->nullable();
            $t->string('last_error', 500)->nullable();
            $t->timestamps();
        });
        Schema::create('channel_listings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_channel_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->string('platform_item_id', 64)->nullable();
            $t->string('platform_sku_id', 64)->nullable();
            $t->string('platform_sku', 64)->nullable();
            $t->boolean('sync_stock')->default(true);
            $t->integer('stock_buffer')->default(0);       // giu lai, khong day len san
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamps();
            $t->unique(['sales_channel_id', 'variant_id']);
            $t->index(['sales_channel_id', 'platform_sku_id']);
        });
        Schema::create('channel_sync_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_channel_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 20);                        // orders, stock, token
            $t->string('status', 20);                      // ok, error, skipped
            $t->unsignedInteger('items')->default(0);
            $t->text('message')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->id(); $t->string('queue')->index(); $t->longText('payload');
            $t->unsignedTinyInteger('attempts'); $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at'); $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id(); $t->string('uuid')->unique(); $t->text('connection'); $t->text('queue');
            $t->longText('payload'); $t->longText('exception'); $t->timestamp('failed_at')->useCurrent();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('type'); $t->morphs('notifiable');
            $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->text('app_authentication_secret')->nullable();
            $t->text('app_authentication_recovery_codes')->nullable();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('sales_channel_id')->nullable()->after('channel')->constrained()->nullOnDelete();
            $t->string('channel_order_id', 64)->nullable()->after('sales_channel_id');
            $t->unique(['sales_channel_id', 'channel_order_id']);
        });
    }
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) { $t->dropUnique(['sales_channel_id', 'channel_order_id']); $t->dropConstrainedForeignId('sales_channel_id'); $t->dropColumn('channel_order_id'); });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']));
        foreach (['notifications', 'failed_jobs', 'jobs', 'channel_sync_logs', 'channel_listings', 'sales_channels'] as $tb) Schema::dropIfExists($tb);
    }
};
