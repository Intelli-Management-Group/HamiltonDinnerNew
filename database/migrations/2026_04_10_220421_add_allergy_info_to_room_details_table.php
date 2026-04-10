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
        Schema::table('room_details', function (Blueprint $table) {
            $table->text('allergy_info')->nullable()->after('food_texture');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_details', function (Blueprint $table) {
            $table->dropColumn('allergy_info');
        });
    }
};
