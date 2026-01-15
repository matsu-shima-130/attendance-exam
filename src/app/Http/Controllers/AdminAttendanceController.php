<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminAttendanceUpdateRequest;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        Carbon::setLocale('ja');

        // date=YYYY-MM-DD（なければ今日）
        $dateParam = $request->query('date');
        try {
            $targetDate = $dateParam
                ? Carbon::createFromFormat('Y-m-d', $dateParam)->startOfDay()
                : Carbon::today();
        } catch (\Exception $e) {
            $targetDate = Carbon::today();
        }

        // 対象日の勤怠（休憩も一緒に）
        // ※「その日に勤怠がある人だけ」表示するため、Attendance側から取る
        $attendances = Attendance::with(['breaks', 'user:id,name'])
            ->whereDate('work_date', $targetDate->toDateString())
            ->whereNotNull('clock_in_at') // 出勤している人だけ
            ->orderBy('user_id', 'asc')
            ->get();

        $rows = [];
        foreach ($attendances as $attendance) {
            $clockIn = $attendance->clock_in_at
                ? Carbon::parse($attendance->clock_in_at)->format('H:i')
                : '';

            $clockOut = $attendance->clock_out_at
                ? Carbon::parse($attendance->clock_out_at)->format('H:i')
                : '';

            $breakMinutes = 0;
            foreach ($attendance->breaks as $breakItem) {
                if ($breakItem->break_in_at && $breakItem->break_out_at) {
                    $breakMinutes += Carbon::parse($breakItem->break_in_at)
                        ->diffInMinutes(Carbon::parse($breakItem->break_out_at));
                }
            }
            $breakTotal = $this->minutesToHm($breakMinutes);

            $workTotal = '';
            if ($attendance->clock_in_at && $attendance->clock_out_at) {
                $workMinutes = Carbon::parse($attendance->clock_in_at)
                    ->diffInMinutes(Carbon::parse($attendance->clock_out_at));

                $workMinutes = max(0, $workMinutes - $breakMinutes);
                $workTotal = $this->minutesToHm($workMinutes);
            }

            $detailUrl = route('admin.attendance.show', ['id' => $attendance->id]);

            $rows[] = [
                'name' => $attendance->user?->name ?? '',
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'break_total' => $breakTotal,
                'work_total' => $workTotal,
                'detail_url' => $detailUrl,
            ];
        }

        return view('admin.attendance.index', [
            'titleDateText' => $targetDate->format('Y年n月j日'),
            'displayDateText' => $targetDate->format('Y/m/d'),
            'prevDate' => $targetDate->copy()->subDay()->format('Y-m-d'),
            'nextDate' => $targetDate->copy()->addDay()->format('Y-m-d'),
            'rows' => $rows,
        ]);
    }

    // 詳細表示
    public function show(int $id)
    {
        Carbon::setLocale('ja');

        $attendance = Attendance::with([
            'user:id,name',
            'breaks' => function ($breakQuery) {
                $breakQuery->orderBy('id', 'asc');
            },
        ])->findOrFail($id);

        $workDate = Carbon::parse($attendance->work_date);

        $clockIn = $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '';
        $clockOut = $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '';

        $breaks = [];
        foreach ($attendance->breaks->take(2) as $breakItem) {
            $breaks[] = [
                'in' => $breakItem->break_in_at ? Carbon::parse($breakItem->break_in_at)->format('H:i') : '',
                'out' => $breakItem->break_out_at ? Carbon::parse($breakItem->break_out_at)->format('H:i') : '',
            ];
        }
        while (count($breaks) < 2) {
            $breaks[] = ['in' => '', 'out' => ''];
        }

        return view('admin.attendance.show', [
            'attendanceId' => $attendance->id,
            'userName' => $attendance->user?->name ?? '',
            'yearText' => $workDate->format('Y年'),
            'dateText' => $workDate->format('n月j日'),
            'clockIn' => $clockIn,
            'clockOut' => $clockOut,
            'breaks' => $breaks,
            'note' => $attendance->note ?? '',
        ]);
    }

    // 修正(POST)
    public function update(AdminAttendanceUpdateRequest $request, int $id): RedirectResponse
    {
        $attendance = Attendance::with(['breaks' => function ($breakQuery) {
            $breakQuery->orderBy('id', 'asc');
        }])->findOrFail($id);

        $validated = $request->validated();
        $workDate = Carbon::parse($attendance->work_date)->format('Y-m-d');

        $attendance->update([
            'clock_in_at' => Carbon::parse($workDate . ' ' . $validated['clock_in']),
            'clock_out_at' => Carbon::parse($workDate . ' ' . $validated['clock_out']),
            'note' => $validated['note'] ?? null,
        ]);

        $this->syncBreak($attendance, 1, $validated['break1_in'] ?? null, $validated['break1_out'] ?? null, $workDate);
        $this->syncBreak($attendance, 2, $validated['break2_in'] ?? null, $validated['break2_out'] ?? null, $workDate);

        return redirect()
            ->route('admin.attendance.show', ['id' => $attendance->id])
            ->with('just_updated', true);

    }

    private function syncBreak(Attendance $attendance, int $breakNo, ?string $breakIn, ?string $breakOut, string $workDate): void
    {
        $breakIndex = $breakNo - 1;
        $existingBreak = $attendance->breaks->get($breakIndex);

        $isEmpty = empty($breakIn) && empty($breakOut);

        if ($isEmpty) {
            if ($existingBreak) {
                $existingBreak->delete();
            }
            return;
        }

        $breakInAt = empty($breakIn) ? null : Carbon::parse($workDate . ' ' . $breakIn);
        $breakOutAt = empty($breakOut) ? null : Carbon::parse($workDate . ' ' . $breakOut);

        if ($existingBreak) {
            $existingBreak->update([
                'break_in_at' => $breakInAt,
                'break_out_at' => $breakOutAt,
            ]);
            return;
        }

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in_at' => $breakInAt,
            'break_out_at' => $breakOutAt,
        ]);
    }

    private function minutesToHm(int $minutes): string
    {
        if ($minutes < 0) {
            return '';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return $hours . ':' . str_pad((string) $mins, 2, '0', STR_PAD_LEFT);
    }

    public function staff(Request $request, int $id)
    {
        Carbon::setLocale('ja');

        $user = User::select(['id', 'name'])->findOrFail($id);

        // month=YYYY-MM（なければ今月）
        $monthParam = $request->query('month');
        $targetMonth = $monthParam
            ? Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $startDate = $targetMonth->copy()->startOfMonth();
        $endDate = $targetMonth->copy()->endOfMonth();

        // 対象スタッフのその月の勤怠をまとめて取得（休憩も一緒に）
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
                        $breakMinutes += Carbon::parse($break->break_in_at)
                            ->diffInMinutes(Carbon::parse($break->break_out_at));
                    }
                }
                $breakTotal = $this->minutesToHm($breakMinutes);

                // 勤務合計（出勤〜退勤の差 - 休憩）
                if ($attendance->clock_in_at && $attendance->clock_out_at) {
                    $workMinutes = Carbon::parse($attendance->clock_in_at)
                        ->diffInMinutes(Carbon::parse($attendance->clock_out_at));
                    $workMinutes = max(0, $workMinutes - $breakMinutes);
                    $workTotal = $this->minutesToHm($workMinutes);
                }
            }

            $rows[] = [
                'date' => $date->format('m/d') . '(' . $date->locale('ja')->isoFormat('ddd') . ')',
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'break_total' => $breakTotal,
                'work_total' => $workTotal,
                'detail_id' => $detailId,
            ];
        }

        $prevMonth = $targetMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $targetMonth->copy()->addMonth()->format('Y-m');

        return view('admin.attendance.staff', [
            'userId' => $user->id,
            'userName' => $user->name,
            'monthText' => $targetMonth->format('Y/m'),
            'monthParam' => $targetMonth->format('Y-m'),
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'rows' => $rows,
        ]);
    }

    public function exportStaffMonthlyCsv(Request $request, int $id)
    {
        Carbon::setLocale('ja');

        $user = User::select(['id', 'name'])->findOrFail($id);

        // month=YYYY-MM（なければ今月）
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

        // CSVファイル名
        $fileName = $user->name . '_勤怠_' . $targetMonth->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($startDate, $endDate, $attendances) {

            $stream = fopen('php://output', 'w');

            // Excelで文字化けしにくいようにUTF-8 BOMを付ける
            fwrite($stream, "\xEF\xBB\xBF");

            // ヘッダー行（好きに増やしてOK）
            fputcsv($stream, ['日付', '出勤', '退勤', '休憩', '合計']);

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateKey = $date->toDateString();
                $attendance = $attendances->get($dateKey);

                $clockIn = '';
                $clockOut = '';
                $breakTotal = '';
                $workTotal = '';

                if ($attendance) {
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
                            $breakMinutes += Carbon::parse($break->break_in_at)
                                ->diffInMinutes(Carbon::parse($break->break_out_at));
                        }
                    }
                    $breakTotal = $this->minutesToHm($breakMinutes);

                    // 勤務合計（出勤〜退勤の差 - 休憩）
                    if ($attendance->clock_in_at && $attendance->clock_out_at) {
                        $workMinutes = Carbon::parse($attendance->clock_in_at)
                            ->diffInMinutes(Carbon::parse($attendance->clock_out_at));
                        $workMinutes = max(0, $workMinutes - $breakMinutes);
                        $workTotal = $this->minutesToHm($workMinutes);
                    }
                }

                // 日付はCSVでは「YYYY/MM/DD(曜)」が見やすいのでこれおすすめ
                $dateText = $date->format('Y/m/d') . '(' . $date->locale('ja')->isoFormat('ddd') . ')';

                fputcsv($stream, [$dateText, $clockIn, $clockOut, $breakTotal, $workTotal]);
            }

            fclose($stream);

        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
