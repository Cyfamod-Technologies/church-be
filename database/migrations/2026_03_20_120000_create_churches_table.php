<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('district_area')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('pastor_name');
            $table->string('pastor_phone');
            $table->string('pastor_email')->nullable();
            $table->boolean('finance_enabled')->default(false);
            $table->boolean('special_services_enabled')->default(true);
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('churches');
    }
};
