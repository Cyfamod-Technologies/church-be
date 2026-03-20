<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homecells', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('meeting_day')->nullable();
            $table->time('meeting_time')->nullable();
            $table->string('host_name')->nullable();
            $table->string('host_phone')->nullable();
            $table->string('city_area')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['church_id', 'branch_id']);
            $table->index(['church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homecells');
    }
};
