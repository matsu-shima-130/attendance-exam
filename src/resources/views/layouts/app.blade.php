<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', '勤怠管理')</title>

        <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
        <link rel="stylesheet" href="{{ asset('css/common.css') }}">

        @yield('css')
    </head>

    <body>
        <header class="header">
            <div class="header__inner">
                <div class="header__logo">
                    <a href="{{ url('/') }}">
                        <img src="{{ asset('images/logo.png') }}" alt="COACHTECH">
                    </a>
                </div>

                {{-- ログイン後だけ右側メニュー表示（一般ユーザー or 管理者） --}}
                @php
                    // 「この画面では右メニューを絶対出さない」一覧
                    $hideNav = request()->is('login')
                        || request()->is('register')
                        || request()->is('email/verify')
                        || request()->is('admin/login');
                @endphp

                @if(!$hideNav)
                    @if(\Illuminate\Support\Facades\Auth::guard('admin')->check())
                        {{-- 管理者ログイン後ヘッダー --}}
                        <nav class="header__nav">
                            <a href="{{ url('/admin/attendance/list') }}" class="header__link">勤怠一覧</a>
                            <a href="{{ url('/admin/staff/list') }}" class="header__link">スタッフ一覧</a>
                            <a href="{{ url('/stamp_correction_request/list') }}" class="header__link">申請一覧</a>

                            <form action="{{ url('/admin/logout') }}" method="POST" class="header__logout">
                                @csrf
                                <button type="submit" class="header__button">ログアウト</button>
                            </form>
                        </nav>

                    @elseif(\Illuminate\Support\Facades\Auth::check())
                        {{-- 一般ユーザーログイン後ヘッダー --}}
                        <nav class="header__nav">
                            <a href="{{ url('/attendance') }}" class="header__link">勤怠</a>
                            <a href="{{ url('/attendance/list') }}" class="header__link">勤怠一覧</a>
                            <a href="{{ url('/stamp_correction_request/list') }}" class="header__link">申請</a>

                            <form action="{{ url('/logout') }}" method="POST" class="header__logout">
                                @csrf
                                <button type="submit" class="header__button">ログアウト</button>
                            </form>
                        </nav>
                    @endif
                @endif
            </div>
        </header>

        <main class="main">
            @yield('content')
        </main>
    </body>
</html>
