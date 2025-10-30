<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('item_preferences', function (Blueprint $table) {
            $table->increments('id');
            $table->string('pname', 255)->nullable();
            $table->string('pname_cn', 255)->nullable();
            $table->timestamps()->nullable();
            $table->softDeletes()->nullable();
            $table->primary('id');
        });
    }
    public function down()
    {
        Schema::dropIfExists('item_preferences');
    }
};
