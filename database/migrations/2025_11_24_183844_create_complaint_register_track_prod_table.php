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
        Schema::create('complaint_register_track_prod', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_type')->nullable();
            $table->string('participation_type')->nullable();
            $table->string('biller_id')->nullable();
            $table->string('txn_ref_id')->nullable();
            $table->string('complaint_desc')->nullable();
            $table->string('serv_reason')->nullable();
            $table->string('complaint_disposition')->nullable();
            $table->string('complaint_assigned')->nullable();
            $table->string('register_complaint_id')->nullable();
            $table->string('register_response_reason')->nullable();
            $table->string('track_complaint_id')->nullable();
            $table->string('complaint_remarks')->nullable();
            $table->string('track_response_reason')->nullable();
            $table->string('complaint_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_register_track_prod');
    }
};
