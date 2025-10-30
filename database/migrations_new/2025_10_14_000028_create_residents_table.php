<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->bigIncrements('resident_id');
            $table->string('resident_name', 127);
            $table->string('room_no', 127)->nullable();
            $table->string('external_resident_id', 127)->nullable();
            $table->string('resident_alert', 127)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('resident_image', 127)->nullable();
            $table->tinyInteger('gender')->nullable()->comment('1-Male,2-Female');
            $table->tinyInteger('resident_group_id')->nullable();
            $table->integer('table_id')->nullable();
            $table->text('diet_type')->nullable();
            $table->text('diet_texture')->nullable();
            $table->text('fluid_consistency')->nullable();
            $table->text('dislikes_diet_preference')->nullable();
            $table->text('feeding_aids')->nullable();
            $table->string('risk_level', 127)->nullable();
            $table->text('additional_info')->nullable();
            $table->string('nourishment', 127)->nullable();
            $table->text('allergy_info')->nullable();
            $table->text('comments')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1-Active,0-Inactive');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('deleted_at')->nullable();
            $table->primary('resident_id');
        });
    }
    public function down()
    {
        Schema::dropIfExists('residents');
    }
};
