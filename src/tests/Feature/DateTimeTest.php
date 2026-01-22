<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateTimeTest extends TestCase
{
    use RefreshDatabase;

    // テスト内容 1：勤怠画面に「今日の日付」と「現在時刻」が表示される
    /** @test */
    public function attendance_screen_shows_current_date_and_time()
    {
        // 1. 「今」を固定する（テスト中に時刻がズレないようにする）
        Carbon::setTestNow(Carbon::parse('2026-01-18 09:07:00'));

        // 2. 認証済みユーザーでログイン（/attendance は verified が必要）
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 3. 勤怠画面へアクセス
        $response = $this->get('/attendance');

        // 4. 画面が表示できること
        $response->assertStatus(200);

        // 5. 日付表示（YYYY年M月D日(ddd)）
        $response->assertSee('2026年1月18日(日)');

        // 6. 時刻表示（H:i）
        $response->assertSee('09:07');

        // 7. 固定を解除（他のテストに影響させない）
        Carbon::setTestNow();
    }
}
