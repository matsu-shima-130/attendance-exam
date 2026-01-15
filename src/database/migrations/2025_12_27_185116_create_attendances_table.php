<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendancesTable extends Migration
{
    public function up()
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();
            $table->unsignedTinyInteger('status');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendances');
    }
}
