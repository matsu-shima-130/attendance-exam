<?php

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\StampCorrectionRequestController;

Route::get('/', function () {
    return redirect('login');
});

// 一般ユーザー（ゲストのみ）
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// 一般ユーザー（ログイン必須）
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // 認証誘導画面
    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');

    // 認証メール再送
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    })->name('verification.send');

    // 認証リンクを踏んだとき
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return redirect('/attendance');
    })->middleware(['signed'])->name('verification.verify');
});

// PG12のミドルウェアで区別用
Route::middleware('user_or_admin_verified')->group(function () {
    Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'list'])
        ->name('stamp_correction_request.list');
});



Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');

    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'detail'])->name('attendance.detail');
    Route::post('/attendance/detail/{id}', [AttendanceController::class, 'requestCorrection'])
        ->name('attendance.detail.request');
});

Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    // 管理者（ログイン必須）
    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

        // 勤怠一覧（管理者）
        Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])
            ->name('admin.attendance.index');

        // （次で作る予定）勤怠詳細（管理者）
        Route::get('/attendance/{id}', [AdminAttendanceController::class, 'show'])
            ->name('admin.attendance.show');

        // 勤怠修正（管理者）
        Route::post('/attendance/{id}', [AdminAttendanceController::class, 'update'])
            ->name('admin.attendance.update');

        // 修正申請承認画面（管理者）※詳細ボタンの遷移先
        Route::get('/stamp_correction_request/approve/{attendanceCorrectionRequest}', [StampCorrectionRequestController::class, 'approve'])
            ->name('admin.stamp_correction_request.approve');

        // 承認ボタン押下（管理者）
        Route::post('/stamp_correction_request/approve/{attendanceCorrectionRequest}', [StampCorrectionRequestController::class, 'approveUpdate'])
            ->name('admin.stamp_correction_request.approve.update');

        // スタッフ一覧（管理者）
        Route::get('/staff/list', [AdminStaffController::class, 'index'])
            ->name('admin.staff.index');

        // スタッフ別勤怠一覧（月次）（管理者）
        Route::get('/attendance/staff/{id}', [AdminAttendanceController::class, 'staff'])
            ->name('admin.attendance.staff');

        // CSV出力（管理者：スタッフ別勤怠）
        Route::get('/attendance/staff/{id}/export', [AdminAttendanceController::class, 'exportStaffMonthlyCsv'])
            ->name('admin.attendance.staff.export');

    });
});