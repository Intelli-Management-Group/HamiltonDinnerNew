<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('item_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('item_name', 127);
            $table->string('item_chinese_name', 255)
                ->nullable()
                ->tap(function ($column) {
                    if (Schema::getConnection()->getDriverName() === 'mysql') {
                        $column->charset('utf8mb3')->collation('utf8mb3_unicode_ci');
                    }
                });
            $table->integer('cat_id')->nullable();
            $table->tinyInteger('is_allday')->nullable();
            $table->string('item_image', 127)->nullable();
            $table->timestamps();
            $table->softDeletes()->nullable();
            $table->text('options')->nullable();
            $table->text('preference')->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('item_details');
    }
};
