@extends('layouts.app')

@section('title', '勤怠')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance/index.css') }}">
@endsection

@section('content')
    <div class="attendance">
        <div class="attendance__inner">
            <p class="attendance__badge">{{ $statusLabel }}</p>

            <p class="attendance__date">{{ $dateText }}</p>
            <p class="attendance__time">{{ $timeText }}</p>

            @if ($status === 'before')
                {{-- ① 出勤前 --}}
                <div class="attendance__actions">
                    <form method="POST" action="{{ route('attendance.store') }}">
                        @csrf
                        <input type="hidden" name="action" value="clock_in">
                        <button type="submit" class="btn btn--primary">出勤</button>
                    </form>
                </div>

            @elseif ($status === 'working')
                {{-- ② 出勤中 --}}
                <div class="attendance__actions attendance__actions--two">
                    <form method="POST" action="{{ route('attendance.store') }}">
                        @csrf
                        <input type="hidden" name="action" value="clock_out">
                        <button type="submit" class="btn btn--primary">退勤</button>
                    </form>

                    <form method="POST" action="{{ route('attendance.store') }}">
                        @csrf
                        <input type="hidden" name="action" value="break_in">
                        <button type="submit" class="btn btn--secondary">休憩入</button>
                    </form>
                </div>

            @elseif ($status === 'breaking')
                {{-- ③ 休憩中 --}}
                <div class="attendance__actions">
                    <form method="POST" action="{{ route('attendance.store') }}">
                        @csrf
                        <input type="hidden" name="action" value="break_out">
                        <button type="submit" class="btn btn--secondary">休憩戻</button>
                    </form>
                </div>

            @elseif ($status === 'after')
                {{-- ④ 退勤後 --}}
                <p class="attendance__done">お疲れ様でした。</p>
            @endif
        </div>
    </div>
@endsection
