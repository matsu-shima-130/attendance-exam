<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminAttendanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clock_in' => ['required', 'date_format:H:i'],
            'clock_out' => ['required', 'date_format:H:i', 'after_or_equal:clock_in'],

            'break1_in' => ['nullable', 'date_format:H:i'],
            'break1_out' => ['nullable', 'date_format:H:i', 'after_or_equal:break1_in'],

            'break2_in' => ['nullable', 'date_format:H:i'],
            'break2_out' => ['nullable', 'date_format:H:i', 'after_or_equal:break2_in'],

            'note' => ['nullable', 'string'],
        ];
    }
}
