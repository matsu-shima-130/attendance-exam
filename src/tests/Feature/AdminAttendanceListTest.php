<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    // 管理者ユーザーを作って、admin guard でログインできる状態にする。
    private function makeAdmin()
    {
        $adminClass = \App\Models\Admin::class;

        if (class_exists($adminClass) && method_exists($adminClass, 'factory')) {
            return $adminClass::factory()->create();
        }

        // factory が無い/未用意でも動くように保険（admins テーブルの最低限を想定）
        $adminId = DB::table('admins')->insertGetId([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $adminClass::find($adminId);
    }

    private function makeUser(string $name): User
    {
        return User::factory()->create([
            'name' => $name,
        ]);
    }

    private function makeAttendance(User $user, string $workDate, string $clockIn, string $clockOut): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'work_date' => $workDate,
            'clock_in_at' => $workDate . ' ' . $clockIn . ':00',
            'clock_out_at' => $workDate . ' ' . $clockOut . ':00',
            'status' => 3, // 管理者一覧でstatusは使ってないので適当
        ]);
    }

    private function addBreak(Attendance $attendance, string $workDate, string $breakIn, string $breakOut): void
    {
        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in_at' => $workDate . ' ' . $breakIn . ':00',
            'break_out_at' => $workDate . ' ' . $breakOut . ':00',
        ]);
    }

    // テスト内容 1：その日になされた全ユーザーの勤怠情報が正確に確認できる
    /** @test */
    public function admin_can_see_all_users_attendance_for_the_day()
    {
        // 「今日」を固定（コントローラが date パラメータ無しなら Carbon::today() を使うため）
        Carbon::setTestNow('2026-01-19 10:00:00');

        $admin = $this->makeAdmin();

        $taroUser = $this->makeUser('山田 太郎');
        $hanakoUser = $this->makeUser('佐藤 花子');

        $taroAttendance = $this->makeAttendance($taroUser, '2026-01-19', '09:00', '18:00');
        $this->addBreak($taroAttendance, '2026-01-19', '12:00', '12:30'); // 休憩 0:30

        $hanakoAttendance = $this->makeAttendance($hanakoUser, '2026-01-19', '10:15', '19:15');
        // 休憩なし

        $response = $this->actingAs($admin, 'admin')->get('/admin/attendance/list');

        $response->assertStatus(200);

        // ユーザー名が見える
        $response->assertSee('山田 太郎');
        $response->assertSee('佐藤 花子');

        // 出勤・退勤時刻が見える（H:i）
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('10:15');
        $response->assertSee('19:15');

        // 休憩合計と勤務合計が見える
        // 09:00-18:00=9:00(540分) - 0:30(30分) = 8:30
        $response->assertSee('0:30');
        $response->assertSee('8:30');

        // 10:15-19:15=9:00、休憩なしなので「休憩 0:00 / 合計 9:00」を想定
        $response->assertSee('0:00');
        $response->assertSee('9:00');
    }

    // テスト内容 2：遷移した際に現在の日付が表示される
    /** @test */
    public function admin_attendance_list_shows_today_date_by_default()
    {
        Carbon::setTestNow('2026-01-19 10:00:00');

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'admin')->get('/admin/attendance/list');

        $response->assertStatus(200);

        // コントローラの view 渡し：
        // titleDateText => Y年n月j日
        // displayDateText => Y/m/d
        $response->assertSee('2026年1月19日');
        $response->assertSee('2026/01/19');
    }

    // テスト内容 3：「前日」を押下した時に前の日の勤怠情報が表示される
    /** @test */
    public function admin_can_see_previous_day_attendance_by_date_query()
    {
        Carbon::setTestNow('2026-01-19 10:00:00');

        $admin = $this->makeAdmin();

        $previousDayUser = $this->makeUser('前日さん');
        $this->makeAttendance($previousDayUser, '2026-01-18', '09:10', '18:10');

        $response = $this->actingAs($admin, 'admin')->get('/admin/attendance/list?date=2026-01-18');

        $response->assertStatus(200);

        $response->assertSee('2026年1月18日');
        $response->assertSee('2026/01/18');

        $response->assertSee('前日さん');
        $response->assertSee('09:10');
        $response->assertSee('18:10');
    }

    // テスト内容 4：「翌日」を押下した時に次の日の勤怠情報が表示される
    /** @test */
    public function admin_can_see_next_day_attendance_by_date_query()
    {
        Carbon::setTestNow('2026-01-19 10:00:00');

        $admin = $this->makeAdmin();

        $nextDayUser = $this->makeUser('翌日さん');
        $this->makeAttendance($nextDayUser, '2026-01-20', '08:55', '17:55');

        $response = $this->actingAs($admin, 'admin')->get('/admin/attendance/list?date=2026-01-20');

        $response->assertStatus(200);

        $response->assertSee('2026年1月20日');
        $response->assertSee('2026/01/20');

        $response->assertSee('翌日さん');
        $response->assertSee('08:55');
        $response->assertSee('17:55');
    }
}
