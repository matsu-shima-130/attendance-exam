<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Carbon\Carbon;

class UserAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        // 一般ユーザー5人（メール認証済みにしておくと verified ミドルウェアでも動作確認しやすい）
        $users = User::factory()->count(5)->create([
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        // 前月、今月、翌月の3か月分
        $start = now()->subMonths()->startOfMonth();
        $end   = now()->endOfMonth();

        foreach ($users as $user) {
            $date = $start->copy();

            while ($date->lte($end)) {

                // 土日を除外（必要なら消してOK）
                if ($date->isWeekend()) {
                    $date->addDay();
                    continue;
                }

                // たまに休みを作る（20%）
                if (mt_rand(1, 100) <= 20) {
                    $date->addDay();
                    continue;
                }

                // 出勤/退勤（少し揺らしてリアルにしています）
                $clockIn  = $date->copy()->setTime(9, mt_rand(0, 15));   // 09:00〜09:15
                $clockOut = $date->copy()->setTime(18, mt_rand(0, 20));  // 18:00〜18:20

                // 勤怠作成（退勤済）
                $attendance = Attendance::create([
                    'user_id'      => $user->id,
                    'work_date'    => $date->toDateString(),
                    'clock_in_at'  => $clockIn,
                    'clock_out_at' => $clockOut,
                    'status'       => 3, // 退勤済（※番号が違うならここを修正）
                    'note'         => (mt_rand(1, 100) <= 15) ? 'テスト備考です' : null,
                ]);

                // 休憩（0〜2回）
                $breakCount = mt_rand(0, 2);

                for ($breakIndex = 0; $breakIndex < $breakCount; $breakIndex++) {
                    // 12:00開始、2回目は14:00開始みたいにずらす
                    $breakIn  = $date->copy()->setTime(12 + ($breakIndex * 2), mt_rand(0, 10));
                    $breakOut = $breakIn->copy()->addMinutes(30);

                    BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_in_at'   => $breakIn,
                        'break_out_at'  => $breakOut,
                    ]);
                }

                $date->addDay();
            }
        }
    }
}
