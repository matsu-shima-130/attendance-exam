<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceClockOutTest extends TestCase
{
    use RefreshDatabase;

    // テスト内容 1：退勤ボタンが正しく機能し、処理後にステータスが「退勤済」になる
    /** @test */
    public function clock_out_button_works_and_status_becomes_after()
    {
        // 時刻固定（いつ実行しても同じ結果になる）
        Carbon::setTestNow(Carbon::create(2026, 1, 18, 18, 10, 0)); // 2026-01-18 18:10:00

        $user = User::factory()->create();

        // 「勤務中」状態を作る（出勤済・退勤未）
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => Carbon::today(),
            'clock_in_at' => Carbon::create(2026, 1, 18, 9, 0, 0),
            'clock_out_at' => null,
            'status' => 1, // 出勤中
        ]);

        // 勤怠打刻画面を開く → 「退勤」ボタン（clock_out）が存在すること
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('value="clock_out"', false);

        // 退勤処理
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_out',
        ]);
        $response->assertRedirect('/attendance');

        // DBに退勤時刻が入って、ステータスが退勤済になること
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => Carbon::today()->toDateString(),
            'status' => 3, // 退勤済
        ]);

        // 退勤時刻が確実に入っていることも確認（値そのものはDBの型/保存形式に依存するので null じゃないチェック）
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', Carbon::today())
            ->first();
        $this->assertNotNull($attendance->clock_out_at);

        // 再表示で「退勤済」表示になっていること（表示文言チェック）
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤済');
    }

    // テスト内容 2：退勤時刻が勤怠一覧画面で確認できる
    /** @test */
    public function clock_out_time_is_visible_on_attendance_list()
    {
        // 時刻固定（一覧の month 指定もブレない）
        Carbon::setTestNow(Carbon::create(2026, 1, 18, 18, 10, 0)); // 2026-01-18 18:10:00

        $user = User::factory()->create();

        // 出勤 → 退勤の状態を作る
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => Carbon::today(),
            'clock_in_at' => Carbon::create(2026, 1, 18, 9, 0, 0),
            'clock_out_at' => Carbon::create(2026, 1, 18, 18, 10, 0),
            'status' => 3, // 退勤済
        ]);

        // 勤怠一覧（対象月を固定して表示）
        $response = $this->actingAs($user)->get('/attendance/list?month=2026-01');
        $response->assertStatus(200);

        // 一覧の表示は H:i 形式なので「18:10」が表示されること
        $response->assertSee('18:10');
    }
}
