<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('form_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->tinyInteger('allow_print')->default(1);
            $table->tinyInteger('allow_mail')->default(1);
            $table->timestamps();
            $table->softDeletes()->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('form_types');
    }
};
