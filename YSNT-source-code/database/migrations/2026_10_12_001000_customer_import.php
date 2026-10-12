<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one row per uploaded customer list; keeps what it changed so the whole batch can be undone
        Schema::create('customer_import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('file_name');
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('added_count')->default(0);
            $t->unsignedInteger('filled_count')->default(0);
            $t->unsignedInteger('skipped_count')->default(0);
            $t->unsignedInteger('error_count')->default(0);
            $t->json('filled')->nullable();
            $t->timestamp('undone_at')->nullable();
            $t->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('customers', function (Blueprint $t) {
            $t->date('birthday')->nullable()->after('address');
            $t->string('customer_group', 20)->nullable()->index()->after('birthday');
            $t->string('source', 30)->nullable()->after('customer_group');
            $t->text('note')->nullable()->after('source');
            $t->foreignId('import_batch_id')->nullable()->after('zalo_opt_out')->constrained('customer_import_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('import_batch_id');
            $t->dropIndex(['customer_group']);
            $t->dropColumn(['birthday', 'customer_group', 'source', 'note']);
        });
        Schema::dropIfExists('customer_import_batches');
    }
};
