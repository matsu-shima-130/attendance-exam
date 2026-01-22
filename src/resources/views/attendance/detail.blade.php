@extends('layouts.app')

@section('title', '勤怠詳細')

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

            @if ($isPending)
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
                                <input class="time-input" type="text" value="{{ $clockIn }}" readonly>
                                <span class="time-tilde">～</span>
                                <input class="time-input" type="text" value="{{ $clockOut }}" readonly>
                            </div>
                        </div>
                    </div>

                    @foreach ($breaks as $i => $break)
                        <div class="detail-row">
                            <div class="detail-label">休憩{{ $i === 0 ? '' : $i + 1 }}</div>
                            <div class="detail-value">
                                <div class="time-pair">
                                    <input class="time-input" type="text" value="{{ $break['in'] }}" readonly>
                                    <span class="time-tilde">～</span>
                                    <input class="time-input" type="text" value="{{ $break['out'] }}" readonly>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="detail-row detail-row--note">
                        <div class="detail-label">備考</div>
                        <div class="detail-value">
                            <textarea class="note-textarea" readonly>{{ $note }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="detail-actions">
                    <p class="detail-message">※承認待ちのため修正はできません。</p>
                </div>
            @else
                <form method="POST" action="{{ route('attendance.detail.request', ['id' => $attendanceId]) }}">
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
                                    <input class="time-input" type="text" name="requested_clock_in_at"
                                        value="{{ old('requested_clock_in_at', $clockIn) }}">
                                    <span class="time-tilde">～</span>
                                    <input class="time-input" type="text" name="requested_clock_out_at"
                                        value="{{ old('requested_clock_out_at', $clockOut) }}">
                                </div>

                                @error('requested_clock_in_at')
                                    <p class="detail-message">{{ $message }}</p>
                                @enderror
                                @error('requested_clock_out_at')
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
                                <textarea class="note-textarea" name="requested_note">{{ old('requested_note', $note) }}</textarea>

                                @error('requested_note')
                                    <p class="detail-message">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="detail-actions">
                        <button class="detail-button" type="submit">修正</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
