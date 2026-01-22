<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceBreakTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト内の「今」を固定（いつ実行しても同じ結果になる）
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 9, 0, 0));
    }

    private function createWorkingUser(): User
    {
        // 出勤中のユーザー状態を作る（今日の勤怠があり、退勤は未）
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => Carbon::today()->toDateString(),
            'clock_in_at' => Carbon::now(), // 09:00
            'clock_out_at' => null,
            'status' => 1, // 出勤中
        ]);

        return $user;
    }

    // テスト内容 1：休憩入ボタンが正しく機能する（出勤中→休憩中）
    /** @test */
    public function break_in_button_works_and_status_becomes_breaking()
    {
        $user = $this->createWorkingUser();

        // 画面に「休憩入」ボタンが表示されていること（＝break_inフォームがある）
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');
        $response->assertSee('value="break_in"', false);

        // 休憩入の処理
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 12, 0, 0));
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);
        $response->assertRedirect('/attendance');

        // 休憩レコードが作られている
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', Carbon::today())
            ->firstOrFail();

        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
        ]);

        // 勤怠ステータスが休憩中になる
        $attendance->refresh();
        $this->assertEquals(2, $attendance->status);

        // 画面上のステータスが「休憩中」になっている
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('休憩中');
        $response->assertSee('value="break_out"', false); // 休憩戻が出る
    }

    // テスト内容 2：休憩は一日に何回でもできる（入→戻→入 ができる）
    /** @test */
    public function break_can_be_taken_multiple_times_in_a_day()
    {
        $user = $this->createWorkingUser();

        // 1回目 休憩入
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 12, 0, 0));
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        // 1回目 休憩戻
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 12, 30, 0));
        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);

        // 「休憩入」ボタンが再び表示されること
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');
        $response->assertSee('value="break_in"', false);
    }

    // テスト内容 3：休憩戻ボタンが正しく機能する（休憩中→出勤中）
    /** @test */
    public function break_out_button_works_and_status_becomes_working()
    {
        $user = $this->createWorkingUser();

        // 休憩入して「休憩中」状態を作る
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 12, 0, 0));
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        // 休憩戻ボタンが表示されていること
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩戻');
        $response->assertSee('value="break_out"', false);

        // 休憩戻
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 12, 30, 0));
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);
        $response->assertRedirect('/attendance');

        // 勤怠ステータスが出勤中に戻る
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', Carbon::today())
            ->firstOrFail();

        $this->assertEquals(1, $attendance->status);

        // 画面上のステータスが「出勤中」になっている
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('出勤中');
        $response->assertSee('value="break_in"', false);
    }

    // テスト内容 4：休憩戻は一日に何回でもできる（入→戻→入→戻 ができる）
    /** @test */
    public function break_out_can_be_done_multiple_times_in_a_day()
    {
        $user = $this->createWorkingUser();

        // 1回目 入→戻
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 12, 0, 0));
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 12, 30, 0));
        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);

        // 2回目 入
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 15, 0, 0));
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        // 「休憩戻」ボタンが表示されること
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩戻');
        $response->assertSee('value="break_out"', false);
    }

    // テスト内容 5：休憩時刻が勤怠一覧画面で確認できる
    /** @test */
    public function break_time_can_be_seen_in_attendance_list()
    {
        $user = $this->createWorkingUser();

        // 休憩入 12:00
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 12, 0, 0));
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        // 休憩戻 12:30
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 12, 30, 0));
        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);

        // 一覧（対象月を明示）
        $response = $this->actingAs($user)->get('/attendance/list?month=2026-01');
        $response->assertStatus(200);

        // 合計表示
        $response->assertSee('0:30');

        // 休憩時刻は title 属性に入っている（= 一覧画面で確認できる）
        $response->assertSee('title="12:00〜12:30"', false);
    }
}
