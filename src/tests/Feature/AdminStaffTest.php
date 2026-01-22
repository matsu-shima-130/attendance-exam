<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use App\Models\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 2, 15, 9, 0, 0));
    }

    private function actingAsAdmin(): Admin
    {
        $adminUser = Admin::factory()->create();
        $this->actingAs($adminUser, 'admin');
        return $adminUser;
    }

    private function createUserWithAttendanceInMonth(User $user, string $workDate): Attendance
    {
        // 例: $workDate = '2026-01-10'
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => $workDate,
            'clock_in_at' => Carbon::parse($workDate . ' 09:00'),
            'clock_out_at' => Carbon::parse($workDate . ' 18:00'),
            'status' => 3,
            'note' => 'テスト備考',
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in_at' => Carbon::parse($workDate . ' 12:00'),
            'break_out_at' => Carbon::parse($workDate . ' 12:30'),
        ]);

        return $attendance;
    }

    // テスト内容 1：管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる
    /** @test */
    public function admin_can_see_all_users_name_and_email_on_staff_list()
    {
        $this->actingAsAdmin();

        $userA = User::factory()->create(['name' => '田中 太郎', 'email' => 'taro@example.com']);
        $userB = User::factory()->create(['name' => '佐藤 花子', 'email' => 'hanako@example.com']);

        $response = $this->get(route('admin.staff.index'));

        $response->assertStatus(200);
        $response->assertSee('田中 太郎');
        $response->assertSee('taro@example.com');
        $response->assertSee('佐藤 花子');
        $response->assertSee('hanako@example.com');

        // 月次勤怠のリンクが出てること（詳細リンク）
        $response->assertSee('詳細');
    }

    // テスト内容 2：ユーザーの勤怠情報が正しく表示される
    /** @test */
    public function staff_monthly_attendance_shows_correct_attendance_data()
    {
        $this->actingAsAdmin();

        $staffUser = User::factory()->create(['name' => '山田 次郎']);
        $attendance = $this->createUserWithAttendanceInMonth($staffUser, '2026-02-10');

        // 対象ユーザーの月次勤怠
        $response = $this->get("/admin/attendance/staff/{$staffUser->id}?month=2026-02");

        $response->assertStatus(200);

        // 月表示
        $response->assertSee('2026/02');

        // 勤怠情報（出勤・退勤）
        $response->assertSee('09:00');
        $response->assertSee('18:00');

        // 休憩合計 0:30 / 勤務合計 8:30 の想定
        $response->assertSee('0:30');
        $response->assertSee('8:30');

        // 詳細リンク（その日の勤怠詳細へ）
        $response->assertSee("/admin/attendance/{$attendance->id}");
    }

    // テスト内容 3：「前月」を押下した時に表示月の前月の情報が表示される
    /** @test */
    public function clicking_prev_month_shows_previous_month_data()
    {
        $this->actingAsAdmin();

        $staffUser = User::factory()->create(['name' => '前月テスト']);
        $this->createUserWithAttendanceInMonth($staffUser, '2026-01-10'); // 前月データ
        $this->createUserWithAttendanceInMonth($staffUser, '2026-02-10'); // 当月データ

        $response = $this->get("/admin/attendance/staff/{$staffUser->id}?month=2026-02");
        $response->assertStatus(200);

        // 「前月」リンクが 2026-01 を指してること
        $response->assertSee('month=2026-01');

        // 前月ページに移動した想定でGETして、前月データが見えること
        $prevResponse = $this->get("/admin/attendance/staff/{$staffUser->id}?month=2026-01");
        $prevResponse->assertStatus(200);
        $prevResponse->assertSee('2026/01');

        // 2026-01-10 のレコードが見える（09:00など）
        $prevResponse->assertSee('09:00');
        $prevResponse->assertSee('18:00');
    }

    // テスト内容 4：「翌月」を押下した時に表示月の翌月の情報が表示される
    /** @test */
    public function clicking_next_month_shows_next_month_data()
    {
        $this->actingAsAdmin();

        $staffUser = User::factory()->create(['name' => '翌月テスト']);
        $this->createUserWithAttendanceInMonth($staffUser, '2026-02-10'); // 当月データ
        $this->createUserWithAttendanceInMonth($staffUser, '2026-03-10'); // 翌月データ

        $response = $this->get("/admin/attendance/staff/{$staffUser->id}?month=2026-02");
        $response->assertStatus(200);

        // 「翌月」リンクが 2026-03 を指してること
        $response->assertSee('month=2026-03');

        $nextResponse = $this->get("/admin/attendance/staff/{$staffUser->id}?month=2026-03");
        $nextResponse->assertStatus(200);
        $nextResponse->assertSee('2026/03');
        $nextResponse->assertSee('09:00');
        $nextResponse->assertSee('18:00');
    }

    // テスト内容 5：「詳細」を押下すると、その日の勤怠詳細画面に遷移する
    /** @test */
    public function clicking_detail_goes_to_attendance_detail_page()
    {
        $this->actingAsAdmin();

        $staffUser = User::factory()->create(['name' => '詳細遷移テスト']);
        $attendance = $this->createUserWithAttendanceInMonth($staffUser, '2026-02-10');

        $monthlyResponse = $this->get("/admin/attendance/staff/{$staffUser->id}?month=2026-02");
        $monthlyResponse->assertStatus(200);

        // 詳細リンクがある
        $monthlyResponse->assertSee("/admin/attendance/{$attendance->id}");

        // 詳細ページを開いてチェック
        $detailResponse = $this->get("/admin/attendance/{$attendance->id}");
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('勤怠詳細');
        $detailResponse->assertSee('詳細遷移テスト');
    }
}
