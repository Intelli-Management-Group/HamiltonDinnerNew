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
        Schema::create('menu_details', function (Blueprint $table) {
            $table->increments('id');
            $table->string('menu_name', 127);
            $table->date('date')->unique();
            $table->longText('items')->nullable();
            $table->boolean('is_allday')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_details');
    }
};
