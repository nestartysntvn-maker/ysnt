<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Only new tables: nothing here alters or drops a column of an existing table. */
return new class extends Migration {
    public function up(): void
    {
        // one buyer may reach us from many places (Zalo, Facebook, TikTok...); every id points back to the one customer row
        Schema::create('customer_identities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('provider', 20);
            $t->string('external_id', 128);
            $t->string('display_name')->nullable();
            $t->string('phone', 20)->nullable();
            $t->timestamps();
            $t->unique(['provider', 'external_id']);
            $t->index('customer_id');
        });
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->string('channel', 20)->index();
            $t->foreignId('customer_identity_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name')->nullable();
            $t->string('status', 20)->default('open');
            $t->boolean('needs_human')->default(false);
            $t->boolean('ai_enabled')->default(true);
            $t->string('tag', 20)->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('unread')->default(0);
            $t->string('last_preview', 200)->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->string('external_ref', 128)->nullable();   // web: visitor key, TikTok: platform conversation id
            $t->timestamps();
            $t->index(['status', 'last_message_at']);
            $t->unique(['channel', 'external_ref']);
        });
        Schema::create('conversation_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->string('direction', 3);
            $t->string('sender', 10);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('body');
            $t->json('attachments')->nullable();
            $t->string('external_id', 128)->nullable();
            $t->string('status', 20)->default('received');
            $t->string('error', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['conversation_id', 'external_id']);
        });
        Schema::create('seo_meta', function (Blueprint $t) {
            $t->id();
            $t->string('page_type', 20);
            $t->string('page_key', 191);
            $t->string('meta_title')->nullable();
            $t->string('meta_description', 320)->nullable();
            $t->string('focus_keyword', 100)->nullable();
            $t->string('og_image', 500)->nullable();
            $t->boolean('noindex')->default(false);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['page_type', 'page_key']);
        });
        Schema::create('redirects', function (Blueprint $t) {
            $t->id();
            $t->string('from_path', 500)->unique();
            $t->string('to_path', 500);
            $t->unsignedSmallInteger('code')->default(301);
            $t->unsignedInteger('hits')->default(0);
            $t->timestamp('last_hit_at')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });
        // one journal for every add-on (mail, Meta, marketplaces auth, AI...), dry runs included
        Schema::create('integration_logs', function (Blueprint $t) {
            $t->id();
            $t->string('integration', 30)->index();
            $t->string('action', 40);
            $t->string('status', 20)->index();
            $t->string('ref', 64)->nullable()->index();
            $t->text('message')->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
        Schema::create('ai_logs', function (Blueprint $t) {
            $t->id();
            $t->string('task', 30)->index();
            $t->string('provider', 20);
            $t->string('model', 60)->nullable();
            $t->string('status', 20);
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->unsignedInteger('ms')->default(0);
            $t->string('error', 500)->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['ai_logs', 'integration_logs', 'redirects', 'seo_meta', 'conversation_messages', 'conversations', 'customer_identities'] as $tb) Schema::dropIfExists($tb);
    }
};
