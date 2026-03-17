<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('temp_form_responses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('form_type_id')->nullable();
            $table->bigInteger('room_id')->nullable();
            $table->tinyInteger('is_follow_up_incomplete')->nullable();
            $table->string('file_name', 255)->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->longText('form_response');
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('temp_form_responses');
    }
};
