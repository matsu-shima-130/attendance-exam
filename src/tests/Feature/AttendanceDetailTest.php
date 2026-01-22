<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    // テスト内容 1：勤怠詳細画面の「名前」がログインユーザーの氏名になっている
    /** @test */
    public function detail_page_shows_logged_in_user_name()
    {
        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-01-10',
            'clock_in_at' => '2026-01-10 09:00:00',
            'clock_out_at' => '2026-01-10 18:00:00',
            'status' => 3,
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);
        $response->assertStatus(200);

        // detail.blade.php の「名前」欄に表示される
        $response->assertSee('テスト太郎');
    }

    // テスト内容 2：勤怠詳細画面の「日付」が選択した日付になっている
    /** @test */
    public function detail_page_shows_selected_date()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-01-10',
            'clock_in_at' => '2026-01-10 09:00:00',
            'clock_out_at' => '2026-01-10 18:00:00',
            'status' => 3,
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);
        $response->assertStatus(200);

        // detail.blade.php では yearText / dateText をそのまま表示している
        $response->assertSee('2026年');
        $response->assertSee('1月10日');
    }

    // テスト内容 3：「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致している
    /** @test */
    public function detail_page_shows_clock_in_and_out_time()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-01-10',
            'clock_in_at' => '2026-01-10 09:00:00',
            'clock_out_at' => '2026-01-10 18:00:00',
            'status' => 3,
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);
        $response->assertStatus(200);

        // detail.blade.php の入力欄 value に入っているかを確認
        $response->assertSee('value="09:00"', false);
        $response->assertSee('value="18:00"', false);
    }

    // テスト内容 4：「休憩」にて記されている時間がログインユーザーの打刻と一致している
    /** @test */
    public function detail_page_shows_break_times()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-01-10',
            'clock_in_at' => '2026-01-10 09:00:00',
            'clock_out_at' => '2026-01-10 18:00:00',
            'status' => 3,
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in_at' => '2026-01-10 12:00:00',
            'break_out_at' => '2026-01-10 12:30:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);
        $response->assertStatus(200);

        // 休憩も input value に入っているので同じ方式で確認
        $response->assertSee('value="12:00"', false);
        $response->assertSee('value="12:30"', false);
    }
}
