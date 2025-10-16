@extends('layouts.app_userin')

@section('title', 'Penambahan Stock')

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

    <!-- Form Tambah Stok -->
    <div class="card mt-4 shadow-lg border-0 rounded-3">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h4>Penambahan Stock Inventory</h4>
            </div>

            <form method="POST" action="{{ route('scan.store.history.in', $part->id) }}">
                @csrf

                <!-- Status (IN) -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Status</label>
                    <input 
                        type="text" 
                        class="form-control bg-light border-0" 
                        value="IN STOCK (Tambah Stok)" 
                        disabled
                    >
                    <input type="hidden" name="status" value="IN">
                </div>

                <!-- Input Jumlah / Rak / Tanggal -->
                <div class="row g-3">
                    <div class="col-md-4">
                    <label>Jumlah Tambah</label>
                        <input type="number" name="stok_inout_display" id="stok_inout_display"
                            value="{{ $stok_inout ?? 20 }}" class="form-control bg-light" readonly>
                        <input type="hidden" name="stok_inout" value="{{ $stok_inout ?? 20 }}">
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
                        <input type="text" class="form-control bg-light" value="{{ now()->format('Y-m-d H:i:s') }}" readonly>
                    </div>
                </div>

                <!-- Hidden fields -->
                <input type="hidden" name="id_inventory" value="{{ $part->id }}">
                <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                <input type="hidden" name="qrcode_raw" value="{{ $qrcode_raw ?? '' }}">

                <!-- Tombol Aksi -->
                <div class="mt-4 d-grid gap-2">
                    <button type="submit" class="btn btn-success w-100">Simpan Perubahan</button>
                    <a href="{{ route('dashboardinout.index') }}" class="btn btn-secondary mt-3 w-100">Kembali</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
