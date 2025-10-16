@extends('layouts.app_userin')

@section('title', 'Pengurangan Stock')

@section('contents')
<div class="container">
    <!-- Card Informasi Part -->
    <div class="card p-2 p-md-4 mt-4 shadow-lg">
        <h5 class="mb-3">Informasi Part</h5>
        <table class="table table-bordered">
            <tbody>
                <tr>
                    <th style="width: 200px;">Inventory ID</th>
                    <td>{{ $part->Inv_id ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Part Name</th>
                    <td>{{ $part->Part_name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Part Number</th>
                    <td>{{ $part->Part_number ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Plant</th>
                    <td>{{ $part->plant->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Stok Saat Ini</th>
                    <td>{{ $stok_saat_ini }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Form Kurangi Stok -->
    <div class="card mt-4 shadow-lg border-0 rounded-3">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h4>Pengurangan Stock Inventory</h4>
            </div>

            <form method="POST" action="{{ route('scan.store.history.out', $part->id) }}">
                @csrf

                <!-- Status (OUT) -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Status</label>
                    <input 
                        type="text" 
                        class="form-control bg-light border-0" 
                        value="OUT STOCK (Kurangi Stok)" 
                        disabled
                    >
                    <input type="hidden" name="status" value="OUT">
                </div>

                <!-- Input Jumlah / Rak / Tanggal -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <label>Jumlah Dikurangi</label>
                        <input 
                            type="number" 
                            name="stok_inout" 
                            id="stok_inout"
                            class="form-control" 
                            min="1"
                            required
                            placeholder="Masukkan jumlah stok keluar"
                        >
                    </div>
                    <div class="col-md-4">
                        <label>Lokasi Area</label>
                        <select name="area_id" class="form-control" required>
                            <option value="">-- Pilih Area --</option>
                            @foreach($areas as $area)
                                <option value="{{ $area->id }}">{{ $area->nama_area }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Lokasi Rak</label>
                        <select name="rak_id" id="rak_id" class="form-select" required>
                            <option value="">-- Pilih Rak --</option>
                            @foreach ($raks as $rak)
                                <option value="{{ $rak->id }}">
                                    {{ $rak->rak_name }} (Stok: {{ $rak->stok }})
                                </option>
                            @endforeach
                        </select>
                    </div>


                    <div class="col-md-4">
                        <label>Tanggal Scan</label>
                        <input 
                            type="text" 
                            class="form-control bg-light" 
                            value="{{ now()->format('Y-m-d H:i:s') }}" 
                            readonly
                        >
                    </div>
                </div>

                <!-- Hidden fields -->
                <input type="hidden" name="id_inventory" value="{{ $part->id }}">
                <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                <input type="hidden" name="qrcode_raw" value="{{ $qrcode_raw ?? '' }}">

                <!-- Tombol Aksi -->
                <div class="mt-4 d-grid gap-2">
                    <button type="submit" class="btn btn-danger w-100">Kurangi Stok</button>
                    <a href="{{ route('scanOutStok.index') }}" class="btn btn-secondary mt-3 w-100">Kembali</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
