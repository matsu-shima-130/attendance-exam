<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function create()
    {
        return view('admin.auth.login');
    }

    public function login(AdminLoginRequest $request)
    {
        $ok = Auth::guard('admin')->attempt(
            $request->only('email', 'password')
        );

        if (!$ok) {
            return back()
                ->withErrors(['email' => 'ログイン情報が登録されていません'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return redirect('/admin/attendance/list');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
