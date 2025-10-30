<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('menu_id')->nullable();
            $table->string('title', 127);
            $table->string('url', 127);
            $table->string('target', 127)->default('_self');
            $table->string('icon_class', 127)->nullable();
            $table->string('color', 127)->nullable();
            $table->integer('parent_id')->nullable();
            $table->integer('order');
            $table->timestamps()->nullable();
            $table->string('route', 127)->nullable();
            $table->text('parameters')->nullable();
            $table->primary('id');
            $table->index('menu_id', 'menu_items_menu_id_foreign');
            $table->foreign('menu_id')
                ->references('id')
                ->on('menus')
                ->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::dropIfExists('menu_items');
    }
};
