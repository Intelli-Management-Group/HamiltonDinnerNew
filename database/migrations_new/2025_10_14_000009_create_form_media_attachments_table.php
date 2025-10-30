<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('form_media_attachments', function (Blueprint $table) {
            $table->increments('id'); // Primary key, auto_increment
            $table->text('name');
            $table->text('type')->nullable();
            $table->double('size_in_kb')->nullable();
            $table->text('thumbnail')->nullable();
            $table->text('file_extension')->nullable();
            $table->integer('form_response_id')->unsigned();
            $table->timestamps();
            $table->softDeletes();
            $table->primary('id');
        });
    }
    public function down() {
        Schema::dropIfExists('form_media_attachments');
    }
};
