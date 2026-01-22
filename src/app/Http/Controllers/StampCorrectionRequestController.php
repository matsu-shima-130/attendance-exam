<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceCorrectionRequestBreak;
use App\Models\User;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StampCorrectionRequestController extends Controller
{
    // 申請一覧（一般ユーザー）
    public function index(Request $request)
    {
        Carbon::setLocale('ja');

        $user = Auth::user();

        // tab=pending(承認待ち) / approved(承認済み)
        $tab = $request->query('tab', 'pending');
        $status = $tab === 'approved' ? 1 : 0;

        $requests = AttendanceCorrectionRequest::with(['attendance'])
            ->where('user_id', $user->id)
            ->where('status', $status)
            ->latest('id')
            ->get();

        $statusLabelMap = [
            0 => '承認待ち',
            1 => '承認済み',
        ];

        $rows = $requests->map(function ($correctionRequest) use ($statusLabelMap, $user) {
            $workDate = $correctionRequest->attendance
                ? Carbon::parse($correctionRequest->attendance->work_date)->format('Y/m/d')
                : '';

            return [
                'status_label' => $statusLabelMap[$correctionRequest->status] ?? '',
                'name' => $user->name,
                'work_date' => $workDate,
                'reason' => $correctionRequest->requested_note ?? '',
                'requested_at' => Carbon::parse($correctionRequest->created_at)->format('Y/m/d'),
                'detail_url' => route('attendance.detail', ['id' => $correctionRequest->attendance_id]),
            ];
        });

        return view('stamp_correction_request.index', [
            'tab' => $tab,
            'rows' => $rows,
        ]);
    }

    // 申請一覧（管理者）
    public function adminIndex(Request $request)
    {
        Carbon::setLocale('ja');

        $tab = $request->query('tab', 'pending');
        $status = $tab === 'approved' ? 1 : 0;

        // user名・対象日を出したいのでJOINで取る（リレーション無くても動く）
        $requests = AttendanceCorrectionRequest::query()
            ->join('users', 'attendance_correction_requests.user_id', '=', 'users.id')
            ->join('attendances', 'attendance_correction_requests.attendance_id', '=', 'attendances.id')
            ->where('attendance_correction_requests.status', $status)
            ->orderByDesc('attendance_correction_requests.id')
            ->get([
                'attendance_correction_requests.*',
                'users.name as user_name',
                'attendances.work_date as work_date',
            ]);

        $statusLabelMap = [
            0 => '承認待ち',
            1 => '承認済み',
        ];

        $rows = $requests->map(function ($correctionRequest) use ($statusLabelMap) {
            $workDateText = $correctionRequest->work_date
                ? Carbon::parse($correctionRequest->work_date)->format('Y/m/d')
                : '';

            return [
                'status_label' => $statusLabelMap[$correctionRequest->status] ?? '',
                'name' => $correctionRequest->user_name ?? '',
                'work_date' => $workDateText,
                'reason' => $correctionRequest->requested_note ?? '',
                'requested_at' => Carbon::parse($correctionRequest->created_at)->format('Y/m/d'),
                'detail_url' => route('admin.stamp_correction_request.approve', [
                    'attendanceCorrectionRequest' => $correctionRequest->id,
                ]),
            ];
        });

        return view('admin.stamp_correction_request.index', [
            'tab' => $tab,
            'rows' => $rows,
        ]);
    }

    // 修正申請承認画面（管理者：詳細）
    public function approve(AttendanceCorrectionRequest $attendanceCorrectionRequest)
    {
        Carbon::setLocale('ja');

        $attendance = Attendance::with(['breaks' => function ($breakQuery) {
            $breakQuery->orderBy('id', 'asc');
        }])->findOrFail($attendanceCorrectionRequest->attendance_id);

        $user = User::select(['id', 'name'])
            ->find($attendanceCorrectionRequest->user_id);

        $workDate = Carbon::parse($attendance->work_date);
        $workDateText = $workDate->format('Y-m-d');

        // 元の値
        $baseClockIn  = $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '';
        $baseClockOut = $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '';

        // 元の休憩（回数分ぜんぶ）
        $baseBreaks = [];
        foreach ($attendance->breaks as $breakRecord) {
            $baseBreaks[] = [
                'in'  => $breakRecord->break_in_at ? Carbon::parse($breakRecord->break_in_at)->format('H:i') : '',
                'out' => $breakRecord->break_out_at ? Carbon::parse($breakRecord->break_out_at)->format('H:i') : '',
            ];
        }

        // 申請内容（nullなら元の値）
        $clockIn = $attendanceCorrectionRequest->requested_clock_in_at
            ? Carbon::parse($attendanceCorrectionRequest->requested_clock_in_at)->format('H:i')
            : $baseClockIn;

        $clockOut = $attendanceCorrectionRequest->requested_clock_out_at
            ? Carbon::parse($attendanceCorrectionRequest->requested_clock_out_at)->format('H:i')
            : $baseClockOut;

        // 申請休憩（break_no順）
        $requestBreaks = AttendanceCorrectionRequestBreak::where('attendance_correction_request_id', $attendanceCorrectionRequest->id)
            ->orderBy('break_no', 'asc')
            ->get();

        // 表示用 breaks：元をベースに、申請で上書き（足りなければ追加）
        $breaks = $baseBreaks;

        foreach ($requestBreaks as $reqBreak) {
            $idx = $reqBreak->break_no - 1;

            while (count($breaks) <= $idx) {
                $breaks[] = ['in' => '', 'out' => ''];
            }

            if ($reqBreak->requested_break_in_at) {
                $breaks[$idx]['in'] = Carbon::parse($reqBreak->requested_break_in_at)->format('H:i');
            }
            if ($reqBreak->requested_break_out_at) {
                $breaks[$idx]['out'] = Carbon::parse($reqBreak->requested_break_out_at)->format('H:i');
            }
        }

        // ★追加で1行（要件：休憩回数分 + 追加1行）
        $breaks[] = ['in' => '', 'out' => ''];

        $note = !is_null($attendanceCorrectionRequest->requested_note)
            ? $attendanceCorrectionRequest->requested_note
            : ($attendance->note ?? '');

        $isApproved = (int)$attendanceCorrectionRequest->status === 1;

        return view('admin.stamp_correction_request.approve', [
            'requestId' => $attendanceCorrectionRequest->id,
            'attendanceId' => $attendance->id,
            'userName' => $user?->name ?? '',
            'yearText' => $workDate->format('Y年'),
            'dateText' => $workDate->format('n月j日'),
            'workDateText' => $workDateText,

            'clockIn' => $clockIn,
            'clockOut' => $clockOut,
            'breaks' => $breaks,
            'note' => $note,

            'isApproved' => $isApproved,
        ]);
    }

    // 承認処理（管理者）
    public function approveUpdate(Request $request, AttendanceCorrectionRequest $attendanceCorrectionRequest)
    {
        // すでに承認済みなら何もしない
        if ((int)$attendanceCorrectionRequest->status === 1) {
            return redirect()->route('stamp_correction_request.list', ['tab' => 'approved']);
        }

        $validated = $request->validate([
            'requested_clock_in_at' => ['nullable', 'date_format:H:i'],
            'requested_clock_out_at' => ['nullable', 'date_format:H:i'],
            'requested_note' => ['nullable', 'string'],

            'breaks' => ['nullable', 'array'],
            'breaks.*.in' => ['nullable', 'date_format:H:i'],
            'breaks.*.out' => ['nullable', 'date_format:H:i'],
        ]);

        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($attendanceCorrectionRequest, $validated, $adminId) {
            $attendance = Attendance::with(['breaks' => function ($breakQuery) {
                $breakQuery->orderBy('id', 'asc');
            }])
                ->lockForUpdate()
                ->findOrFail($attendanceCorrectionRequest->attendance_id);

            $workDate = Carbon::parse($attendance->work_date)->format('Y-m-d');

            // 入力が空なら「既存を維持」、入力があればそれを反映
            $newClockInAt = empty($validated['requested_clock_in_at'])
                ? $attendance->clock_in_at
                : Carbon::parse($workDate . ' ' . $validated['requested_clock_in_at']);

            $newClockOutAt = empty($validated['requested_clock_out_at'])
                ? $attendance->clock_out_at
                : Carbon::parse($workDate . ' ' . $validated['requested_clock_out_at']);

            $attendance->update([
                'clock_in_at' => $newClockInAt,
                'clock_out_at' => $newClockOutAt,
                'note' => $validated['requested_note'] ?? $attendance->note,
            ]);

            // Breaks
            $existingBreaks = $attendance->breaks->values(); // 0,1,2...

            $breaksInput = $validated['breaks'] ?? [];

            foreach ($breaksInput as $i => $times) {
                $in = $times['in'] ?? null;
                $out = $times['out'] ?? null;

                // 追加1行など “両方空” はスキップ
                if (empty($in) && empty($out)) {
                    continue;
                }

                $breakNo = $i + 1;

                $breakInAt = empty($in) ? null : Carbon::parse($workDate . ' ' . $in);
                $breakOutAt = empty($out) ? null : Carbon::parse($workDate . ' ' . $out);

                $existingBreak = $existingBreaks->get($breakNo - 1);

                if ($existingBreak) {
                    $existingBreak->update([
                        'break_in_at' => $breakInAt ?? $existingBreak->break_in_at,
                        'break_out_at' => $breakOutAt ?? $existingBreak->break_out_at,
                    ]);
                } else {
                    BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_in_at' => $breakInAt,
                        'break_out_at' => $breakOutAt,
                    ]);
                }

                AttendanceCorrectionRequestBreak::updateOrCreate(
                    [
                        'attendance_correction_request_id' => $attendanceCorrectionRequest->id,
                        'break_no' => $breakNo,
                    ],
                    [
                        'requested_break_in_at' => $breakInAt,
                        'requested_break_out_at' => $breakOutAt,
                    ]
                );
            }

            // 申請テーブルも「承認した内容」で更新＋承認情報
            $attendanceCorrectionRequest->update([
                'requested_clock_in_at' => $newClockInAt,
                'requested_clock_out_at' => $newClockOutAt,
                'requested_note' => $validated['requested_note'] ?? $attendanceCorrectionRequest->requested_note,

                'status' => 1, // 承認済み
                'approved_by' => $adminId,
                'approved_at' => Carbon::now(),
            ]);
        });

        // 承認処理が終わったら、同じ詳細画面に戻す
        return redirect()->route('admin.stamp_correction_request.approve', [
            'attendanceCorrectionRequest' => $attendanceCorrectionRequest->id,
        ]);

    }

    // PG12認証ミドルウェア区別用
    public function list(Request $request)
    {
        if (Auth::guard('admin')->check()) {
            return $this->adminIndex($request);
        }

        return $this->index($request);
    }

}
