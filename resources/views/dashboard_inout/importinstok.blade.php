@extends('layouts.app_userinout')

@section('title', 'Import In Stock')

@section('contents')
<div class="container py-4">

    <!-- Header -->
    <div class="data-header mb-4 p-3 p-md-4 rounded shadow-sm text-center">
        <!-- Judul Utama -->
        <h4 class="fw-semibold text-white mb-2">Import In Stok</h4>

        <!-- Label Pengarah -->
        <p class="text-light mb-3" style="opacity: 0.85;">
            Gunakan fitur ini untuk melakukan import data stok dari file Excel atau CSV.
        </p>

        <!-- Tombol Import -->
        <button class="btn btn-success fw-semibold px-4 py-2">
            <i class="bi bi-upload me-2"></i> Import
        </button>
    </div>


    <!-- Card -->
    <div class="table-container">
        <div class="mb-3">
            <h5 class="text-white mb-2 d-flex align-items-center gap-2">
                Data Yang Akan Di Import
            </h5>
        </div>
        
        {{-- <!-- Items per page -->
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div class="d-flex align-items-center gap-2">
                <label for="perPage" class="text-light mb-0">Items per page:</label>
                <select id="perPage" class="form-select form-select-sm d-inline-block w-auto">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive">
            <table class="custom-table w-100" id="historyTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Inventory ID</th>
                        <th>Prepared By</th>
                        <th>QR Code</th>
                        <th class="text-center">Jumlah</th>
                        <th class="text-center">Status</th>
                        <th>Tanggal Scan</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper d-flex justify-content-between align-items-center mt-3 flex-wrap">
            <div id="pagination-info"></div>
            <ul id="pagination-buttons" class="pagination mb-0"></ul>
        </div> --}}
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
    .custom-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem;
        color: #fff;
    }
    .custom-table th, .custom-table td {
        padding: 0.75rem 1rem;
    }
    .custom-table thead th {
        border-bottom: 2px solid #6e6d6d;
        background-color: #032950;
        font-weight: 600;
    }
    .custom-table tbody tr {
        border-bottom: 1px solid #a3a3a3;
    }
    .custom-table tbody tr:hover {
        background-color: #032950;
    }
    .custom-table tbody tr:nth-child(even) {
        background-color: #35495e;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .status-in {
        background-color: #145a32;
        color: #fff;
    }
    .status-out {
        background-color: #7b241c;
        color: #fff;
    }

    /* Pagination Styling */
    .pagination-wrapper {
        width: 100%;
    }
    #pagination-info {
        font-size: 1rem;
        color: #fff;
    }
    .pagination .page-link {
        color: #0d6efd;
        border: 1px solid #dee2e6;
        background: #fff;
        border-radius: 6px;
        padding: 5px 10px;
    }
    .pagination .page-item.active .page-link {
        background-color: #0d6efd;
        color: #fff;
        border-color: #0d6efd;
    }
    .pagination .page-link:hover {
        background-color: #f1f3f5;
        color: #0a58ca;
    }
    .pagination .page-item.disabled .page-link {
        background-color: #e9ecef;
        color: #6c757d;
    }

    /* Import Button */
    .btn-success {
        background-color: #28a745;
        border: none;
        border-radius: 8px;
    }
    .btn-success:hover {
        background-color: #218838;
    }
</style>
@endsection
