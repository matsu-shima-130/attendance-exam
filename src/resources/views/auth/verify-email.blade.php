@extends('layouts.app')

@section('title', 'メール認証')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/auth/verify-email.css') }}">
@endsection

@section('content')
<div class="verify">
    <div class="verify__inner">
        <p class="verify__message">
            登録していただいたメールアドレスに認証メールを送付しました。<br>
            メール認証を完了してください。
        </p>

        <a href="http://localhost:8025" target="_blank" rel="noopener" class="verify__button">
            認証はこちらから
        </a>

        <form method="POST" action="{{ route('verification.send') }}" class="verify__resend-form">
            @csrf
            <button type="submit" class="verify__resend">認証メールを再送する</button>
        </form>

        @if (session('status') === 'verification-link-sent')
            <p class="verify__status">認証メールを再送しました。</p>
        @endif
    </div>
</div>
@endsection
