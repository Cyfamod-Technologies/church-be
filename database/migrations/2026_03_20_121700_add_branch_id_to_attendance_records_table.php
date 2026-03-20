<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('church_id')
                ->constrained('branches')
                ->nullOnDelete();

            $table->index(['church_id', 'branch_id', 'service_date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropIndex(['church_id', 'branch_id', 'service_date']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
