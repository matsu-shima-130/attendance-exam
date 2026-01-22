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
            // 出勤・退勤（必須）
            'clock_in' => ['required', 'date_format:H:i'],
            'clock_out' => ['required', 'date_format:H:i', 'after_or_equal:clock_in'],

            // 休憩（配列。どちらか入れたら両方必須）
            'breaks' => ['nullable', 'array'],

            'breaks.*.in' => [
                'nullable',
                'date_format:H:i',
                'required_with:breaks.*.out',
                'after_or_equal:clock_in',
                'before_or_equal:clock_out',
            ],
            'breaks.*.out' => [
                'nullable',
                'date_format:H:i',
                'required_with:breaks.*.in',
                'after_or_equal:breaks.*.in',
                'before_or_equal:clock_out',
            ],

            // 備考（必須）
            'note' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // 出勤・退勤（前後関係）
            'clock_out.after_or_equal' => '出勤時間もしくは退勤時間が不適切な値です',

            // 休憩（片方だけ入力）
            'breaks.*.in.required_with' => '休憩開始時間を入力してください',
            'breaks.*.out.required_with' => '休憩終了時間を入力してください',

            // 休憩（範囲・前後関係）
            'breaks.*.in.after_or_equal' => '休憩時間が不適切な値です',
            'breaks.*.in.before_or_equal' => '休憩時間が不適切な値です',

            'breaks.*.out.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
            'breaks.*.out.after_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',

            // 備考必須
            'note.required' => '備考を記入してください',
        ];
    }

}
