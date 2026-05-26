<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->tinyInteger('is_brk_printed')->default(0)->after('is_dinner_takeout_service');
            $table->tinyInteger('is_lunch_printed')->default(0)->after('is_brk_printed');
            $table->tinyInteger('is_dinner_printed')->default(0)->after('is_lunch_printed');
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn(['is_brk_printed', 'is_lunch_printed', 'is_dinner_printed']);
        });
    }
};
