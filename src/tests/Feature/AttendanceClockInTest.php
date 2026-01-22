<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceClockInTest extends TestCase
{
    use RefreshDatabase;

    // テスト内容 1：勤務外のとき「出勤」ボタンが表示され、出勤処理後にステータスが「出勤中」になる
    /** @test */
    public function clock_in_button_works_and_status_becomes_working()
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 9, 0, 0));

        $user = User::factory()->create([
            'email_verified_at' => now(), // /attendance は verified が必要
        ]);

        // 1. 勤務外で勤怠画面を開く → 出勤ボタンがある
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');
        $response->assertSee('value="clock_in"', false);

        // 2. 出勤処理（POST）
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        // 3. リダイレクト後、ステータス表示が出勤中になる
        $response->assertRedirect('/attendance');
        $follow = $this->actingAs($user)->get('/attendance');
        $follow->assertStatus(200);
        $follow->assertSee('出勤中');

        // DBにも保存されている
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => Carbon::today()->toDateString(),
            'status' => 1,
        ]);
    }

    // テスト内容 2：退勤済のとき「出勤」は表示されない（出勤は一日一回のみ）
    /** @test */
    public function clock_in_button_is_not_shown_after_clock_out()
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 18, 0, 0));

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => Carbon::today(),
            'clock_in_at' => Carbon::now()->subHours(9),
            'clock_out_at' => Carbon::now(),
            'status' => 3,
            'note' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);

        // clock_inのinputが無いことを確認
        $response->assertDontSee('value="clock_in"', false);
    }

    // テスト内容 3：出勤時刻が勤怠一覧画面で確認できる
    /** @test */
    public function clock_in_time_can_be_seen_on_attendance_list()
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 9, 7, 0));

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 出勤処理
        $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ])->assertRedirect('/attendance');

        // 勤怠一覧（対象月を明示）
        $response = $this->actingAs($user)->get('/attendance/list?month=2026-01');
        $response->assertStatus(200);

        // 09:07 が表示されていること（list側の表示が H:i 前提）
        $response->assertSee('09:07');
    }
}
