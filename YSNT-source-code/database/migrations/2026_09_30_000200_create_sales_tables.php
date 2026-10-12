<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone', 20)->unique();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->string('first_ref_code', 32)->nullable()->index();
            $t->timestamps();
        });
        Schema::create('ambassadors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone', 20)->unique();
            $t->string('type', 20)->index();
            $t->string('ref_code', 32)->unique();
            $t->foreignId('parent_id')->nullable()->constrained('ambassadors')->nullOnDelete();
            $t->text('bank_info')->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
        });
        Schema::create('commission_policies', function (Blueprint $t) {
            $t->id();
            $t->string('ambassador_type', 20)->index();
            $t->string('name');
            $t->decimal('rate_percent', 5, 2)->nullable();
            $t->decimal('bonus_rate_percent', 5, 2)->nullable();
            $t->unsignedBigInteger('bonus_threshold_amount')->nullable();
            $t->decimal('customer_discount_percent', 5, 2)->nullable();
            $t->decimal('upline_rate_percent', 5, 2)->nullable();
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });
        Schema::create('ref_clicks', function (Blueprint $t) {
            $t->id();
            $t->string('ref_code', 32)->index();
            $t->string('landing_path', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('channel', 20)->index();
            $t->string('ref_code', 32)->nullable()->index();
            $t->foreignId('ambassador_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('shipping_fee')->default(0);
            $t->unsignedBigInteger('total');
            $t->string('status', 20)->default('new')->index();
            $t->string('payment_method', 20)->nullable();
            $t->string('payment_status', 20)->default('unpaid');
            $t->string('ship_name')->nullable();
            $t->string('ship_phone', 20)->nullable();
            $t->text('ship_address')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->string('product_name');
            $t->string('variant_name');
            $t->string('sku', 64)->nullable();
            $t->unsignedBigInteger('unit_price');
            $t->unsignedInteger('qty');
            $t->unsignedBigInteger('line_total');
            $t->timestamps();
        });
        Schema::create('commissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ambassador_id')->constrained()->restrictOnDelete();
            $t->string('level', 10);
            $t->unsignedBigInteger('base_amount');
            $t->decimal('rate_percent', 5, 2);
            $t->unsignedBigInteger('amount');
            $t->string('status', 20)->default('pending')->index();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['order_id', 'ambassador_id', 'level']);
        });
        Schema::create('payout_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ambassador_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('amount');
            $t->string('status', 20)->default('pending')->index();
            $t->timestamp('paid_at')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['payout_requests','commissions','order_items','orders','ref_clicks','commission_policies','ambassadors','customers'] as $tb) {
            Schema::dropIfExists($tb);
        }
    }
};
