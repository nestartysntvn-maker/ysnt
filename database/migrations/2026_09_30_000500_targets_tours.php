<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $t) {
            $t->id();
            $t->date('period');
            $t->string('channel_group', 20);
            $t->unsignedBigInteger('target_amount');
            $t->unsignedBigInteger('stretch_amount')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
            $t->unique(['period', 'channel_group']);
        });
        Schema::create('tour_visits', function (Blueprint $t) {
            $t->id();
            $t->dateTime('visited_at')->index();
            $t->string('operator_name');
            $t->string('plate', 20)->nullable();
            $t->foreignId('ambassador_id')->nullable()->constrained()->nullOnDelete();
            $t->string('guide_name')->nullable();
            $t->unsignedSmallInteger('pax');
            $t->string('market', 20)->default('vn');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('note')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tour_visits');
        Schema::dropIfExists('sales_targets');
    }
};
