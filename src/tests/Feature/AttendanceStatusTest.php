<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    // テスト内容 1：勤務外の場合、勤怠ステータスが正しく表示される
    /** @test */
    public function status_is_before_when_no_attendance_today()
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 9, 0, 0));

        $user = User::factory()->create([
            'email_verified_at' => now(), // /attendance は verified が必要
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('勤務外');
    }

    // テスト内容 2：出勤中の場合、勤怠ステータスが正しく表示される
    /** @test */
    public function status_is_working_when_clocked_in_and_not_clocked_out_and_not_breaking()
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 9, 0, 0));

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => Carbon::today(),
            'clock_in_at' => Carbon::now(),
            'clock_out_at' => null,
            'status' => 1,
            'note' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');
    }

    // テスト内容 3：休憩中の場合、勤怠ステータスが正しく表示される
    /** @test */
    public function status_is_breaking_when_latest_break_has_no_break_out()
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 12, 0, 0));

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => Carbon::today(),
            'clock_in_at' => Carbon::now()->subHours(3),
            'clock_out_at' => null,
            'status' => 2,
            'note' => null,
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in_at' => Carbon::now(),
            'break_out_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');
    }

    // テスト内容 4：退勤済の場合、勤怠ステータスが正しく表示される
    /** @test */
    public function status_is_after_when_clock_out_exists()
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
        $response->assertSee('退勤済');
    }
}
