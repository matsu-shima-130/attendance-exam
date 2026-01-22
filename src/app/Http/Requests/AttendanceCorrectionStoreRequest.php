<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceCorrectionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 出勤・退勤（必須）
            'requested_clock_in_at' => ['required', 'date_format:H:i'],
            'requested_clock_out_at' => ['required', 'date_format:H:i', 'after_or_equal:requested_clock_in_at'],

            // 休憩（回数分 + 追加1行ぶん来る想定）
            'breaks' => ['array'],
            'breaks.*.in' => [
                'nullable',
                'date_format:H:i',
                'required_with:breaks.*.out',
                'after_or_equal:requested_clock_in_at',
                'before_or_equal:requested_clock_out_at',
            ],
            'breaks.*.out' => [
                'nullable',
                'date_format:H:i',
                'required_with:breaks.*.in',
                'after_or_equal:breaks.*.in',
                'before_or_equal:requested_clock_out_at',
            ],

            // 備考（必須）
            'requested_note' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'requested_clock_in_at' => '出勤時間',
            'requested_clock_out_at' => '退勤時間',
            'breaks.*.in' => '休憩開始時間',
            'breaks.*.out' => '休憩終了時間',
            'requested_note' => '備考',
        ];
    }

    public function messages(): array
    {
        return [
            // 出勤・退勤
            'requested_clock_in_at.required' => '出勤時間を入力してください',
            'requested_clock_out_at.required' => '退勤時間を入力してください',
            'requested_clock_out_at.after_or_equal' => '出勤時間もしくは退勤時間が不適切な値です',

            // 休憩
            'breaks.*.in.required_with' => '休憩開始時間を入力してください',
            'breaks.*.out.required_with' => '休憩終了時間を入力してください',
            'breaks.*.in.after_or_equal' => '休憩時間が不適切な値です',
            'breaks.*.in.before_or_equal' => '休憩時間が不適切な値です',
            'breaks.*.out.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
            'breaks.*.out.after_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',

            // 備考
            'requested_note.required' => '備考を記入してください',
        ];
    }
}