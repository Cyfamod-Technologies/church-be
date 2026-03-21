<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->boolean('homecell_schedule_locked')->default(false)->after('special_services_enabled');
            $table->string('homecell_default_day')->nullable()->after('homecell_schedule_locked');
            $table->time('homecell_default_time')->nullable()->after('homecell_default_day');
            $table->json('homecell_monthly_dates')->nullable()->after('homecell_default_time');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->dropColumn([
                'homecell_schedule_locked',
                'homecell_default_day',
                'homecell_default_time',
                'homecell_monthly_dates',
            ]);
        });
    }
};
