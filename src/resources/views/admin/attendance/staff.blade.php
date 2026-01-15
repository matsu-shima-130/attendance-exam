@extends('layouts.app')

@section('title', 'スタッフ別勤怠一覧')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin/attendance/staff.css') }}">
@endsection

@section('content')
    <div class="attendance-list">
        <div class="attendance-list__inner">
            <h1 class="attendance-list__title">
                <span class="attendance-list__title-bar"></span>
                {{ $userName }}さんの勤怠
            </h1>

            <div class="attendance-list__month">
                <a class="month-nav"
                    href="{{ route('admin.attendance.staff', ['id' => $userId, 'month' => $prevMonth]) }}">←前月</a>

                <div class="month-center">
                    <img class="month-center__icon" src="{{ asset('images/icon_calender.png') }}" alt="">
                    <span class="month-center__text">{{ $monthText }}</span>
                </div>

                <a class="month-nav month-nav--right"
                    href="{{ route('admin.attendance.staff', ['id' => $userId, 'month' => $nextMonth]) }}">翌月→</a>
            </div>

            <div class="table-wrap">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>日付</th>
                            <th>出勤</th>
                            <th>退勤</th>
                            <th>休憩</th>
                            <th>合計</th>
                            <th>詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['clock_in'] }}</td>
                                <td>{{ $row['clock_out'] }}</td>
                                <td>{{ $row['break_total'] }}</td>
                                <td>{{ $row['work_total'] }}</td>
                                <td>
                                    @if ($row['detail_id'])
                                        <a class="detail-link"
                                            href="{{ route('admin.attendance.show', ['id' => $row['detail_id']]) }}">詳細</a>
                                    @else
                                        <span class="detail-link">詳細</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="csv-wrap">
                <a class="csv-button"
                    href="{{ route('admin.attendance.staff.export', ['id' => $userId, 'month' => $monthParam]) }}">
                    CSV出力
                </a>
            </div>

        </div>
    </div>
@endsection
