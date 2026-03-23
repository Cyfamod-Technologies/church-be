<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->boolean('finance_enabled')->default(false)->after('status');
            $table->boolean('special_services_enabled')->default(false)->after('finance_enabled');
        });

        Schema::table('service_schedules', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('church_id')->constrained('branches')->nullOnDelete();
            $table->index(['church_id', 'branch_id', 'service_type'], 'service_schedules_scope_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('service_schedules', function (Blueprint $table): void {
            $table->dropIndex('service_schedules_scope_type_index');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['finance_enabled', 'special_services_enabled']);
        });
    }
};
