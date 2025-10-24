@extends('layouts.app_userout')

@section('title', 'Search Part Name & Number')

@section('contents')
<div class="container">
    <div class="card p-2 p-md-4 mt-4 shadow-lg">
        <h5 class="card-title text-white">Search Results</h5>

        <!-- Search Form -->
        <form action="{{ route('scan.searchout') }}" method="GET" class="mb-4">
            <div class="input-group">
                <input type="text" name="query" class="form-control" placeholder="Search for parts..." required>
                <div class="input-group-append">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </div>
        </form>

        @if ($results->isEmpty())
            <p class="text-white">No results found.</p>
        @else
            <div class="table-responsive">
                <table class="table table-bordered text-center align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>Inventory ID</th>
                            <th>Part Name</th>
                            <th>Part Number</th>
                            <th>Plant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $result)
                            <tr>
                                <td>
                                    <form action="{{ route('scan.outstok') }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="inventory_id" value="{{ $result->id }}">
                                        <button type="submit" class="btn btn-link p-0 border-0 bg-transparent">
                                            {{ $result->Inv_id ?? '-' }}
                                        </button>
                                    </form>
                                </td>
                                <td>{{ $result->Part_name ?? '-' }}</td>
                                <td>{{ $result->Part_number ?? '-' }}</td>
                                <td>{{ $result->plant->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <a href="{{ route('scanOutStok.index') }}" class="btn btn-success mt-3">Back</a>
    </div>
</div>
@endsection
