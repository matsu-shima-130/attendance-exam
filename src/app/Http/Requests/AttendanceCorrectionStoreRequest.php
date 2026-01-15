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

            // 休憩1（どちらか入れたら両方必須）
            'break1_in' => [
                'nullable',
                'date_format:H:i',
                'required_with:break1_out',
                'after_or_equal:requested_clock_in_at',
                'before_or_equal:requested_clock_out_at',
            ],
            'break1_out' => [
                'nullable',
                'date_format:H:i',
                'required_with:break1_in',
                'after_or_equal:break1_in',
                'before_or_equal:requested_clock_out_at',
            ],

            // 休憩2（どちらか入れたら両方必須）
            'break2_in' => [
                'nullable',
                'date_format:H:i',
                'required_with:break2_out',
                'after_or_equal:requested_clock_in_at',
                'before_or_equal:requested_clock_out_at',
            ],
            'break2_out' => [
                'nullable',
                'date_format:H:i',
                'required_with:break2_in',
                'after_or_equal:break2_in',
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
            'break1_in' => '休憩開始時間',
            'break1_out' => '休憩終了時間',
            'break2_in' => '休憩2開始時間',
            'break2_out' => '休憩2終了時間',
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

            // 休憩1
            'break1_in.required_with' => '休憩開始時間を入力してください',
            'break1_out.required_with' => '休憩終了時間を入力してください',
            'break1_in.after_or_equal' => '休憩時間が不適切な値です',
            'break1_in.before_or_equal' => '休憩時間が不適切な値です',
            'break1_out.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
            'break1_out.after_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',

            // 休憩2
            'break2_in.required_with' => '開始時間を入力してください',
            'break2_out.required_with' => '終了時間を入力してください',
            'break2_in.after_or_equal' => '休憩時間が不適切な値です',
            'break2_in.before_or_equal' => '休憩時間が不適切な値です',
            'break2_out.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
            'break2_out.after_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',

            // 備考
            'requested_note.required' => '備考を記入してください',
        ];
    }
}
