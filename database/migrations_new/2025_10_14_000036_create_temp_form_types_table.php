<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('temp_form_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->longText('form_fields');
            $table->tinyInteger('allow_print')->nullable();
            $table->tinyInteger('allow_mail')->nullable();
            $table->tinyInteger('is_published')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable()->default(DB::raw("'0000-00-00 00:00:00'"));
            $table->primary('id');
        });
    }
    public function down()
    {
        Schema::dropIfExists('temp_form_types');
    }
};
