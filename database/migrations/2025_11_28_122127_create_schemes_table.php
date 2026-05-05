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
        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->enum('commission_type', ['wallet'])->default('wallet')->nullable();
            $table->enum('type', ['flat', 'percent'])->default('percent')->nullable();
            $table->string('commission_value')->nullable();
            $table->boolean('status')->default(1)->nullable();
            $table->enum("gst_type", ["percent"])->default("percent")->nullable();
            $table->string('gst_value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schemes');
    }
};
