<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('db_migrations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('sourceDatabase', 30)->nullable();
            $table->string('destDatabase', 30)->nullable();
            $table->string('subsiteUrl', 255)->nullable();
            $table->bigInteger('sourceSubsiteId')->nullable();
            $table->bigInteger('destSubsiteId')->nullable();
            $table->boolean('created')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_migrations');
    }
};
