<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_schedules', function (Blueprint $table): void {
            $table->string('recurrence_type')->nullable()->after('service_time');
            $table->string('recurrence_detail')->nullable()->after('recurrence_type');
        });
    }

    public function down(): void
    {
        Schema::table('service_schedules', function (Blueprint $table): void {
            $table->dropColumn(['recurrence_type', 'recurrence_detail']);
        });
    }
};
