<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('room_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('room_name', 127);
            $table->text('special_instrucations')->nullable();
            $table->tinyInteger('occupancy')->default(1)->nullable();
            $table->string('resident_name', 255)->nullable();
            $table->tinyInteger('language')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->text('password')->nullable();
            $table->tinyInteger('role_id')->default(8)->nullable();
            $table->string('food_texture', 255)->nullable();
            $table->timestamps();
            $table->softDeletes()->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('room_details');
    }
};
