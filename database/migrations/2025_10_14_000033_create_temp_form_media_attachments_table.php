<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('temp_form_media_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('name');
            $table->string('form_field_name', 255)->nullable();
            $table->text('type')->nullable();
            $table->double('size_in_kb')->nullable();
            $table->text('thumbnail')->nullable();
            $table->text('file_extension')->nullable();
            $table->bigInteger('form_response_id');
            $table->timestamps();
            $table->softDeletes()->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('temp_form_media_attachments');
    }
};
