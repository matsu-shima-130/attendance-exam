@extends('layouts.app')

@section('title')
    ログイン
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/auth/login.css') }}">
@endsection

@section('content')
    <div class="auth">
        <h1 class="auth__title">ログイン</h1>

        <form action="{{ url('/login') }}" method="POST" class="auth__form" novalidate>
            @csrf

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

            <button type="submit" class="auth__button">ログインする</button>

            <div class="auth__link">
                <a href="{{ url('/register') }}">会員登録はこちら</a>
            </div>
        </form>
    </div>
@endsection
