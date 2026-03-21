<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_response_entries', function (Blueprint $table): void {
            $table->boolean('foundation_class_completed')->default(false)->after('notes');
            $table->boolean('baptism_completed')->default(false)->after('foundation_class_completed');
            $table->boolean('wofbi_completed')->default(false)->after('baptism_completed');
            $table->string('wofbi_level')->nullable()->after('wofbi_completed');
        });
    }

    public function down(): void
    {
        Schema::table('guest_response_entries', function (Blueprint $table): void {
            $table->dropColumn([
                'foundation_class_completed',
                'baptism_completed',
                'wofbi_completed',
                'wofbi_level',
            ]);
        });
    }
};
