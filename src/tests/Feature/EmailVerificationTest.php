<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト内の「今」を固定
        Carbon::setTestNow(Carbon::create(2026, 2, 15, 9, 0, 0));
    }

    // テスト内容 1：会員登録後、認証メールが送信される（再送ボタン押下で送信される）
    /** @test */
    public function verification_email_can_be_sent_after_registration()
    {
        Notification::fake();

        // 1. 会員登録
        $registerResponse = $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // registerの遷移先は実装により変わるので、ひとまず302でOKにしておく
        $registerResponse->assertStatus(302);

        // 登録ユーザーを取得
        $registeredUser = User::where('email', 'test@example.com')->firstOrFail();

        // 2. ログイン状態にする（登録時にログインしていない実装でも通るように）
        $this->actingAs($registeredUser);

        // 3. 認証メールを送信（再送）
        $sendResponse = $this->post('/email/verification-notification');
        $sendResponse->assertStatus(302);
        $sendResponse->assertSessionHas('status', 'verification-link-sent');

        // 4. VerifyEmail が送信されている
        Notification::assertSentTo($registeredUser, VerifyEmail::class);
    }

    // テスト内容 2：メール認証誘導画面で「認証はこちらから」ボタンを押下するとメール認証サイトに遷移する（リンクが正しい）
    /** @test */
    public function verify_email_screen_has_link_to_mailhog()
    {
        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($unverifiedUser)->get('/email/verify');

        $response->assertStatus(200);

        // ボタン文言
        $response->assertSee('認証はこちらから');

        // リンク先（MailHog）
        $response->assertSee('http://localhost:8025');
    }

    // テスト内容 3：メール認証サイトのメール認証を完了すると、勤怠登録画面に遷移する
    /** @test */
    public function user_is_redirected_to_attendance_after_email_verification()
    {
        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // signed ミドルウェアを通すため、署名付きURLを発行
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $unverifiedUser->id,
                'hash' => sha1($unverifiedUser->email),
            ]
        );

        // 認証リンクを踏む（ログイン状態で）
        $response = $this->actingAs($unverifiedUser)->get($verificationUrl);

        // 認証完了後は /attendance にリダイレクトする実装になっている
        $response->assertRedirect('/attendance');

        // email_verified_at が入っていること
        $this->assertNotNull($unverifiedUser->fresh()->email_verified_at);

        // 念のため、勤怠登録画面にアクセスできること（verifiedミドルウェア）
        $attendanceResponse = $this->actingAs($unverifiedUser->fresh())->get('/attendance');
        $attendanceResponse->assertStatus(200);
    }
}
