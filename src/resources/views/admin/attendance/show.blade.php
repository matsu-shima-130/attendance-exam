@extends('layouts.app')

@section('title', '勤怠詳細（管理者）')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance/detail.css') }}">
@endsection

@section('content')
    <div class="attendance-detail">
        <div class="attendance-detail__inner">
            <h1 class="attendance-detail__title">
                <span class="attendance-detail__title-bar"></span>
                勤怠詳細
            </h1>

            <form method="POST" action="{{ route('admin.attendance.update', ['id' => $attendanceId]) }}">
                @csrf

                <div class="detail-card">
                    <div class="detail-row">
                        <div class="detail-label">名前</div>
                        <div class="detail-value detail-value--center">{{ $userName }}</div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">日付</div>
                        <div class="detail-value detail-value--date">
                            <span class="detail-date__year">{{ $yearText }}</span>
                            <span class="detail-date__md">{{ $dateText }}</span>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">出勤・退勤</div>
                        <div class="detail-value">
                            <div class="time-pair">
                                <input class="time-input" type="text" name="clock_in" value="{{ old('clock_in', $clockIn) }}">
                                <span class="time-tilde">～</span>
                                <input class="time-input" type="text" name="clock_out" value="{{ old('clock_out', $clockOut) }}">
                            </div>

                            @error('clock_in')
                                <p class="detail-message">{{ $message }}</p>
                            @enderror
                            @error('clock_out')
                                <p class="detail-message">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @foreach ($breaks as $i => $break)
                        <div class="detail-row">
                            <div class="detail-label">休憩{{ $i === 0 ? '' : $i + 1 }}</div>
                            <div class="detail-value">
                                <div class="time-pair">
                                    <input class="time-input" type="text"
                                        name="breaks[{{ $i }}][in]"
                                        value="{{ old("breaks.$i.in", $break['in']) }}">
                                    <span class="time-tilde">～</span>
                                    <input class="time-input" type="text"
                                        name="breaks[{{ $i }}][out]"
                                        value="{{ old("breaks.$i.out", $break['out']) }}">
                                </div>

                                @error("breaks.$i.in")
                                    <p class="detail-message">{{ $message }}</p>
                                @enderror
                                @error("breaks.$i.out")
                                    <p class="detail-message">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endforeach

                    <div class="detail-row detail-row--note">
                        <div class="detail-label">備考</div>
                        <div class="detail-value">
                            <textarea class="note-textarea" name="note">{{ old('note', $note) }}</textarea>
                            @error('note')
                                <p class="detail-message">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="detail-actions">
                    @if (session('just_updated'))
                        <button class="detail-button detail-button--approved" type="button" disabled>修正済み</button>
                    @else
                        <button class="detail-button" type="submit">修正</button>
                    @endif
                </div>

            </form>
        </div>
    </div>
@endsection
