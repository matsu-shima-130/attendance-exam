<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テストをいつ実行しても結果が変わらないように、"今" を固定
        Carbon::setTestNow(Carbon::create(2026, 1, 15, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // 固定解除
        parent::tearDown();
    }

    // テスト内容 1：自分が行った勤怠情報が全て表示されている
    /** @test */
    public function user_sees_only_their_own_attendance_records_in_the_list()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $otherUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 自分の勤怠（2026-01）
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-01-10',
            'clock_in_at' => '2026-01-10 09:00:00',
            'clock_out_at' => '2026-01-10 18:00:00',
            'status' => 3,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-01-11',
            'clock_in_at' => '2026-01-11 09:30:00',
            'clock_out_at' => '2026-01-11 17:30:00',
            'status' => 3,
        ]);

        // 他人の勤怠（同じ月に入れておく。これが表示されたらアウト）
        Attendance::create([
            'user_id' => $otherUser->id,
            'work_date' => '2026-01-10',
            'clock_in_at' => '2026-01-10 07:07:00',
            'clock_out_at' => '2026-01-10 16:16:00',
            'status' => 3,
        ]);

        $response = $this->actingAs($user)->get('/attendance/list?month=2026-01');
        $response->assertStatus(200);

        // 自分の打刻が表示される
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('09:30');
        $response->assertSee('17:30');

        // 他人の打刻は表示されない
        $response->assertDontSee('07:07');
        $response->assertDontSee('16:16');
    }

    // テスト内容 2：勤怠一覧画面に遷移した際に現在の月が表示される
    /** @test */
    public function current_month_is_shown_when_opening_the_list_page()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // monthパラメータなし → Carbon::now() の月（setTestNowで2026/01固定）
        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);

        $response->assertSee('2026/01');

    }

    // テスト内容 3：「前月」を押下した時に表示月の前月の情報が表示される
    /** @test */
    public function previous_month_records_are_shown_when_month_is_set_to_previous()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 前月（2025-12）の勤怠を用意
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2025-12-20',
            'clock_in_at' => '2025-12-20 08:08:00',
            'clock_out_at' => '2025-12-20 17:17:00',
            'status' => 3,
        ]);

        $response = $this->actingAs($user)->get('/attendance/list?month=2025-12');
        $response->assertStatus(200);

        // 表示月が前月になっている
        $response->assertSee('2025/12');

        // 前月の勤怠が見える
        $response->assertSee('08:08');
        $response->assertSee('17:17');
    }

    // テスト内容 4：「翌月」を押下した時に表示月の翌月の情報が表示される
    /** @test */
    public function next_month_records_are_shown_when_month_is_set_to_next()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 翌月（2026-02）の勤怠を用意
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-02-01',
            'clock_in_at' => '2026-02-01 10:10:00',
            'clock_out_at' => '2026-02-01 19:19:00',
            'status' => 3,
        ]);

        $response = $this->actingAs($user)->get('/attendance/list?month=2026-02');
        $response->assertStatus(200);

        // 表示月が翌月になっている
        $response->assertSee('2026/02');

        // 翌月の勤怠が見える
        $response->assertSee('10:10');
        $response->assertSee('19:19');
    }

    // テスト内容 5：「詳細」を押下すると、その日の勤怠詳細画面に遷移する
    /** @test */
    public function detail_link_navigates_to_attendance_detail_page()
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

        $response = $this->actingAs($user)->get('/attendance/list?month=2026-01');
        $response->assertStatus(200);

        // 「詳細」リンクが詳細ページのURLになっている
        $response->assertSee('/attendance/detail/' . $attendance->id, false);
    }
}
