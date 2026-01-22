<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeVerifiedUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(), // verified middleware 対策
        ]);
    }

    private function makeAttendance(User $user, string $workDate): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'work_date' => $workDate,
            'clock_in_at' => $workDate . ' 09:00:00',
            'clock_out_at' => $workDate . ' 18:00:00',
            'status' => 1,
        ]);
    }

    // テスト内容 1：出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
    /** @test */
    public function clock_in_after_clock_out_shows_error_message()
    {
        Carbon::setTestNow('2026-01-19 10:00:00');

        $user = $this->makeVerifiedUser();
        $attendance = $this->makeAttendance($user, '2026-01-19');

        $response = $this->from("/attendance/detail/{$attendance->id}")
            ->actingAs($user)
            ->post("/attendance/detail/{$attendance->id}", [
                'requested_clock_in_at' => '18:00',
                'requested_clock_out_at' => '09:00',
                'breaks' => [],
                'requested_note' => 'テスト備考',
            ]);

        $response->assertRedirect("/attendance/detail/{$attendance->id}");
        $response->assertSessionHasErrors([
            // after_or_equal は out 側のキーにエラーが乗る
            'requested_clock_out_at' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    // テスト内容 2：休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
    /** @test */
    public function break_start_after_clock_out_shows_error_message()
    {
        Carbon::setTestNow('2026-01-19 10:00:00');

        $user = $this->makeVerifiedUser();
        $attendance = $this->makeAttendance($user, '2026-01-19');

        $response = $this->from("/attendance/detail/{$attendance->id}")
            ->actingAs($user)
            ->post("/attendance/detail/{$attendance->id}", [
                'requested_clock_in_at' => '09:00',
                'requested_clock_out_at' => '18:00',
                'breaks' => [
                    ['in' => '19:00', 'out' => '19:00'],
                ],
                'requested_note' => 'テスト備考',
            ]);

        $response->assertRedirect("/attendance/detail/{$attendance->id}");
        $response->assertSessionHasErrors([
            'breaks.0.in' => '休憩時間が不適切な値です',
        ]);
    }

    // テスト内容 3：休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
    /** @test */
    public function break_end_after_clock_out_shows_error_message()
    {
        Carbon::setTestNow('2026-01-19 10:00:00');

        $user = $this->makeVerifiedUser();
        $attendance = $this->makeAttendance($user, '2026-01-19');

        $response = $this->from("/attendance/detail/{$attendance->id}")
            ->actingAs($user)
            ->post("/attendance/detail/{$attendance->id}", [
                'requested_clock_in_at' => '09:00',
                'requested_clock_out_at' => '18:00',
                'breaks' => [
                    ['in' => '17:00', 'out' => '19:00'],
                ],
                'requested_note' => 'テスト備考',
            ]);

        $response->assertRedirect("/attendance/detail/{$attendance->id}");
        $response->assertSessionHasErrors([
            'breaks.0.out' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    // テスト内容 4：備考欄が未入力の場合のエラーメッセージが表示される
    /** @test */
    public function note_is_required_shows_error_message()
    {
        Carbon::setTestNow('2026-01-19 10:00:00');

        $user = $this->makeVerifiedUser();
        $attendance = $this->makeAttendance($user, '2026-01-19');

        $response = $this->from("/attendance/detail/{$attendance->id}")
            ->actingAs($user)
            ->post("/attendance/detail/{$attendance->id}", [
                'requested_clock_in_at' => '09:00',
                'requested_clock_out_at' => '18:00',
                'breaks' => [],
                'requested_note' => '',
            ]);

        $response->assertRedirect("/attendance/detail/{$attendance->id}");
        $response->assertSessionHasErrors([
            'requested_note' => '備考を記入してください',
        ]);
    }

    // テスト内容 5：修正申請処理が実行される（DBに承認待ちが作成される）
    /** @test */
    public function correction_request_is_created()
    {
        Carbon::setTestNow('2026-01-19 10:00:00');

        $user = $this->makeVerifiedUser();
        $attendance = $this->makeAttendance($user, '2026-01-19');

        $response = $this->from("/attendance/detail/{$attendance->id}")
            ->actingAs($user)
            ->post("/attendance/detail/{$attendance->id}", [
                'requested_clock_in_at' => '09:00',
                'requested_clock_out_at' => '18:00',
                'breaks' => [
                    ['in' => '12:00', 'out' => '12:30'],
                ],
                'requested_note' => '修正理由テスト',
            ]);

        // 申請後は同じ詳細へ戻る仕様
        $response->assertRedirect("/attendance/detail/{$attendance->id}");

        // DBに「承認待ち(status=0)」が1件作られていること
        $this->assertDatabaseHas('attendance_correction_requests', [
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'status' => 0,
            'requested_note' => '修正理由テスト',
        ]);
    }

    // テスト内容 6：「承認待ち」にログインユーザーが行った申請が全て表示されていること
    /** @test */
    public function pending_tab_shows_all_my_requests()
    {
        Carbon::setTestNow('2026-01-19 10:00:00');

        $user = $this->makeVerifiedUser();

        // 勤怠を2日分作り、それぞれ申請を作る（＝承認待ちが2件）
        $attendance1 = $this->makeAttendance($user, '2026-01-18');
        $attendance2 = $this->makeAttendance($user, '2026-01-19');

        AttendanceCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance1->id,
            'requested_clock_in_at' => '2026-01-18 09:00:00',
            'requested_clock_out_at' => '2026-01-18 18:00:00',
            'requested_note' => '申請1',
            'status' => 0,
        ]);

        AttendanceCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance2->id,
            'requested_clock_in_at' => '2026-01-19 09:00:00',
            'requested_clock_out_at' => '2026-01-19 18:00:00',
            'requested_note' => '申請2',
            'status' => 0,
        ]);

        // 申請一覧（承認待ち）を開く
        $response = $this->actingAs($user)->get('/stamp_correction_request/list?tab=pending');

        $response->assertStatus(200);

        // 2件分の申請理由が表示されていること（本人の申請が全部見える想定）
        $response->assertSee('申請1');
        $response->assertSee('申請2');

        // 状態ラベルが「承認待ち」になっていること
        $response->assertSee('承認待ち');
    }

    // テスト内容 7：「承認済み」に管理者が承認した修正申請が全て表示されている
    /** @test */
    public function approved_tab_shows_all_approved_requests()
    {
        Carbon::setTestNow('2026-01-19 10:00:00');

        $user = $this->makeVerifiedUser();

        $attendance1 = $this->makeAttendance($user, '2026-01-18');
        $attendance2 = $this->makeAttendance($user, '2026-01-19');

        AttendanceCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance1->id,
            'requested_clock_in_at' => '2026-01-18 09:00:00',
            'requested_clock_out_at' => '2026-01-18 18:00:00',
            'requested_note' => '承認済み1',
            'status' => 1, // 承認済み
        ]);

        AttendanceCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance2->id,
            'requested_clock_in_at' => '2026-01-19 09:00:00',
            'requested_clock_out_at' => '2026-01-19 18:00:00',
            'requested_note' => '承認済み2',
            'status' => 1, // 承認済み
        ]);

        // 申請一覧（承認済み）を開く
        $response = $this->actingAs($user)->get('/stamp_correction_request/list?tab=approved');

        $response->assertStatus(200);

        // 2件分の申請理由が表示されていること
        $response->assertSee('承認済み1');
        $response->assertSee('承認済み2');

        // 状態ラベルが「承認済み」になっていること
        $response->assertSee('承認済み');
    }

    // テスト内容 8：各申請の「詳細」を押下すると勤怠詳細画面に遷移する
    /** @test */
    public function request_list_detail_link_goes_to_attendance_detail()
    {
        Carbon::setTestNow('2026-01-19 10:00:00');

        $user = $this->makeVerifiedUser();
        $attendance = $this->makeAttendance($user, '2026-01-19');

        AttendanceCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'requested_clock_in_at' => '2026-01-19 09:00:00',
            'requested_clock_out_at' => '2026-01-19 18:00:00',
            'requested_note' => '詳細リンクテスト',
            'status' => 0,
        ]);

        // 申請一覧を表示して「詳細」リンク(URL)が含まれていることを確認
        $response = $this->actingAs($user)->get('/stamp_correction_request/list?tab=pending');

        $response->assertStatus(200);

        // コントローラで detail_url は attendance.detail にしてるので、そのURLが表示される想定
        $response->assertSee(route('attendance.detail', ['id' => $attendance->id]));

        // 実際に勤怠詳細へGETして 200 になること（＝遷移先が存在する）
        $detailResponse = $this->actingAs($user)->get("/attendance/detail/{$attendance->id}");
        $detailResponse->assertStatus(200);
    }
}
