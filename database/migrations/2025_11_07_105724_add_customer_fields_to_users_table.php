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
        Schema::table('users', function (Blueprint $table) {
            $table->string('customer_mobile')->nullable()->after('password');
            $table->string('customer_email')->nullable()->after('customer_mobile');
            $table->string('customer_adhaar')->nullable()->after('customer_email');
            $table->string('customer_pan')->nullable()->after('customer_adhaar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'customer_mobile',
                'customer_email',
                'customer_adhaar',
                'customer_pan'
            ]);
        });
    }
};
