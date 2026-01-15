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

            // 休憩1（どちらか入れたら両方必須）
            'break1_in' => [
                'nullable',
                'date_format:H:i',
                'required_with:break1_out',
                'after_or_equal:clock_in',
                'before_or_equal:clock_out',
            ],
            'break1_out' => [
                'nullable',
                'date_format:H:i',
                'required_with:break1_in',
                'after_or_equal:break1_in',
                'before_or_equal:clock_out',
            ],

            // 休憩2（どちらか入れたら両方必須）
            'break2_in' => [
                'nullable',
                'date_format:H:i',
                'required_with:break2_out',
                'after_or_equal:clock_in',
                'before_or_equal:clock_out',
            ],
            'break2_out' => [
                'nullable',
                'date_format:H:i',
                'required_with:break2_in',
                'after_or_equal:break2_in',
                'before_or_equal:clock_out',
            ],

            // 備考（管理者側は任意のまま）
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // 出勤・退勤（前後関係）
            'clock_out.after_or_equal' => '出勤時間もしくは退勤時間が不適切な値です',

            // 休憩1（片方だけ入力）
            'break1_in.required_with' => '休憩開始時間を入力してください',
            'break1_out.required_with' => '休憩終了時間を入力してください',

            // 休憩1（範囲・前後関係）
            'break1_in.after_or_equal' => '休憩時間が不適切な値です',
            'break1_in.before_or_equal' => '休憩時間が不適切な値です',
            'break1_out.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
            'break1_out.after_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',

            // 休憩2（片方だけ入力）
            'break2_in.required_with' => '開始時間を入力してください',
            'break2_out.required_with' => '終了時間を入力してください',

            // 休憩2（範囲・前後関係）
            'break2_in.after_or_equal' => '休憩時間が不適切な値です',
            'break2_in.before_or_equal' => '休憩時間が不適切な値です',
            'break2_out.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
            'break2_out.after_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
        ];
    }

}
