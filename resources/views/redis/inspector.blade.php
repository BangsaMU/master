@extends('adminlte::page')

@section('content')
<div class="container">
    <h3>Redis Inspector</h3>

    <form method="GET" class="mb-3">
        <input type="text" name="pattern" value="{{ $pattern }}" placeholder="Key pattern (e.g. *)" class="form-control w-25 d-inline">
        <button class="btn btn-primary">Filter</button>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Key</th>
                <th>Type</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $item)
                <tr>
                    <td>{{ $item['key'] }}</td>
                    <td>{{ $item['type'] }}</td>
                    <td><pre>{{ print_r($item['value'], true) }}</pre></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
