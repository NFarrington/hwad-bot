@extends('base')

@section('content')
    <table class="table">
        <tr>
            <th>Username/Nickname</th>
            <th>Old Value</th>
            <th>New Value</th>
            <th>Date Changed</th>
        </tr>
        @foreach($changes as $change)
            <tr>
                <td>{{ $change->key }}</td>
                <td>{{ $change->old_value }}</td>
                <td>{{ $change->new_value }}</td>
                <td>{{ $change->created_at->tz('America/New_York') }}</td>
            </tr>
        @endforeach
    </table>
@endsection
