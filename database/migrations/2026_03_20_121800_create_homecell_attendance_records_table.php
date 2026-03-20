<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homecell_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('homecell_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('meeting_date');
            $table->unsignedInteger('male_count')->default(0);
            $table->unsignedInteger('female_count')->default(0);
            $table->unsignedInteger('children_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('first_timers_count')->default(0);
            $table->unsignedInteger('new_converts_count')->default(0);
            $table->decimal('offering_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'meeting_date']);
            $table->index(['homecell_id', 'meeting_date']);
            $table->index(['branch_id', 'meeting_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homecell_attendance_records');
    }
};
