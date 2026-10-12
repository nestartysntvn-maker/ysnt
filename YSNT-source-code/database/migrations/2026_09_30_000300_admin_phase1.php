<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->string('type', 20)->index();
            $t->integer('qty');
            $t->integer('stock_after')->nullable();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('note')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('order_status_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('note')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 30);
            $t->string('subject_type', 60)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('changes')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('created_by')->nullable()->after('ambassador_id')->constrained('users')->nullOnDelete();
            $t->text('internal_note')->nullable()->after('note');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone', 20)->nullable()->after('email');
            $t->boolean('is_active')->default(true)->after('role');
        });
        Schema::table('product_variants', function (Blueprint $t) {
            $t->longText('description')->nullable()->after('image');
        });
    }
    public function down(): void
    {
        Schema::table('product_variants', fn (Blueprint $t) => $t->dropColumn('description'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['phone', 'is_active']));
        Schema::table('orders', function (Blueprint $t) { $t->dropConstrainedForeignId('created_by'); $t->dropColumn('internal_note'); });
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('stock_movements');
    }
};
