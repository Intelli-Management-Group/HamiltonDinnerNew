<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('special_instruction_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('room_id');
            $table->date('date')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps()->nullable();
            $table->softDeletes()->nullable();
            $table->primary('id');
        });
    }
    public function down()
    {
        Schema::dropIfExists('special_instruction_details');
    }
};
