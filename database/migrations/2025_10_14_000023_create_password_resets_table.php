<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email', 127);
            $table->string('token', 127);
            $table->timestamp('created_at')->nullable();
            $table->index('email', 'password_resets_email_index');
        });
    }
    public function down()
    {
        Schema::dropIfExists('password_resets');
    }
};
