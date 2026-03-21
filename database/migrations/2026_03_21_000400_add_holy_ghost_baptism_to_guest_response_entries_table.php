<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_response_entries', function (Blueprint $table): void {
            $table->boolean('holy_ghost_baptism_completed')->default(false)->after('baptism_completed');
        });
    }

    public function down(): void
    {
        Schema::table('guest_response_entries', function (Blueprint $table): void {
            $table->dropColumn('holy_ghost_baptism_completed');
        });
    }
};
