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
                                        value="{{ old("breaks.$i.in", $break['in']) }}"
                                        {{ $isApproved ? 'readonly' : '' }}>
                                    <span class="time-tilde">～</span>
                                    <input class="time-input" type="text"
                                        name="breaks[{{ $i }}][out]"
                                        value="{{ old("breaks.$i.out", $break['out']) }}"
                                        {{ $isApproved ? 'readonly' : '' }}>
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
                            <textarea class="note-textarea" name="requested_note" {{ $isApproved ? 'readonly' : '' }}>{{ old('requested_note', $note) }}</textarea>

                            @error('requested_note')
                                <p class="detail-message">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="detail-actions">
                    @if ($isApproved)
                        <button class="detail-button detail-button--approved" type="button" disabled>承認済み</button>
                    @else
                        <button class="detail-button" type="submit">承認</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
@endsection
