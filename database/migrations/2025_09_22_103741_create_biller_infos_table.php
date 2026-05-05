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
        Schema::create('biller_infos', function (Blueprint $table) {
            $table->id();
            $table->string('biller_id')->nullable();
            // fields coming from <paramInfo>
            $table->string('param_name')->nullable();           // e.g., Consumer Number, a, a b
            $table->string('data_type')->nullable(); // NUMERIC, STRING, etc.
            $table->string('is_optional')->nullable(); // NUMERIC, STRING, etc.
            $table->unsignedInteger('min_length')->nullable();
            $table->unsignedInteger('max_length')->nullable();
            $table->string('reg_ex')->nullable(); // NUMERIC, STRING, etc.
            $table->string('visibility')->nullable(); // NUMERIC, STRING, etc.
            $table->string('biller_adhoc')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biller_infos');
    }
};
