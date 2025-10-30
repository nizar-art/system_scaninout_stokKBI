@extends('layouts.app_userinout')

@section('title', 'History Transaksi Stock')

@section('contents')
<div class="container py-4">

    <!-- Header -->
    <div class="data-header mb-4 p-3 p-md-4 rounded shadow-sm">
        <h4 class="fw-semibold text-white mb-1">History Transaksi Stock</h4>
        <p class="text-light mb-0" style="opacity: 0.8;">Filter dan lihat riwayat hasil scan dan import stok.</p>
    </div>

    <!-- Card -->
    <div class="table-container">
        <div class="mb-3">
            <h5 class="text-white mb-2 d-flex align-items-center gap-2">
                Data History Transaksi
            </h5>

            <div class="mt-2">
                <a href="{{ route('historyscan.export', request()->query()) }}" 
                class="btn btn-success btn-sm d-inline-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-excel"></i>
                    Export Excel History Scan
                </a>
            </div>
        </div>
        <!-- Filter -->
        <!-- Filter Section -->
        <form action="{{ route('historyscan.index') }}" method="GET" class="mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="inventory_id" class="form-label text-light mb-1">Inventory ID</label>
                    <input type="text" id="inventory_id" name="inventory_id" class="form-control"
                        placeholder="Cari Inventory ID"
                        value="{{ request('inventory_id') }}">
                </div>

                <div class="col-md-3">
                    <label for="user_id" class="form-label text-light mb-1">Prepared By</label>
                    <select id="user_id" name="user_id" class="form-select">
                        <option value="">-- Semua User --</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->username }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label text-light mb-1">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">-- Semua Status --</option>
                        <option value="in" {{ request('status') == 'in' ? 'selected' : '' }}>In Stok</option>
                        <option value="out" {{ request('status') == 'out' ? 'selected' : '' }}>Out Stok</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-light mb-1">Tanggal Scan</label>
                    <input type="text" id="date_range" class="form-control"
                        name="date_range" value="{{ request('date_range') }}" placeholder="Select date range">
                </div>


                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                    <a href="{{ route('historyscan.index') }}" class="btn btn-secondary flex-fill">Reset</a>
                </div>
            </div>
        </form>


        <!-- Items per page -->
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
                    @foreach ($histories as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->part->Inv_id ?? '-' }}</td>
                            <td>{{ $item->user->username ?? '-' }}</td>
                            <td>{{ $item->qrcode_raw ?? '-' }}</td>
                            <td class="text-center">{{ $item->stok_inout ?? 0 }}</td>
                            <td class="text-center">
                                @if (strtolower($item->status) == 'in')
                                    <span class="status-badge status-in">In Stok</span>
                                @elseif (strtolower($item->status) == 'out')
                                    <span class="status-badge status-out">Out Stok</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $item->scanned_at ? $item->scanned_at->format('Y-m-d H:i') : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper d-flex justify-content-between align-items-center mt-3 flex-wrap">
            <div id="pagination-info"></div>
            <ul id="pagination-buttons" class="pagination mb-0"></ul>
        </div>
    </div>
</div>

<!-- ================= JavaScript Pagination ================= -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const rows = Array.from(document.querySelectorAll("#tableBody tr"));
    const perPageSelect = document.getElementById("perPage");
    const pagination = document.getElementById("pagination-buttons");
    const info = document.getElementById("pagination-info");
    let perPage = parseInt(perPageSelect.value);
    let currentPage = 1;

    function renderPagination(totalPages) {
        pagination.innerHTML = "";

        const createButton = (label, disabled = false, active = false, clickFn = null) => {
            const li = document.createElement("li");
            li.className = `page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}`;
            const a = document.createElement("a");
            a.className = "page-link";
            a.href = "#";
            a.innerText = label;
            if (clickFn) a.onclick = (e) => { e.preventDefault(); clickFn(); };
            li.appendChild(a);
            pagination.appendChild(li);
        };

        // Prev
        createButton("‹", currentPage === 1, false, () => {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
            }
        });

        // Numbered pages
        const maxVisible = 10;
        let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let end = Math.min(totalPages, start + maxVisible - 1);

        if (end - start < maxVisible - 1) start = Math.max(1, end - maxVisible + 1);

        if (start > 1) {
            createButton("1", false, currentPage === 1, () => { currentPage = 1; renderTable(); });
            if (start > 2) {
                const dots = document.createElement("li");
                dots.className = "page-item disabled";
                dots.innerHTML = '<span class="page-link">...</span>';
                pagination.appendChild(dots);
            }
        }

        for (let i = start; i <= end; i++) {
            createButton(i, false, i === currentPage, () => {
                currentPage = i;
                renderTable();
            });
        }

        if (end < totalPages) {
            if (end < totalPages - 1) {
                const dots = document.createElement("li");
                dots.className = "page-item disabled";
                dots.innerHTML = '<span class="page-link">...</span>';
                pagination.appendChild(dots);
            }
            createButton(totalPages, false, currentPage === totalPages, () => {
                currentPage = totalPages;
                renderTable();
            });
        }

        // Next
        createButton("›", currentPage === totalPages, false, () => {
            if (currentPage < totalPages) {
                currentPage++;
                renderTable();
            }
        });
    }

    function renderTable() {
        rows.forEach((row, index) => {
            row.style.display = (index >= (currentPage - 1) * perPage && index < currentPage * perPage) ? "" : "none";
        });

        const totalItems = rows.length;
        const totalPages = Math.ceil(totalItems / perPage);
        const start = (currentPage - 1) * perPage + 1;
        const end = Math.min(currentPage * perPage, totalItems);
        info.innerHTML = `Showing ${start} to ${end} of ${totalItems} results`;

        renderPagination(totalPages);
    }

    perPageSelect.addEventListener("change", () => {
        perPage = parseInt(perPageSelect.value);
        currentPage = 1;
        renderTable();
    });

    renderTable();
});
flatpickr("#date_range", {
    mode: "range",
    dateFormat: "Y-m-d",
    defaultDate: "{{ request('date_range') }}" || new Date(),
});
</script>

<!-- ================= CSS ================= -->
<style>
    .data-header { background: #2f4357; border: 1px solid #415a70; }
    .table-container { background: #2f4357; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,0.2); }
    .custom-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; color: #fff; }
    .custom-table th, .custom-table td { padding: 0.75rem 1rem; }
    .custom-table thead th { border-bottom: 2px solid #6e6d6d; background-color: #032950; font-weight: 600; }
    .custom-table tbody tr { border-bottom: 1px solid #a3a3a3; }
    .custom-table tbody tr:hover { background-color: #032950; }
    .custom-table tbody tr:nth-child(even) { background-color: #35495e; }

    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; }
    .status-in { background-color: #145a32; color: #fff; }
    .status-out { background-color: #7b241c; color: #fff; }

    /* Pagination Styling */
    .pagination-wrapper { width: 100%; }
    #pagination-info { font-size: 1rem; color: #fff; }
    .pagination .page-link { color: #0d6efd; border: 1px solid #dee2e6; background: #fff; border-radius: 6px; padding: 5px 10px; }
    .pagination .page-item.active .page-link { background-color: #0d6efd; color: #fff; border-color: #0d6efd; }
    .pagination .page-link:hover { background-color: #f1f3f5; color: #0a58ca; }
    .pagination .page-item.disabled .page-link { background-color: #e9ecef; color: #6c757d; }
</style>
@endsection
