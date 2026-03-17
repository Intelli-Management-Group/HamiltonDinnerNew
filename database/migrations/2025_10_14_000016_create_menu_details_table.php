<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('menu_details', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->date('date');
            $table->longText('items');
            $table->timestamps();
            $table->softDeletes()->nullable();
            $table->tinyInteger('is_allday')->nullable();
            $table->unique('date');
        });
    }
    public function down()
    {
        Schema::dropIfExists('menu_details');
    }
};
