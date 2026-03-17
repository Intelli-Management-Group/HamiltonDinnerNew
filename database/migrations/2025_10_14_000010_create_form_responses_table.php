<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('form_responses', function (Blueprint $table) {
            $table->increments('id')->unsigned(); // Primary key, auto_increment
            $table->integer('form_type_id')->unsigned();
            $table->integer('room_id')->nullable();
            $table->longText('form_response')
                ->tap(function ($column) {
                    if (Schema::getConnection()->getDriverName() === 'mysql') {
                        $column->charset('utf8mb4')->collation('utf8mb4_bin');
                    }
                });
            $table->tinyInteger('is_follow_up_incomplete')
                ->unsigned()->nullable();
            $table->string('file_name', 255);
            $table->tinyInteger('follow_up_assigned_to')->default(0);
            $table->integer('created_by');
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('form_responses');
    }
};
