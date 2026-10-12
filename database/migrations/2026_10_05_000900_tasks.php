<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Task assignment: who gave it, who does it, deadline, reminders. New tables only. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('link', 500)->nullable();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();                         // who does it
            $t->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();  // given from /admin
            $t->foreignId('assigned_by_employee_id')->nullable()->constrained('employees')->nullOnDelete(); // given by a manager on the Workplace
            $t->string('batch', 36)->nullable()->index();                                             // same task given to several people at once
            $t->string('priority', 8)->default('normal');
            $t->string('status', 12)->default('todo')->index();                                       // todo, doing, done, cancelled
            $t->dateTime('due_at')->nullable()->index();
            $t->unsignedSmallInteger('remind_before_hours')->default(24);
            $t->timestamp('reminded_at')->nullable();
            $t->timestamp('overdue_notified_at')->nullable();
            $t->timestamp('done_at')->nullable();
            $t->string('done_note', 1000)->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'status']);
        });
        Schema::create('task_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind', 16);                                                                    // comment, status, reminder
            $t->text('body')->nullable();
            $t->string('channels', 64)->nullable();                                                    // for reminders: where it went
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_updates');
        Schema::dropIfExists('tasks');
    }
};
