<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_response_entry_church_unit', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_response_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('church_unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['guest_response_entry_id', 'church_unit_id'], 'gre_church_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_response_entry_church_unit');
    }
};
