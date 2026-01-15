<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionRequestBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_correction_request_id',
        'break_no',
        'requested_break_in_at',
        'requested_break_out_at',
    ];

    protected $casts = [
        'requested_break_in_at' => 'datetime',
        'requested_break_out_at' => 'datetime',
    ];

    public function correctionRequest()
    {
        return $this->belongsTo(AttendanceCorrectionRequest::class, 'attendance_correction_request_id');
    }
}
