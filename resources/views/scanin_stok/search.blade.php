@extends('layouts.app_userin')

@section('title', 'Search Part Name & Number')

@section('contents')
<div class="container">
    <div class="card p-2 p-md-4 mt-4 shadow-lg">
        <h5 class="card-title text-white mb-3">Search Results</h5>

        <!-- 🔍 Search Form -->
        <form action="{{ route('scan.searchin') }}" method="GET" class="mb-4">
            <div class="input-group">
                <input type="text" name="query" class="form-control" placeholder="Search for parts..." value="{{ request('query') }}" required>
                <div class="input-group-append">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </div>
        </form>

        @if ($results->isEmpty())
            <p class="text-white">No results found.</p>
        @else
            <div class="table-responsive">
                <table class="table table-bordered text-center align-middle text-white">
                    <thead class="thead-light bg-secondary text-dark">
                        <tr>
                            <th>Inventory ID</th>
                            <th>Part Name</th>
                            <th>Part Number</th>
                            <th>Plant</th>
                            <th>Total Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $result)
                            @php
                                // hitung total qty dari DailyStockLog per inventory
                                $totalQty = \App\Models\DailyStockLog::where('id_inventory', $result->id)
                                    ->sum('Total_qty');
                            @endphp
                            <tr>
                                <!-- Klik inventory ID untuk scan in -->
                                <td>
                                    <form action="{{ route('scan.instok') }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="inventory_id" value="{{ $result->Inv_id }}">
                                        <button type="submit" class="btn btn-link p-0 border-0 bg-transparent text-info">
                                            {{ $result->Inv_id ?? '-' }}
                                        </button>
                                    </form>
                                </td>
                                <td>{{ $result->Part_name ?? '-' }}</td>
                                <td>{{ $result->Part_number ?? '-' }}</td>
                                <td>{{ $result->plant->Plant_name ?? '-' }}</td>
                                <td>{{ $totalQty }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-3">
                {{ $results->links() }}
            </div>
        @endif
        <a href="{{ route('scanInStok.index') }}" class="btn btn-success mt-3">Back</a>
    </div>
</div>
@endsection
