<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    // テスト内容 1：メールアドレスが未入力の場合、バリデーションメッセージが表示される
    /** @test */
    public function email_is_required()
    {
        $response = $this->post('/login', [
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
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    // テスト内容 3：登録内容と一致しない場合、バリデーションメッセージが表示される
    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        // 1. まず正しいユーザーをDBに作っておく
        User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // 2. 間違ったメールアドレスでログインする
        $response = $this->from('/login')->post('/login', [
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);

        // 3. ログイン画面に戻って、指定のメッセージが出る
        $response->assertRedirect('/login');

        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }
}