<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceCorrectionRequestBreak;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStampCorrectionRequestTest extends TestCase
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

    private function createAttendanceForUser(User $user, string $workDate): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'work_date' => $workDate,
            'clock_in_at' => Carbon::parse($workDate . ' 09:00'),
            'clock_out_at' => Carbon::parse($workDate . ' 18:00'),
            'status' => 3,
            'note' => '元の備考',
        ]);
    }

    private function addBreakToAttendance(Attendance $attendance, string $workDate, string $breakIn, string $breakOut): BreakTime
    {
        return BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in_at' => Carbon::parse($workDate . ' ' . $breakIn),
            'break_out_at' => Carbon::parse($workDate . ' ' . $breakOut),
        ]);
    }

    /**
     * @param array<int, array{break_no:int, in:string|null, out:string|null}> $requestedBreaks
     */
    private function createCorrectionRequest(
        User $user,
        Attendance $attendance,
        int $status,
        ?string $requestedClockIn,
        ?string $requestedClockOut,
        ?string $requestedNote,
        array $requestedBreaks = []
    ): AttendanceCorrectionRequest {
        $workDate = Carbon::parse($attendance->work_date)->format('Y-m-d');

        $correctionRequest = AttendanceCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'requested_clock_in_at' => $requestedClockIn ? Carbon::parse($workDate . ' ' . $requestedClockIn) : null,
            'requested_clock_out_at' => $requestedClockOut ? Carbon::parse($workDate . ' ' . $requestedClockOut) : null,
            'requested_note' => $requestedNote,
            'status' => $status, // 0:承認待ち / 1:承認済み
            'approved_by' => null,
            'approved_at' => null,
        ]);

        foreach ($requestedBreaks as $requestedBreak) {
            AttendanceCorrectionRequestBreak::create([
                'attendance_correction_request_id' => $correctionRequest->id,
                'break_no' => $requestedBreak['break_no'],
                'requested_break_in_at' => $requestedBreak['in'] ? Carbon::parse($workDate . ' ' . $requestedBreak['in']) : null,
                'requested_break_out_at' => $requestedBreak['out'] ? Carbon::parse($workDate . ' ' . $requestedBreak['out']) : null,
            ]);
        }

        return $correctionRequest;
    }

    // テスト内容 1：承認待ちの修正申請が全て表示されている
    /** @test */
    public function pending_requests_are_listed_on_pending_tab_for_admin()
    {
        $this->actingAsAdmin();

        $userA = User::factory()->create(['name' => '田中 太郎']);
        $userB = User::factory()->create(['name' => '佐藤 花子']);

        $attendanceA = $this->createAttendanceForUser($userA, '2026-02-10');
        $attendanceB = $this->createAttendanceForUser($userB, '2026-02-11');

        $pendingRequestA = $this->createCorrectionRequest(
            user: $userA,
            attendance: $attendanceA,
            status: 0,
            requestedClockIn: '10:00',
            requestedClockOut: '19:00',
            requestedNote: '申請理由A'
        );

        $pendingRequestB = $this->createCorrectionRequest(
            user: $userB,
            attendance: $attendanceB,
            status: 0,
            requestedClockIn: null,
            requestedClockOut: null,
            requestedNote: '申請理由B'
        );

        // 承認済みも混ぜておいて「pendingでは出ない」を確認
        $approvedUser = User::factory()->create(['name' => '承認済み 太郎']);
        $approvedAttendance = $this->createAttendanceForUser($approvedUser, '2026-02-12');
        $this->createCorrectionRequest(
            user: $approvedUser,
            attendance: $approvedAttendance,
            status: 1,
            requestedClockIn: '11:00',
            requestedClockOut: '20:00',
            requestedNote: '承認済み理由'
        );

        $response = $this->get(route('stamp_correction_request.list', ['tab' => 'pending']));
        $response->assertStatus(200);

        // pending2件が見える
        $response->assertSee('田中 太郎');
        $response->assertSee('2026/02/10');
        $response->assertSee('申請理由A');
        $response->assertSee(route('admin.stamp_correction_request.approve', [
            'attendanceCorrectionRequest' => $pendingRequestA->id,
        ]));

        $response->assertSee('佐藤 花子');
        $response->assertSee('2026/02/11');
        $response->assertSee('申請理由B');
        $response->assertSee(route('admin.stamp_correction_request.approve', [
            'attendanceCorrectionRequest' => $pendingRequestB->id,
        ]));

        // approvedは見えない
        $response->assertDontSee('承認済み 太郎');
        $response->assertDontSee('承認済み理由');
    }

    // テスト内容 2：承認済みの修正申請が全て表示されている
    /** @test */
    public function approved_requests_are_listed_on_approved_tab_for_admin()
    {
        $this->actingAsAdmin();

        $user = User::factory()->create(['name' => '承認済み 花子']);
        $attendance = $this->createAttendanceForUser($user, '2026-02-10');

        $approvedRequest = $this->createCorrectionRequest(
            user: $user,
            attendance: $attendance,
            status: 1,
            requestedClockIn: '10:00',
            requestedClockOut: '19:00',
            requestedNote: '承認済み理由'
        );

        // pendingも混ぜておいて「approvedでは出ない」を確認
        $pendingUser = User::factory()->create(['name' => '承認待ち 太郎']);
        $pendingAttendance = $this->createAttendanceForUser($pendingUser, '2026-02-11');
        $this->createCorrectionRequest(
            user: $pendingUser,
            attendance: $pendingAttendance,
            status: 0,
            requestedClockIn: null,
            requestedClockOut: null,
            requestedNote: '承認待ち理由'
        );

        $response = $this->get(route('stamp_correction_request.list', ['tab' => 'approved']));
        $response->assertStatus(200);

        $response->assertSee('承認済み 花子');
        $response->assertSee('2026/02/10');
        $response->assertSee('承認済み理由');
        $response->assertSee(route('admin.stamp_correction_request.approve', [
            'attendanceCorrectionRequest' => $approvedRequest->id,
        ]));

        $response->assertDontSee('承認待ち 太郎');
        $response->assertDontSee('承認待ち理由');
    }

    // テスト内容 3：修正申請の詳細内容が正しく表示されている
    /** @test */
    public function correction_request_detail_displays_correct_information()
    {
        $this->actingAsAdmin();

        $user = User::factory()->create(['name' => '詳細表示 太郎']);
        $attendance = $this->createAttendanceForUser($user, '2026-02-10');

        // 元の休憩（base）
        $this->addBreakToAttendance($attendance, '2026-02-10', '12:00', '12:30');

        // 申請：休憩1を上書き、休憩2を追加
        $correctionRequest = $this->createCorrectionRequest(
            user: $user,
            attendance: $attendance,
            status: 0,
            requestedClockIn: '10:00',
            requestedClockOut: '19:00',
            requestedNote: '申請理由（詳細）',
            requestedBreaks: [
                ['break_no' => 1, 'in' => '12:10', 'out' => '12:40'],
                ['break_no' => 2, 'in' => '15:00', 'out' => '15:10'],
            ]
        );

        $response = $this->get(route('admin.stamp_correction_request.approve', [
            'attendanceCorrectionRequest' => $correctionRequest->id,
        ]));
        $response->assertStatus(200);

        $response->assertSee('勤怠詳細');
        $response->assertSee('詳細表示 太郎');

        // 日付表示（テンプレ側が「YYYY年」「n月j日」なのでざっくり確認）
        $response->assertSee('2026年');
        $response->assertSee('2月10日');

        // 申請の出勤・退勤が表示されている（input value）
        $response->assertSee('name="requested_clock_in_at"', false);
        $response->assertSee('value="10:00"', false);
        $response->assertSee('name="requested_clock_out_at"', false);
        $response->assertSee('value="19:00"', false);

        // 申請の休憩が表示されている（breaks[0], breaks[1]）
        $response->assertSee('name="breaks[0][in]"', false);
        $response->assertSee('value="12:10"', false);
        $response->assertSee('name="breaks[0][out]"', false);
        $response->assertSee('value="12:40"', false);

        $response->assertSee('name="breaks[1][in]"', false);
        $response->assertSee('value="15:00"', false);
        $response->assertSee('name="breaks[1][out]"', false);
        $response->assertSee('value="15:10"', false);

        // 備考（textarea）
        $response->assertSee('申請理由（詳細）');
    }

    // テスト内容 4：修正申請の承認処理が正しく行われる（申請が承認され、勤怠情報が更新される）
    /** @test */
    public function approving_request_updates_attendance_and_marks_request_as_approved()
    {
        $adminUser = $this->actingAsAdmin();

        $user = User::factory()->create(['name' => '承認処理 太郎']);
        $attendance = $this->createAttendanceForUser($user, '2026-02-10');

        // 元の休憩（1件）
        $this->addBreakToAttendance($attendance, '2026-02-10', '12:00', '12:30');

        $correctionRequest = $this->createCorrectionRequest(
            user: $user,
            attendance: $attendance,
            status: 0,
            requestedClockIn: null,
            requestedClockOut: null,
            requestedNote: '申請理由（承認）'
        );

        $response = $this->post(route('admin.stamp_correction_request.approve.update', [
            'attendanceCorrectionRequest' => $correctionRequest->id,
        ]), [
            'requested_clock_in_at' => '10:00',
            'requested_clock_out_at' => '19:00',
            'requested_note' => '承認後の備考',
            'breaks' => [
                ['in' => '12:10', 'out' => '12:40'], // 既存1件を更新
                ['in' => '15:00', 'out' => '15:10'], // 新規追加
                ['in' => '', 'out' => ''],           // 追加1行想定（空はスキップされる）
            ],
        ]);

        $response->assertRedirect(route('admin.stamp_correction_request.approve', [
            'attendanceCorrectionRequest' => $correctionRequest->id,
        ]));

        // 勤怠が更新されている
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_at' => Carbon::parse('2026-02-10 10:00')->toDateTimeString(),
            'clock_out_at' => Carbon::parse('2026-02-10 19:00')->toDateTimeString(),
            'note' => '承認後の備考',
        ]);

        // 休憩（1件目が更新・2件目が追加）
        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_in_at' => Carbon::parse('2026-02-10 12:10')->toDateTimeString(),
            'break_out_at' => Carbon::parse('2026-02-10 12:40')->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_in_at' => Carbon::parse('2026-02-10 15:00')->toDateTimeString(),
            'break_out_at' => Carbon::parse('2026-02-10 15:10')->toDateTimeString(),
        ]);

        // 申請が承認済みになっている
        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $correctionRequest->id,
            'status' => 1,
            'approved_by' => $adminUser->id,
        ]);

        $approvedRequest = AttendanceCorrectionRequest::findOrFail($correctionRequest->id);
        $this->assertNotNull($approvedRequest->approved_at);
    }
}
