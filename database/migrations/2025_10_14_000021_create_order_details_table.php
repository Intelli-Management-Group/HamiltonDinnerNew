<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('room_id');
            $table->date('date');
            $table->integer('item_id');
            $table->tinyInteger('quantity')->default(0);
            $table->text('comment')->nullable();
            $table->tinyInteger('status');
            $table->tinyInteger('is_for_guest')->default(0)->nullable();
            $table->tinyInteger('is_brk_tray_service')->default(0);
            $table->tinyInteger('is_lunch_tray_service')->default(0);
            $table->tinyInteger('is_dinner_tray_service')->default(0);
            $table->tinyInteger('is_brk_escort_service')->default(0);
            $table->tinyInteger('is_lunch_escort_service')->default(0);
            $table->tinyInteger('is_dinner_escort_service')->default(0);
            $table->tinyInteger('is_brk_takeout_service')->default(0);
            $table->tinyInteger('is_lunch_takeout_service')->default(0);
            $table->tinyInteger('is_dinner_takeout_service')->default(0);
            $table->text('item_options')->nullable();
            $table->text('preference')->nullable();
            $table->timestamps();
            $table->softDeletes()->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('order_details');
    }
};
