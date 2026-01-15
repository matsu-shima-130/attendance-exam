<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateUserOrAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // 管理者ログインならOK
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        // 一般ログインなら、メール認証済みならOK
        if (Auth::check()) {
            if (! $request->user()->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }
            return $next($request);
        }

        // どっちも未ログイン
        return redirect()->route('login');
    }
}
