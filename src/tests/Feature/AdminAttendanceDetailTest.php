<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 1, 10, 9, 0, 0));
    }

    private function createAdminAccount()
    {
        // admin guard があるかどうかで切り替え（環境差の保険）
        $adminGuardConfig = config('auth.guards.admin');
        if ($adminGuardConfig) {
            $providerName = $adminGuardConfig['provider'] ?? null;
            $adminModelClass = $providerName ? config("auth.providers.$providerName.model") : null;

            if ($adminModelClass && class_exists($adminModelClass) && method_exists($adminModelClass, 'factory')) {
                return $adminModelClass::factory()->create();
            }
        }

        // admin guard が無い/モデルが特定できない場合は User で代用
        return User::factory()->create();
    }

    private function actingAsAdmin($adminAccount)
    {
        $guardName = config('auth.guards.admin') ? 'admin' : null;

        return $guardName
            ? $this->actingAs($adminAccount, $guardName)
            : $this->actingAs($adminAccount);
    }

    private function createAttendanceWithBreaksAndNote(User $targetUser): Attendance
    {
        $attendance = Attendance::create([
            'user_id' => $targetUser->id,
            'work_date' => '2026-01-10',
            'clock_in_at' => Carbon::parse('2026-01-10 09:00:00'),
            'clock_out_at' => Carbon::parse('2026-01-10 18:00:00'),
            'status' => 3,
            'note' => 'メモです',
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in_at' => Carbon::parse('2026-01-10 12:00:00'),
            'break_out_at' => Carbon::parse('2026-01-10 12:30:00'),
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in_at' => Carbon::parse('2026-01-10 15:00:00'),
            'break_out_at' => Carbon::parse('2026-01-10 15:15:00'),
        ]);

        return $attendance;
    }

    // テスト内容 1：勤怠詳細画面に表示されるデータが選択したものになっている
    /** @test */
    public function admin_can_see_selected_attendance_data_on_detail_page()
    {
        $adminAccount = $this->createAdminAccount();
        $targetUser = User::factory()->create(['name' => '山田太郎']);
        $attendance = $this->createAttendanceWithBreaksAndNote($targetUser);

        $response = $this->actingAsAdmin($adminAccount)
            ->get(route('admin.attendance.show', ['id' => $attendance->id]));

        $response->assertStatus(200);

        // 表示内容が一致していること
        $response->assertSee('山田太郎');
        $response->assertSee('2026年');
        $response->assertSee('1月10日');

        // input の value（HTMLそのまま）
        $response->assertSee('name="clock_in"', false);
        $response->assertSee('value="09:00"', false);

        $response->assertSee('name="clock_out"', false);
        $response->assertSee('value="18:00"', false);

        // 休憩1
        $response->assertSee('name="breaks[0][in]"', false);
        $response->assertSee('value="12:00"', false);
        $response->assertSee('name="breaks[0][out]"', false);
        $response->assertSee('value="12:30"', false);

        // 休憩2
        $response->assertSee('name="breaks[1][in]"', false);
        $response->assertSee('value="15:00"', false);
        $response->assertSee('name="breaks[1][out]"', false);
        $response->assertSee('value="15:15"', false);

        // 備考
        $response->assertSee('メモです');
    }

    // テスト内容 2：出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
    /** @test */
    public function validation_error_is_shown_when_clock_in_is_after_clock_out()
    {
        $adminAccount = $this->createAdminAccount();
        $targetUser = User::factory()->create();
        $attendance = $this->createAttendanceWithBreaksAndNote($targetUser);

        $response = $this->actingAsAdmin($adminAccount)->post(
            route('admin.attendance.update', ['id' => $attendance->id]),
            [
                'clock_in' => '19:00',
                'clock_out' => '18:00',
                'breaks' => [],
                'note' => '備考あり',
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    // テスト内容 3：休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
    /** @test */
    public function validation_error_is_shown_when_break_in_is_after_clock_out()
    {
        $adminAccount = $this->createAdminAccount();
        $targetUser = User::factory()->create();
        $attendance = $this->createAttendanceWithBreaksAndNote($targetUser);

        $response = $this->actingAsAdmin($adminAccount)->post(
            route('admin.attendance.update', ['id' => $attendance->id]),
            [
                'clock_in' => '09:00',
                'clock_out' => '18:00',
                'breaks' => [
                    ['in' => '19:00', 'out' => '19:10'],
                ],
                'note' => '備考あり',
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'breaks.0.in' => '休憩時間が不適切な値です',
        ]);
    }

    // テスト内容 4：休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
    /** @test */
    public function validation_error_is_shown_when_break_out_is_after_clock_out()
    {
        $adminAccount = $this->createAdminAccount();
        $targetUser = User::factory()->create();
        $attendance = $this->createAttendanceWithBreaksAndNote($targetUser);

        $response = $this->actingAsAdmin($adminAccount)->post(
            route('admin.attendance.update', ['id' => $attendance->id]),
            [
                'clock_in' => '09:00',
                'clock_out' => '18:00',
                'breaks' => [
                    ['in' => '17:50', 'out' => '19:10'],
                ],
                'note' => '備考あり',
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'breaks.0.out' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    // テスト内容 5：備考欄が未入力の場合のエラーメッセージが表示される
    /** @test */
    public function validation_error_is_shown_when_note_is_empty()
    {
        $adminAccount = $this->createAdminAccount();
        $targetUser = User::factory()->create();
        $attendance = $this->createAttendanceWithBreaksAndNote($targetUser);

        $response = $this->actingAsAdmin($adminAccount)->post(
            route('admin.attendance.update', ['id' => $attendance->id]),
            [
                'clock_in' => '09:00',
                'clock_out' => '18:00',
                'breaks' => [],
                'note' => '',
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'note' => '備考を記入してください',
        ]);
    }
}
