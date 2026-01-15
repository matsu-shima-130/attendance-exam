@extends('layouts.app')

@section('title', '修正申請承認')

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

            <form method="POST" action="{{ route('admin.stamp_correction_request.approve.update', ['attendanceCorrectionRequest' => $requestId]) }}">
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
                                    value="{{ old('requested_clock_in_at', $clockIn) }}" {{ $isApproved ? 'readonly' : '' }}>
                                <span class="time-tilde">～</span>
                                <input class="time-input" type="text" name="requested_clock_out_at"
                                    value="{{ old('requested_clock_out_at', $clockOut) }}" {{ $isApproved ? 'readonly' : '' }}>
                            </div>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">休憩</div>
                        <div class="detail-value">
                            <div class="time-pair">
                                <input class="time-input" type="text" name="break1_in"
                                    value="{{ old('break1_in', $breaks[0]['in']) }}" {{ $isApproved ? 'readonly' : '' }}>
                                <span class="time-tilde">～</span>
                                <input class="time-input" type="text" name="break1_out"
                                    value="{{ old('break1_out', $breaks[0]['out']) }}" {{ $isApproved ? 'readonly' : '' }}>
                            </div>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">休憩2</div>
                        <div class="detail-value">
                            <div class="time-pair">
                                <input class="time-input" type="text" name="break2_in"
                                    value="{{ old('break2_in', $breaks[1]['in']) }}" {{ $isApproved ? 'readonly' : '' }}>
                                <span class="time-tilde">～</span>
                                <input class="time-input" type="text" name="break2_out"
                                    value="{{ old('break2_out', $breaks[1]['out']) }}" {{ $isApproved ? 'readonly' : '' }}>
                            </div>
                        </div>
                    </div>

                    <div class="detail-row detail-row--note">
                        <div class="detail-label">備考</div>
                        <div class="detail-value">
                            <textarea class="note-textarea" name="requested_note" {{ $isApproved ? 'readonly' : '' }}>{{ old('requested_note', $note) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="detail-actions">
                    @if ($isApproved)
                        <button class="detail-button detail-button--approved" type="button" disabled>承認済み</button>
                    @else
                        <form method="POST"
                            action="{{ route('admin.stamp_correction_request.approve.update', ['attendanceCorrectionRequest' => $requestId]) }}">
                            @csrf

                            {{-- 承認時に「申請内容」をそのまま送る（approveUpdateのvalidate対策） --}}
                            <input type="hidden" name="requested_clock_in_at" value="{{ $clockIn }}">
                            <input type="hidden" name="requested_clock_out_at" value="{{ $clockOut }}">
                            <input type="hidden" name="break1_in" value="{{ $breaks[0]['in'] }}">
                            <input type="hidden" name="break1_out" value="{{ $breaks[0]['out'] }}">
                            <input type="hidden" name="break2_in" value="{{ $breaks[1]['in'] }}">
                            <input type="hidden" name="break2_out" value="{{ $breaks[1]['out'] }}">
                            <input type="hidden" name="requested_note" value="{{ $note }}">

                            <button class="detail-button" type="submit">承認</button>
                        </form>
                    @endif
                </div>
            </form>
        </div>
    </div>
@endsection
