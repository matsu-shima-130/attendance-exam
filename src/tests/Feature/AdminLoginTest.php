<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    // テスト内容 1：メールアドレスが未入力の場合、バリデーションメッセージが表示される
    /** @test */
    public function email_is_required()
    {
        $response = $this->post('/admin/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    // テスト内容 2：パスワードが未入力の場合、バリデーションメッセージが表示される
    /** @test */
    public function password_is_required()
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    // テスト内容 3：登録内容と一致しない場合、バリデーションメッセージが表示される
    /** @test */
    public function admin_login_fails_with_invalid_credentials()
    {
        // 1. まず正しい管理者をDBに作っておく
        Admin::create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        // 2. 間違ったメールアドレスでログインする
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);

        // 3. ログイン画面に戻って、指定のメッセージが出る
        $response->assertRedirect('/admin/login');

        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }
}
