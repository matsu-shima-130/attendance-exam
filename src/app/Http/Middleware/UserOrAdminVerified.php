<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserOrAdminVerified
{
    public function handle(Request $request, Closure $next)
    {
        // 管理者ログイン済みなら通す
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        // 一般ユーザーがログインしてなければログインへ
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // 一般ユーザーはメール認証済みだけ通す
        if (!$request->user() || !$request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
