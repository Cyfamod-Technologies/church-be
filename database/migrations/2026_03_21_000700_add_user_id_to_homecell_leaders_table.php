<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homecell_leaders', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->unique()->after('homecell_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('homecell_leaders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
