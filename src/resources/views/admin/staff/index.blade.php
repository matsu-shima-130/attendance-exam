@extends('layouts.app')

@section('title', 'スタッフ一覧')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin/staff/index.css') }}">
@endsection

@section('content')
    <div class="staff-list">
        <div class="staff-list__inner">
            <h1 class="staff-list__title">
                <span class="staff-list__title-bar"></span>
                スタッフ一覧
            </h1>

            <div class="table-wrap">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>名前</th>
                            <th>メールアドレス</th>
                            <th>月次勤怠</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['email'] }}</td>
                                <td>
                                    <a class="detail-link" href="{{ $row['monthly_url'] }}">詳細</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
