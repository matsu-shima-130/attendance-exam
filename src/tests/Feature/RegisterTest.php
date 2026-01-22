<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    // テスト内容 1：名前が未入力の場合、バリデーションメッセージが表示される
    /** @test */
    public function name_is_required()
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);
    }

    // テスト内容 2：メールアドレスが未入力の場合、バリデーションメッセージが表示される
    /** @test */
    public function email_is_required()
    {
        $response = $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    // テスト内容 3：パスワードが未入力の場合、バリデーションメッセージが表示される
    /** @test */
    public function password_is_required()
    {
        $response = $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    // テスト内容 4：パスワードが8文字未満の場合、バリデーションメッセージが表示される
    /** @test */
    public function password_must_be_at_least_8_characters()
    {
        $response = $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
    }


    // テスト内容 5：パスワードが一致しない場合、バリデーションメッセージが表示される
    /** @test */
    public function password_confirmation_must_match()
    {
        $response = $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password456',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
        ]);
    }

    // テスト内容 6：フォームに内容が入力されていた場合、データが正常に保存される
    /** @test */
    public function user_can_register_and_is_saved_in_database()
    {
        $response = $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 登録後はメール認証誘導画面へ
        $response->assertRedirect('/email/verify');

        $this->assertDatabaseHas('users', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
        ]);

    }
}
