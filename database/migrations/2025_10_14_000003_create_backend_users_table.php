<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('backend_users', function (Blueprint $table) {
            $table->bigIncrements('id'); // Primary key, auto_increment
            $table->string('name', 127);
            $table->string('email', 127);
            $table->bigInteger('role_id')->unsigned()->nullable();
            $table->string('avatar', 127)->default('users/default.png');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 127);
            $table->string('remember_token', 100)->nullable();
            $table->text('settings')->nullable();
            $table->tinyInteger('is_admin')->default(0);
            $table->timestamps();
            $table->string('user_name', 127)->nullable();
            $table->unique('email', 'backend_users_email_unique');
        });
    }
    public function down() {
        Schema::dropIfExists('backend_users');
    }
};
