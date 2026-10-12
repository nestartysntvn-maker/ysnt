<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** HR, staff Workplace, internal announcements, knowledge base + AI Q&A, recruitment. New tables only. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name', 64)->unique();
            $t->string('description')->nullable();
            $t->unsignedBigInteger('head_employee_id')->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('code', 16)->nullable()->unique();
            $t->string('full_name');
            $t->string('alias', 64)->nullable();
            $t->string('phone', 20)->unique();                      // Workplace login
            $t->string('email')->nullable()->index();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->string('job_title', 128)->nullable();
            $t->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->date('hire_date')->nullable();
            $t->string('status', 16)->default('active')->index();   // active, inactive, suspended
            $t->string('onboarding_status', 16)->nullable();        // pending, approved
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();         // admin account, if any
            $t->foreignId('ambassador_id')->nullable()->constrained()->nullOnDelete();   // internal referral code
            $t->string('zalo_user_id', 64)->nullable();             // set when the employee follows the OA and shares this phone
            $t->string('password')->nullable();
            $t->boolean('must_change_password')->default(true);
            $t->rememberToken();
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->json('profile')->nullable();                         // contact channels, insurance numbers
            $t->text('private')->nullable();                         // encrypted JSON: address, birthday, emergency, bank, ID card, document files
            $t->unsignedBigInteger('base_salary')->nullable();
            $t->boolean('commission_enabled')->default(false);
            $t->decimal('commission_pct', 5, 2)->nullable();
            $t->json('allowances')->nullable();
            $t->string('termination_type', 16)->nullable();          // resign, dismissed
            $t->date('last_working_day')->nullable();
            $t->string('termination_reason', 500)->nullable();
            $t->string('asset_return_note', 500)->nullable();
            $t->timestamp('terminated_at')->nullable();
            $t->timestamps();
        });
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('body');
            $t->string('link', 500)->nullable();
            $t->string('audience', 16);                              // all, departments, employees
            $t->json('target_department_ids')->nullable();
            $t->json('target_employee_ids')->nullable();
            $t->json('channels');                                    // workplace, email, zalo
            $t->boolean('pinned')->default(false);
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('recipient_count')->default(0);
            $t->unsignedInteger('email_ok')->default(0);
            $t->unsignedInteger('zalo_ok')->default(0);
            $t->timestamps();
        });
        Schema::create('announcement_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('email_status', 16)->nullable();
            $t->string('zalo_status', 16)->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->unique(['announcement_id', 'employee_id']);
            $t->index(['employee_id', 'read_at']);
        });
        Schema::create('kb_docs', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('category', 64)->nullable()->index();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();   // null = whole company
            $t->longText('body')->nullable();
            $t->string('file_path', 500)->nullable();
            $t->string('file_name')->nullable();
            $t->longText('file_text')->nullable();
            $t->foreignId('owner_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('is_published')->default(true);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('kb_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->text('question');
            $t->text('answer')->nullable();
            $t->json('doc_ids')->nullable();
            $t->string('status', 16)->index();                       // answered, unknown, dry_run, error
            $t->boolean('helpful')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
        Schema::create('recruitment_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 16)->default('draft')->index();    // draft, open, paused, closed
            $t->string('employment_type', 16)->default('FULL_TIME');
            $t->string('location')->default('Nha Trang');
            $t->string('salary')->nullable();
            $t->text('description')->nullable();
            $t->text('requirements')->nullable();
            $t->text('benefits')->nullable();
            $t->date('deadline')->nullable();
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('job_applications', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->nullable()->unique();
            $t->foreignId('job_id')->nullable()->constrained('recruitment_jobs')->nullOnDelete();
            $t->string('position', 128);
            $t->string('name', 128);
            $t->string('phone', 32);
            $t->string('email')->nullable();
            $t->string('experience', 16)->nullable();
            $t->string('cv_url', 500)->nullable();
            $t->string('cv_path', 500)->nullable();
            $t->text('message')->nullable();
            $t->string('status', 16)->default('new')->index();      // new, interview, hired, rejected
            $t->text('notes')->nullable();
            $t->dateTime('interview_at')->nullable();
            $t->string('interview_location', 500)->nullable();
            $t->string('interview_host')->nullable();
            $t->string('source', 16)->default('web');                // web, manual, referral
            $t->foreignId('referred_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->softDeletes();
            $t->timestamps();
            $t->index('created_at');
        });
        // single-use links for new hires to fill in their own profile (go365 "hồ sơ nhân viên" portal)
        Schema::create('onboarding_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('job_application_id')->nullable()->constrained()->nullOnDelete();
            $t->char('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['onboarding_tokens', 'job_applications', 'recruitment_jobs', 'kb_questions', 'kb_docs', 'announcement_recipients', 'announcements', 'employees', 'departments'] as $tb) Schema::dropIfExists($tb);
    }
};
