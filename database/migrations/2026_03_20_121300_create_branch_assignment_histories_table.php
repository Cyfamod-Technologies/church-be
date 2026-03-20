<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_assignment_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('action_type');
            $table->foreignId('from_parent_church_id')->nullable()->constrained('churches')->nullOnDelete();
            $table->foreignId('from_parent_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_parent_church_id')->nullable()->constrained('churches')->nullOnDelete();
            $table->foreignId('to_parent_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('changed_by_church_id')->nullable()->constrained('churches')->nullOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_actor_type')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'action_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_assignment_histories');
    }
};
