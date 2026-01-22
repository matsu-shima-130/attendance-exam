@extends('layouts.app')

@section('title')
    会員登録
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/auth/register.css') }}">
@endsection

@section('content')
    <div class="auth">
        <h1 class="auth__title">会員登録</h1>

        <form action="{{ url('/register') }}" method="POST" class="auth__form" novalidate>
            @csrf

            <div class="auth__group">
                <label class="auth__label">名前</label>
                <input type="text" name="name" value="{{ old('name') }}" class="auth__input">
                @error('name') <p class="auth__error">{{ $message }}</p> @enderror
            </div>

            <div class="auth__group">
                <label class="auth__label">メールアドレス</label>
                <input type="email" name="email" value="{{ old('email') }}" class="auth__input">
                @error('email') <p class="auth__error">{{ $message }}</p> @enderror
            </div>

            <div class="auth__group">
                <label class="auth__label">パスワード</label>
                <input type="password" name="password" class="auth__input">
                @error('password') <p class="auth__error">{{ $message }}</p> @enderror
            </div>

            <div class="auth__group">
                <label class="auth__label">パスワード確認</label>
                <input type="password" name="password_confirmation" class="auth__input">
            </div>

            <button type="submit" class="auth__button">登録する</button>

            <div class="auth__link">
                <a href="{{ url('/login') }}">ログインはこちら</a>
            </div>
        </form>
    </div>
@endsection
