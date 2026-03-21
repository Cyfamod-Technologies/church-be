<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_response_entries', function (Blueprint $table): void {
            $table->json('wofbi_levels')->nullable()->after('wofbi_level');
        });
    }

    public function down(): void
    {
        Schema::table('guest_response_entries', function (Blueprint $table): void {
            $table->dropColumn('wofbi_levels');
        });
    }
};
