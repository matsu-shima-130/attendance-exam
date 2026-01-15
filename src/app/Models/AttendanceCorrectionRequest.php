<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_id',
        'requested_clock_in_at',
        'requested_clock_out_at',
        'requested_note',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'requested_clock_in_at' => 'datetime',
        'requested_clock_out_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function breaks()
    {
        return $this->hasMany(AttendanceCorrectionRequestBreak::class, 'attendance_correction_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedAdmin()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

}
