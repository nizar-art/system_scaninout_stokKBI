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
                    <!-- Jumlah Tambah -->
                    <div class="col-md-4">
                        <label>Jumlah Tambah</label>
                        @php
                            // Kalau stok_inout dari barcode → readonly, kalau kosong → user isi manual
                            $readonly = !is_null($stok_inout);
                            $stokValue = $stok_inout ?? '';
                        @endphp

                        <input 
                            type="number" 
                            name="stok_inout_display" 
                            id="stok_inout_display"
                            value="{{ old('stok_inout_display', $stokValue) }}" 
                            class="form-control {{ $readonly ? 'bg-light' : '' }}" 
                            {{ $readonly ? 'readonly' : 'required' }}
                            placeholder="{{ $readonly ? '' : 'Masukkan jumlah stok' }}"
                        >

                        <input 
                            type="hidden" 
                            name="stok_inout" 
                            id="stok_inout" 
                            value="{{ old('stok_inout', $stokValue) }}"
                        >
                    </div>

                    <!-- Lokasi Area -->
                    <div class="col-md-4">
                        <label>Lokasi Area</label>
                        <select name="area_id" class="form-control" required>
                            <option value="">-- Pilih Area --</option>
                            @foreach($areas as $area)
                                <option value="{{ $area->id }}">{{ $area->nama_area }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Lokasi Rak -->
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

                    <!-- Tanggal Scan -->
                    <div class="col-md-4">
                        <label>Tanggal Scan</label>
                        <input type="text" class="form-control bg-light" value="{{ now()->format('Y-m-d H:i:s') }}" readonly>
                    </div>

                    <!-- QR Code -->
                    <div class="col-md-4">
                        <label>QR Code</label>
                        @php
                            $qrReadonly = !empty($qrcode_raw);
                            $qrValue = $qrcode_raw ?? '';
                        @endphp
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

<!-- Sinkronisasi input hidden -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const qtyDisplay = document.getElementById('stok_inout_display');
    const qtyHidden = document.getElementById('stok_inout');
    const qrDisplay = document.getElementById('qrcode_raw_display');
    const qrHidden = document.getElementById('qrcode_raw');

    if (qtyDisplay && qtyHidden) {
        qtyDisplay.addEventListener('input', function() {
            qtyHidden.value = this.value;
        });
    }

    if (qrDisplay && qrHidden) {
        qrDisplay.addEventListener('input', function() {
            qrHidden.value = this.value;
        });
    }
});
</script>
@endsection
