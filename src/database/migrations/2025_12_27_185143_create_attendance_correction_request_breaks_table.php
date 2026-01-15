<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceCorrectionRequestBreaksTable extends Migration
{
    public function up()
    {
        Schema::create('attendance_correction_request_breaks', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('attendance_correction_request_id');

            $table->integer('break_no');
            $table->dateTime('requested_break_in_at')->nullable();
            $table->dateTime('requested_break_out_at')->nullable();
            $table->timestamps();

            $table->foreign('attendance_correction_request_id', 'acr_breaks_acr_id_fk')
                ->references('id')
                ->on('attendance_correction_requests')
                ->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_correction_request_breaks');
    }
}
