<?php

namespace App\Http\Controllers;

use App\Models\User;

class AdminStaffController extends Controller
{
    // スタッフ一覧（管理者）
    public function index()
    {
        $users = User::query()
            ->select(['id', 'name', 'email'])
            ->orderBy('id', 'asc')
            ->get();

        $rows = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'monthly_url' => route('admin.attendance.staff', ['id' => $user->id]),
            ];
        });

        return view('admin.staff.index', [
            'rows' => $rows,
        ]);
    }
}
