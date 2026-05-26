<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('name', 127);
            $table->timestamps();
            $table->unique('name');
        });
    }
    public function down()
    {
        Schema::dropIfExists('menus');
    }
};
