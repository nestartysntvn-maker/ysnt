<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zalo_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name');
            $t->string('trigger', 40);
            $t->string('zns_template_id', 32)->nullable();
            $t->json('params')->nullable();
            $t->text('sample_text')->nullable();
            $t->boolean('is_active')->default(false);
            $t->timestamps();
        });
        Schema::create('zalo_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('zalo_template_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('phone', 20)->nullable();
            $t->json('payload')->nullable();
            $t->string('status', 20)->index();
            $t->string('zalo_msg_id', 64)->nullable();
            $t->text('error')->nullable();
            $t->unsignedInteger('cost')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['zalo_template_id', 'order_id']);
        });
        Schema::create('app_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::table('customers', fn (Blueprint $t) => $t->boolean('zalo_opt_out')->default(false)->after('first_ref_code'));
    }
    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('zalo_opt_out'));
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('zalo_messages');
        Schema::dropIfExists('zalo_templates');
    }
};
