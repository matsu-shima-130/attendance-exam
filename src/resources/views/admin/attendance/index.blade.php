@extends('layouts.app')

@section('title', '管理者：勤怠一覧')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin/attendance/index.css') }}">
@endsection

@section('content')
    <div class="admin-attendance">
        <div class="admin-attendance__inner">
            <h1 class="admin-attendance__title">
                <span class="admin-attendance__title-bar"></span>
                {{ $titleDateText }}の勤怠
            </h1>

            <div class="admin-attendance__date-card">
                <a class="admin-attendance__date-nav" href="{{ route('admin.attendance.index', ['date' => $prevDate]) }}">
                    ← 前日
                </a>

                <div class="admin-attendance__date-center">
                    <img class="admin-attendance__date-icon" src="{{ asset('images/icon_calender.png') }}" alt="カレンダー">
                    <span class="admin-attendance__date-text">{{ $displayDateText }}</span>
                </div>

                <a class="admin-attendance__date-nav admin-attendance__date-nav--right"
                    href="{{ route('admin.attendance.index', ['date' => $nextDate]) }}">
                    翌日 →
                </a>
            </div>

            <div class="admin-attendance__table-card">
                <table class="admin-attendance__table">
                    <thead>
                        <tr>
                            <th>名前</th>
                            <th>出勤</th>
                            <th>退勤</th>
                            <th>休憩</th>
                            <th>合計</th>
                            <th>詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['clock_in'] }}</td>
                                <td>{{ $row['clock_out'] }}</td>
                                <td>{{ $row['break_total'] }}</td>
                                <td>{{ $row['work_total'] }}</td>
                                <td>
                                    @if ($row['detail_url'])
                                        <a class="admin-attendance__detail-link" href="{{ $row['detail_url'] }}">詳細</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-attendance__empty" colspan="6">データがありません</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
