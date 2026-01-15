@extends('layouts.app')

@section('title', '申請一覧（管理者）')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/stamp_correction_request/index.css') }}">
@endsection

@section('content')
    <div class="request-list">
        <div class="request-list__inner">
            <h1 class="request-list__title">
                <span class="request-list__title-bar"></span>
                申請一覧
            </h1>

            <div class="request-tabs">
                <a href="{{ route('stamp_correction_request.list', ['tab' => 'pending']) }}"
                    class="request-tabs__item {{ $tab === 'pending' ? 'is-active' : '' }}">
                    承認待ち
                </a>
                <a href="{{ route('stamp_correction_request.list', ['tab' => 'approved']) }}"
                    class="request-tabs__item {{ $tab === 'approved' ? 'is-active' : '' }}">
                    承認済み
                </a>
            </div>

            <div class="request-table-card">
                <table class="request-table">
                    <thead>
                        <tr>
                            <th>状態</th>
                            <th>名前</th>
                            <th>対象日時</th>
                            <th>申請理由</th>
                            <th>申請日時</th>
                            <th>詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['status_label'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['work_date'] }}</td>
                                <td class="request-reason" title="{{ $row['reason'] }}">{{ $row['reason'] }}</td>
                                <td>{{ $row['requested_at'] }}</td>
                                <td><a class="request-detail-link" href="{{ $row['detail_url'] }}">詳細</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td class="request-empty" colspan="6">データがありません</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
