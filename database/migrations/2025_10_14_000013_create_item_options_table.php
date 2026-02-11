<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('item_options', function (Blueprint $table) {
            $table->increments('id');
            $table->string('option_name', 255)->nullable();
            $table->string('option_name_cn', 255)->nullable();
            $table->tinyInteger('is_paid_item')->nullable();
            $table->timestamps();
            $table->softDeletes()->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('item_options');
    }
};
