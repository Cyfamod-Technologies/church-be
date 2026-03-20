<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->foreignId('branch_tag_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pastor_name')->nullable();
            $table->string('pastor_phone')->nullable();
            $table->string('pastor_email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('district_area')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by_church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_actor_type')->default('user');
            $table->foreignId('current_parent_church_id')->nullable()->constrained('churches')->nullOnDelete();
            $table->foreignId('current_parent_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('last_assigned_by_church_id')->nullable()->constrained('churches')->nullOnDelete();
            $table->foreignId('last_assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('last_assigned_actor_type')->nullable();
            $table->timestamps();

            $table->index(['created_by_church_id', 'branch_tag_id']);
            $table->index(['current_parent_church_id', 'current_parent_branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
