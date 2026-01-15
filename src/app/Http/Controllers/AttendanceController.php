<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionStoreRequest;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceCorrectionRequestBreak;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        Carbon::setLocale('ja');

        $user = Auth::user();
        $today = Carbon::today();
        $now = Carbon::now();

        // 今日の勤怠を取得（なければnull）
        $attendance = Attendance::with(['breaks' => function ($breakQuery) {
            $breakQuery->orderBy('id', 'asc');
        }])
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        // ステータス判定
        // before=勤務外 / working=出勤中 / breaking=休憩中 / after=退勤済
        $status = 'before';

        if ($attendance) {
            if (!is_null($attendance->clock_out_at)) {
                $status = 'after';
            } else {
                $latestBreak = $attendance->breaks->last();
                if ($latestBreak && is_null($latestBreak->break_out_at)) {
                    $status = 'breaking';
                } else {
                    $status = 'working';
                }
            }
        }

        $statusLabelMap = [
            'before' => '勤務外',
            'working' => '出勤中',
            'breaking' => '休憩中',
            'after' => '退勤済',
        ];

        return view('attendance.index', [
            'status' => $status,
            'statusLabel' => $statusLabelMap[$status],
            'dateText' => $today->locale('ja')->isoFormat('YYYY年M月D日(ddd)'),
            'timeText' => $now->format('H:i'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'action' => ['required', 'in:clock_in,break_in,break_out,clock_out'],
        ]);

        $user = Auth::user();
        $today = Carbon::today();
        $now = Carbon::now();

        DB::transaction(function () use ($request, $user, $today, $now) {
            // 今日の勤怠（なければ作るのは clock_in のときだけ）
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('work_date', $today)
                ->lockForUpdate()
                ->first();

            $action = $request->input('action');

            // status tinyint の運用（例）
            // 0:勤務外 1:出勤中 2:休憩中 3:退勤済
            if ($action === 'clock_in') {
                // 1日1回だけ
                if ($attendance) {
                    return;
                }

                Attendance::create([
                    'user_id' => $user->id,
                    'work_date' => $today,
                    'clock_in_at' => $now,
                    'status' => 1,
                ]);
                return;
            }

            // clock_in 以外は勤怠がないと無理
            if (!$attendance) {
                return;
            }

            // 退勤済なら何もしない
            if (!is_null($attendance->clock_out_at)) {
                return;
            }

            if ($action === 'break_in') {
                // 休憩入は何回でもOK（出勤中のときだけ想定）
                // 直前の休憩が開きっぱなしなら作らない
                $openBreak = BreakTime::where('attendance_id', $attendance->id)
                    ->whereNull('break_out_at')
                    ->lockForUpdate()
                    ->first();

                if ($openBreak) {
                    return;
                }

                BreakTime::create([
                    'attendance_id' => $attendance->id,
                    'break_in_at' => $now,
                ]);

                $attendance->update(['status' => 2]);
                return;
            }

            if ($action === 'break_out') {
                // 開いてる休憩を閉じる
                $openBreak = BreakTime::where('attendance_id', $attendance->id)
                    ->whereNull('break_out_at')
                    ->lockForUpdate()
                    ->first();

                if (!$openBreak) {
                    return;
                }

                $openBreak->update(['break_out_at' => $now]);
                $attendance->update(['status' => 1]);
                return;
            }

            if ($action === 'clock_out') {
                // 退勤は1日1回だけ
                $attendance->update([
                    'clock_out_at' => $now,
                    'status' => 3,
                ]);
                return;
            }
        });

        return redirect()->route('attendance');
    }

    public function list(Request $request)
    {
        $user = Auth::user();

        // month=YYYY-MM を受け取る（なければ今月）
        $monthParam = $request->query('month');
        $targetMonth = $monthParam
            ? Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $startDate = $targetMonth->copy()->startOfMonth();
        $endDate = $targetMonth->copy()->endOfMonth();

        // その月の勤怠をまとめて取得（休憩も一緒に）
        $attendances = Attendance::with(['breaks'])
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(function ($attendance) {
                return Carbon::parse($attendance->work_date)->toDateString(); // YYYY-MM-DD
            });

        // 1日〜月末まで全部の行を作る（勤怠が無い日は空欄）
        $rows = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->toDateString();
            $attendance = $attendances->get($dateKey);

            $clockIn = '';
            $clockOut = '';
            $breakTotal = '';
            $workTotal = '';
            $detailId = null;

            if ($attendance) {
                $detailId = $attendance->id;

                if ($attendance->clock_in_at) {
                    $clockIn = Carbon::parse($attendance->clock_in_at)->format('H:i');
                }
                if ($attendance->clock_out_at) {
                    $clockOut = Carbon::parse($attendance->clock_out_at)->format('H:i');
                }

                // 休憩合計（分）
                $breakMinutes = 0;
                foreach ($attendance->breaks as $break) {
                    if ($break->break_in_at && $break->break_out_at) {
                        $breakMinutes += Carbon::parse($break->break_in_at)->diffInMinutes(Carbon::parse($break->break_out_at));
                    }
                }
                $breakTotal = $this->minutesToHm($breakMinutes);

                // 勤務合計（出勤〜退勤の差 - 休憩）
                if ($attendance->clock_in_at && $attendance->clock_out_at) {
                    $workMinutes = Carbon::parse($attendance->clock_in_at)->diffInMinutes(Carbon::parse($attendance->clock_out_at));
                    $workMinutes = max(0, $workMinutes - $breakMinutes);
                    $workTotal = $this->minutesToHm($workMinutes);
                }
            }

            $rows[] = [
                'date' => $date->format('m/d') . '(' . $date->locale('ja')->isoFormat('ddd') . ')', // 06/01(木)
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'break_total' => $breakTotal,
                'work_total' => $workTotal,
                'detail_id' => $detailId,
            ];
        }

        $prevMonth = $targetMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $targetMonth->copy()->addMonth()->format('Y-m');

        return view('attendance.list', [
            'monthText' => $targetMonth->format('Y/m'),
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'rows' => $rows,
        ]);
    }

    // 分 → "H:MM" 形式にする
    private function minutesToHm(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return $hours . ':' . str_pad((string)$mins, 2, '0', STR_PAD_LEFT);
    }

    public function detail(int $id)
    {
        Carbon::setLocale('ja');

        $user = Auth::user();

        $attendance = Attendance::with(['breaks' => function ($breakQuery) {
            $breakQuery->orderBy('id', 'asc');
        }])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // 承認待ち（status=0）
        $pendingRequest = AttendanceCorrectionRequest::with(['breaks' => function ($breakQuery) {
            $breakQuery->orderBy('break_no', 'asc');
        }])
            ->where('user_id', $user->id)
            ->where('attendance_id', $attendance->id)
            ->where('status', 0)
            ->latest('id')
            ->first();

        $workDate = Carbon::parse($attendance->work_date);


        // 元の値（勤怠）
        $baseClockIn  = $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '';
        $baseClockOut = $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '';

        // 元の休憩（最大2枠の前提で今はOK）
        $baseBreaks = [];
        foreach ($attendance->breaks->take(2) as $break) {
            $baseBreaks[] = [
                'in'  => $break->break_in_at ? Carbon::parse($break->break_in_at)->format('H:i') : '',
                'out' => $break->break_out_at ? Carbon::parse($break->break_out_at)->format('H:i') : '',
            ];
        }
        while (count($baseBreaks) < 2) {
            $baseBreaks[] = ['in' => '', 'out' => ''];
        }

        // ★表示用：承認待ちがあれば「申請内容」を優先（nullなら元の値にフォールバック）
        if ($pendingRequest) {
            $clockIn = $pendingRequest->requested_clock_in_at
                ? Carbon::parse($pendingRequest->requested_clock_in_at)->format('H:i')
                : $baseClockIn;

            $clockOut = $pendingRequest->requested_clock_out_at
                ? Carbon::parse($pendingRequest->requested_clock_out_at)->format('H:i')
                : $baseClockOut;

            $breaks = $baseBreaks;
            foreach ([1, 2] as $no) {
                $reqBreak = $pendingRequest->breaks->firstWhere('break_no', $no);
                if ($reqBreak) {
                    $breaks[$no - 1]['in'] = $reqBreak->requested_break_in_at
                        ? Carbon::parse($reqBreak->requested_break_in_at)->format('H:i')
                        : $breaks[$no - 1]['in'];

                    $breaks[$no - 1]['out'] = $reqBreak->requested_break_out_at
                        ? Carbon::parse($reqBreak->requested_break_out_at)->format('H:i')
                        : $breaks[$no - 1]['out'];
                }
            }

            $note = !is_null($pendingRequest->requested_note) ? $pendingRequest->requested_note : ($attendance->note ?? '');
            $isPending = true;
        } else {
            $clockIn = $baseClockIn;
            $clockOut = $baseClockOut;
            $breaks = $baseBreaks;
            $note = $attendance->note ?? '';
            $isPending = false;
        }

        return view('attendance.detail', [
            'attendanceId' => $attendance->id,
            'userName' => $user->name,
            'isPending' => $isPending,

            'yearText' => $workDate->format('Y年'),
            'dateText' => $workDate->format('n月j日'),

            'clockIn' => $clockIn,
            'clockOut' => $clockOut,
            'breaks' => $breaks,
            'note' => $note,
        ]);
    }


    public function requestCorrection(AttendanceCorrectionStoreRequest $request, int $id)
    {
        $user = Auth::user();

        $attendance = Attendance::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // 承認待ちがあるなら二重申請させない
        $alreadyPending = AttendanceCorrectionRequest::where('user_id', $user->id)
            ->where('attendance_id', $attendance->id)
            ->where('status', 0)
            ->exists();

        if ($alreadyPending) {
            return redirect()->route('attendance.detail', ['id' => $attendance->id]);
        }

        $validated = $request->validated();
        $workDate = Carbon::parse($attendance->work_date)->format('Y-m-d');

        DB::transaction(function () use ($user, $attendance, $validated, $workDate) {
            $correction = AttendanceCorrectionRequest::create([
                'user_id' => $user->id,
                'attendance_id' => $attendance->id,
                'requested_clock_in_at' => empty($validated['requested_clock_in_at'])
                    ? null
                    : Carbon::parse($workDate . ' ' . $validated['requested_clock_in_at']),
                'requested_clock_out_at' => empty($validated['requested_clock_out_at'])
                    ? null
                    : Carbon::parse($workDate . ' ' . $validated['requested_clock_out_at']),
                'requested_note' => $validated['requested_note'] ?? null,
                'status' => 0, // 承認待ち
            ]);

            $breakInputs = [
                1 => ['in' => $validated['break1_in'] ?? null, 'out' => $validated['break1_out'] ?? null],
                2 => ['in' => $validated['break2_in'] ?? null, 'out' => $validated['break2_out'] ?? null],
            ];

            foreach ($breakInputs as $breakNo => $times) {
                if (empty($times['in']) && empty($times['out'])) {
                    continue;
                }

                AttendanceCorrectionRequestBreak::create([
                    'attendance_correction_request_id' => $correction->id,
                    'break_no' => $breakNo,
                    'requested_break_in_at' => empty($times['in']) ? null : Carbon::parse($workDate . ' ' . $times['in']),
                    'requested_break_out_at' => empty($times['out']) ? null : Carbon::parse($workDate . ' ' . $times['out']),
                ]);
            }
        });

        return redirect()->route('attendance.detail', ['id' => $attendance->id]);
    }

}
