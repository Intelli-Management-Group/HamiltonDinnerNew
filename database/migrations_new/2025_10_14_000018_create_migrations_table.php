<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('migrations', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('migration', 127);
            $table->integer('batch');
            $table->primary('id');
        });
    }
    public function down()
    {
        Schema::dropIfExists('migrations');
    }
};
