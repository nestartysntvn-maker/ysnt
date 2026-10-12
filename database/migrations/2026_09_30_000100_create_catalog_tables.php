<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->longText('description')->nullable();
            $t->string('image')->nullable();
            $t->string('status', 20)->default('active');
            $t->string('source_ref')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('sku', 64)->nullable()->unique();
            $t->unsignedBigInteger('list_price');
            $t->unsignedBigInteger('compare_price')->nullable();
            $t->unsignedInteger('weight_grams')->nullable();
            $t->integer('stock_qty')->nullable();
            $t->boolean('is_available')->default(true);
            $t->string('image')->nullable();
            $t->integer('sort_order')->default(0);
            $t->string('source_ref')->nullable()->index();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
