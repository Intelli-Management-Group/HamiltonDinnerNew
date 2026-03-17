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
        Schema::table('order_details', function (Blueprint $table) {
            $table->boolean('is_brk_takeout_service')->default(false);
            $table->boolean('is_lunch_takeout_service')->default(false);
            $table->boolean('is_dinner_takeout_service')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn([
                'is_brk_takeout_service',
                'is_lunch_takeout_service',
                'is_dinner_takeout_service'
            ]);
        });
    }
};
