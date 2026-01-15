@extends('layouts.app')

@section('title')
    管理者ログイン
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/auth/login.css') }}">
@endsection

@section('content')
    <div class="auth">
        <h1 class="auth__title">管理者ログイン</h1>

        <form action="{{ url('/admin/login') }}" method="POST" class="auth__form">
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

            <button type="submit" class="auth__button">管理者ログインする</button>
        </form>
    </div>
@endsection
