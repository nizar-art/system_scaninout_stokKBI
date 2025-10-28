@extends('layouts.app_userinout')

@section('title', 'Import In Stock')

@section('contents')
<div class="container py-4">

    <!-- Header -->
    <div class="data-header mb-4 p-3 p-md-4 rounded shadow-sm text-center">
        <h4 class="fw-semibold text-white mb-2">Import In Stok</h4>
        <p class="text-light mb-3" style="opacity: 0.85;">
            Gunakan fitur ini untuk melakukan import data stok dari file Excel atau CSV.
        </p>

        <!-- Tombol Import -->
        <button id="btnImport" class="btn btn-success fw-semibold px-4 py-2" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="bi bi-upload me-2"></i> Import
        </button>
    </div>

    <!-- Container Data Preview -->
    <div class="table-container">
        <div class="mb-3">
            <h5 class="text-white mb-2 d-flex align-items-center gap-2">
                Data Yang Akan Di Import
            </h5>
        </div>

        @if(session('preview_instok'))
            <div class="table-responsive mt-3">
                <table class="table table-bordered text-white align-middle">
                    <thead class="bg-primary">
                        <tr>
                            <th>No</th>
                            <th>Inventory ID</th>
                            <th>Status</th>
                            <th>Jumlah</th>
                            <th>Area</th>
                            <th>Rak</th>
                            <th>Tanggal Import</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(session('preview_instok') as $i => $row)
                        <tr>
                            <td>{{ $i+1 }}</td>
                            <td>{{ $row['inventory_id'] }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td>{{ $row['jumlah'] }}</td>
                            <td>{{ $row['nama_area'] }}</td>
                            <td>{{ $row['rak_name'] }}</td>
                            <td>{{ $row['tanggal_scan'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="text-center mt-3">
                <form id="importFormFinal" action="{{ route('importinstok.store') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary px-4 py-2 me-2">
                        <i class="bi bi-upload me-2"></i> Import Sekarang
                    </button>
                </form>

                <!-- Tombol Cancel Reset -->
                <form action="{{ route('importinstok.cancel') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-danger px-4 py-2">
                        <i class="bi bi-x-circle me-2"></i> Cancel
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>

<!-- ================= MODAL IMPORT ================= -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-semibold" id="importModalLabel">Import In Stock from Excel</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="importFormPreview" action="{{ route('importinstok.preview') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold" style="color: black;">Upload Excel File</label>
                <input type="file" name="file" id="fileInput" class="form-control" accept=".xlsx,.xls,.csv" required>
            </div>
            <p class="small text-dark mb-2" >
                *Download Template Excel Import: 
                <a href="{{ route('importinstok.downloadTemplate') }}" class="text-warning text-decoration-none fw-semibold">
                    <i class="bi bi-download"></i> Klik di sini
                </a>
            </p>
            <p class="small text-dark mb-0">Format kolom: inventory_id, status, jumlah, nama_area, rak_name, tanggal_scan</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Tutup</button>
            <button type="submit" id="submitImport" class="btn btn-success px-3">Preview</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= CSS ================= -->
<style>
    .data-header {
        background: #2f4357;
        border: 1px solid #415a70;
    }
    .table-container {
        background: #2f4357;
        border-radius: 10px;
        padding: 1.5rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.2);
    }
    .btn-success {
        background-color: #28a745;
        border: none;
        border-radius: 8px;
    }
    .btn-success:hover {
        background-color: #218838;
    }
</style>

<!-- ================= JAVASCRIPT ================= -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 🔹 File preview info
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                Swal.fire({
                    icon: "info",
                    title: "File Siap",
                    text: `File "${file.name}" siap untuk di-preview.`,
                    confirmButtonText: "OK"
                });
            }
        });
    }

    // 🔹 Konfirmasi sebelum import
    const importFormFinal = document.getElementById('importFormFinal');
    if (importFormFinal) {
        importFormFinal.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: "Konfirmasi Import",
                text: "Apakah Anda yakin ingin mengimport semua data ini ke database?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, Import Sekarang",
                cancelButtonText: "Batal",
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: "Mengimport...",
                        text: "Mohon tunggu sebentar...",
                        icon: "info",
                        showConfirmButton: false,
                        allowOutsideClick: false
                    });

                    e.target.submit();
                }
            });
        });
    }
});
</script>

<!-- 🔹 ALERT DARI SESSION (Success / Error / Info) -->
@if (session('success'))
<script>
Swal.fire({
    icon: "success",
    title: "Berhasil!",
    text: "{{ session('success') }}",
    confirmButtonText: "OK"
});
</script>
@endif

@if (session('error'))
<script>
Swal.fire({
    icon: "error",
    title: "Gagal!",
    text: "{{ session('error') }}",
    confirmButtonText: "OK"
});
</script>
@endif

@if (session('info'))
<script>
Swal.fire({
    icon: "info",
    title: "Informasi",
    text: "{{ session('info') }}",
    confirmButtonText: "OK"
});
</script>
@endif
@if(session('import_fail_details'))
    <div class="alert alert-warning mt-4">
        <h6 class="fw-semibold"><i class="bi bi-exclamation-triangle me-2"></i>Beberapa data gagal diimport:</h6>
        <ul class="mb-0 small">
            @foreach (session('import_fail_details') as $msg)
                <li>{{ $msg }}</li>
            @endforeach
        </ul>
    </div>
@endif

@endsection

