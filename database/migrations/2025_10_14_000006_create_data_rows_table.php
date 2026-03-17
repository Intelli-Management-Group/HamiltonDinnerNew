<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('data_rows', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('data_type_id')->unsigned();
            $table->string('field', 127);
            $table->string('type', 127);
            $table->string('display_name', 127);
            $table->tinyInteger('required')->default(0);
            $table->tinyInteger('browse')->default(1);
            $table->tinyInteger('read')->default(1);
            $table->tinyInteger('edit')->default(1);
            $table->tinyInteger('add')->default(1);
            $table->tinyInteger('delete')->default(1);
            $table->text('details')->nullable();
            $table->integer('order')->default(1);
            $table->index('data_type_id');
            $table->foreign('data_type_id')
                ->references('id')
                ->on('data_types')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }
    public function down() {
        Schema::dropIfExists('data_rows');
    }
};
