<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homecell_leaders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homecell_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->default('Leader');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['homecell_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homecell_leaders');
    }
};
