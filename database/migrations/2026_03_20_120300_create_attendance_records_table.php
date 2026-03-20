<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('service_date');
            $table->string('service_type');
            $table->string('service_label');
            $table->unsignedTinyInteger('sunday_service_number')->nullable();
            $table->string('special_service_name')->nullable();
            $table->unsignedInteger('male_count')->default(0);
            $table->unsignedInteger('female_count')->default(0);
            $table->unsignedInteger('children_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('first_timers_count')->default(0);
            $table->unsignedInteger('new_converts_count')->default(0);
            $table->unsignedInteger('rededications_count')->default(0);
            $table->decimal('main_offering', 12, 2)->nullable();
            $table->decimal('tithe', 12, 2)->nullable();
            $table->decimal('special_offering', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'service_date']);
            $table->index(['church_id', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
