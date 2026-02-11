<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('role_id')->unsigned()->nullable();
            $table->tinyInteger('is_admin')->nullable();
            $table->string('role', 255)->nullable();
            $table->string('name', 127);
            $table->string('email', 127);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 127);
            $table->rememberToken();
            $table->text('settings')->nullable();
            $table->timestamps();
            $table->softDeletes()->nullable();
            $table->string('user_name', 127);
            $table->unique('email', 'users_email_unique');
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('set null');
        });
    }
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
