<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('parent_id')->unsigned()->nullable();
            $table->integer('order')->default(1);
            $table->string('name', 127);
            $table->string('slug', 127);
            $table->timestamps();
            $table->unique('slug');
            $table->index('parent_id');
            $table->foreign('parent_id')
                ->references('id')
                ->on('categories')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });
    }
    public function down() {
        Schema::dropIfExists('categories');
    }
};
