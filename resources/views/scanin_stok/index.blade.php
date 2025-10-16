@extends('layouts.app_userin')

@section('title', 'Scan In Stock')

@section('contents')
    @push('scripts')
        <!-- Deteksi koneksi offline/online -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Fungsi untuk menangani status koneksi
                function handleConnectionStatus() {
                    const offlineAlert = document.getElementById('offline-alert');

                    if (!navigator.onLine) {
                        // Jika tidak ada elemen peringatan, buat baru
                        if (!offlineAlert) {
                            const alert = document.createElement('div');
                            alert.id = 'offline-alert';
                            alert.style =
                                'position:fixed;top:0;left:0;width:100%;background:red;color:white;text-align:center;padding:10px;z-index:10000;';
                            alert.innerHTML =
                                '<strong>Anda sedang offline!</strong> Mohon periksa koneksi internet Anda sebelum melanjutkan.';
                            document.body.prepend(alert);
                        } else {
                            offlineAlert.style.display = 'block';
                        }

                        // Nonaktifkan tombol submit
                        document.querySelectorAll('button[type="submit"]').forEach(btn => {
                            btn.disabled = true;
                        });
                    } else {
                        // Jika online, sembunyikan peringatan
                        if (offlineAlert) {
                            offlineAlert.style.display = 'none';
                        }

                        // Aktifkan kembali tombol submit
                        document.querySelectorAll('button[type="submit"]').forEach(btn => {
                            btn.disabled = false;
                        });
                    }
                }

                // Panggil saat halaman dimuat
                handleConnectionStatus();

                // Tambahkan event listener untuk perubahan status koneksi
                window.addEventListener('online', handleConnectionStatus);
                window.addEventListener('offline', handleConnectionStatus);
            });
        </script>

        <!-- Loading indicator untuk semua form -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Hapus semua overlay loading saat halaman dimuat
                function clearAllOverlays() {
                    // Hapus semua overlay loading yang mungkin masih ada
                    const overlays = [
                        document.getElementById('loading-overlay'),
                        document.getElementById('edit-loading-overlay'),
                        document.getElementById('print-loading'),
                        document.getElementById('slow-connection-warning')
                    ];

                    overlays.forEach(overlay => {
                        if (overlay) overlay.remove();
                    });

                    // Reset semua tombol yang mungkin masih dalam status loading
                    document.querySelectorAll('button[data-submitting="true"]').forEach(btn => {
                        btn.removeAttribute('data-submitting');
                        btn.disabled = false;
                    });

                    // Hapus semua timeout yang tersimpan
                    if (sessionStorage.getItem('slowConnectionTimeoutId')) {
                        clearTimeout(parseInt(sessionStorage.getItem('slowConnectionTimeoutId')));
                        sessionStorage.removeItem('slowConnectionTimeoutId');
                    }

                    if (sessionStorage.getItem('resetTimeoutId')) {
                        clearTimeout(parseInt(sessionStorage.getItem('resetTimeoutId')));
                        sessionStorage.removeItem('resetTimeoutId');
                    }

                    if (sessionStorage.getItem('printTimeout')) {
                        clearTimeout(parseInt(sessionStorage.getItem('printTimeout')));
                        sessionStorage.removeItem('printTimeout');
                    }
                }

                // Bersihkan saat halaman dimuat (termasuk dari navigasi back/forward)
                clearAllOverlays();

                // Bersihkan juga saat navigasi back dengan mendeteksi pageshow event
                window.addEventListener('pageshow', function(event) {
                    // Jika halaman dimuat dari cache (seperti navigasi back)
                    if (event.persisted) {
                        clearAllOverlays();
                    }
                });

                // Fungsi untuk mencegah submit berulang dan menambahkan loading
                function preventDoubleSubmission(formId, loadingText) {
                    const form = document.getElementById(formId);
                    if (!form) return;

                    // Tambahkan hidden input untuk request ID (mencegah duplikasi)
                    const requestId = 'req_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'request_id';
                    hiddenInput.value = requestId;
                    form.appendChild(hiddenInput);

                    form.addEventListener('submit', function(e) {
                        // Cek koneksi
                        if (!navigator.onLine) {
                            e.preventDefault();
                            alert('Anda sedang offline. Mohon periksa koneksi internet Anda.');
                            return false;
                        }

                        // Dapatkan tombol submit
                        const submitBtn = this.querySelector('button[type="submit"]');
                        if (!submitBtn) return true;

                        // Cek apakah form sudah disubmit (mencegah double submit)
                        if (submitBtn.getAttribute('data-submitting') === 'true') {
                            e.preventDefault();
                            return false;
                        }

                        // Set status submitting
                        submitBtn.setAttribute('data-submitting', 'true');
                        const originalText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        // submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> ' + loadingText + '...';
                        submitBtn.innerHTML = loadingText;

                        // Hapus overlay lama jika ada
                        clearAllOverlays();

                        // Tambahkan overlay loading
                        const overlay = document.createElement('div');
                        overlay.id = 'loading-overlay';
                        overlay.style =
                            'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;display:flex;justify-content:center;align-items:center;';
                        overlay.innerHTML = `
                            <div style="background:white;padding:20px;border-radius:5px;text-align:center;">
                                <div class="spinner-border text-primary" role="status"></div>
                                <h5 class="mt-2">Mohon tunggu...</h5>
                                <p>Sedang memproses permintaan Anda.</p>
                                <div id="slow-connection-warning" style="display:none;color:orange;margin-top:10px;">
                                    Koneksi lambat terdeteksi. Tetap tunggu...
                                </div>
                            </div>
                        `;
                        document.body.appendChild(overlay);

                        // Set timeout untuk koneksi lambat
                        const slowConnectionTimeout = setTimeout(function() {
                            const warning = document.getElementById('slow-connection-warning');
                            if (warning) warning.style.display = 'block';
                        }, 5000); // 5 detik

                        // Store timeout ID in sessionStorage to be able to clear it later
                        sessionStorage.setItem('slowConnectionTimeoutId', slowConnectionTimeout);

                        // Set timeout untuk reset form jika terlalu lama (30 detik)
                        const resetTimeout = setTimeout(function() {
                            const existingOverlay = document.getElementById('loading-overlay');
                            if (existingOverlay) {
                                existingOverlay.innerHTML = `
                                    <div style="background:white;padding:20px;border-radius:5px;text-align:center;">
                                        <h5 class="text-warning">Koneksi lambat terdeteksi</h5>
                                        <p>Silakan coba lagi atau periksa halaman utama untuk melihat apakah data sudah tersimpan.</p>
                                        <button id="close-overlay" class="btn btn-secondary mt-2">Tutup</button>
                                    </div>
                                `;
                                document.getElementById('close-overlay').addEventListener('click',
                                    function() {
                                        existingOverlay.remove();
                                        submitBtn.disabled = false;
                                        submitBtn.innerHTML = originalText;
                                        submitBtn.removeAttribute('data-submitting');
                                    });
                            }
                        }, 30000); // 30 detik

                        // Store timeout ID in sessionStorage to be able to clear it later
                        sessionStorage.setItem('resetTimeoutId', resetTimeout);

                        // Add event listener for beforeunload to clean up timeouts and overlays
                        window.addEventListener('beforeunload', function() {
                            clearTimeout(parseInt(sessionStorage.getItem('slowConnectionTimeoutId')));
                            clearTimeout(parseInt(sessionStorage.getItem('resetTimeoutId')));
                            sessionStorage.removeItem('slowConnectionTimeoutId');
                            sessionStorage.removeItem('resetTimeoutId');
                        });

                        return true;
                    });
                }

                // Terapkan untuk semua form
                preventDoubleSubmission('stoForm', 'Show');
                preventDoubleSubmission('searchForm', 'Search');

                // Untuk tombol edit yang bukan form
                const editBtn = document.querySelector('button[onclick="redirectToEdit()"]');
                if (editBtn) {
                    editBtn.addEventListener('click', function(e) {
                        if (!navigator.onLine) {
                            e.preventDefault();
                            alert('Anda sedang offline. Mohon periksa koneksi internet Anda.');
                            return;
                        }

                        const reportId = document.getElementById('id_report').value.trim();
                        if (!reportId) {
                            alert('Harap masukkan Report Number');
                            return;
                        }

                        // Hapus overlay lama jika ada
                        clearAllOverlays();

                        // Tampilkan loading
                        this.disabled = false;
                        this.innerHTML = 'Edit';

                        // Tambahkan overlay loading yang lebih sederhana
                        const overlay = document.createElement('div');
                        overlay.id = 'edit-loading-overlay';
                        overlay.style =
                            'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;display:flex;justify-content:center;align-items:center;';
                        overlay.innerHTML = `
                            <div style="background:white;padding:20px;border-radius:5px;text-align:center;">
                                <div class="spinner-border text-primary" role="status"></div>
                                <h5 class="mt-2">Membuka halaman edit...</h5>
                            </div>
                        `;
                        document.body.appendChild(overlay);

                        // Lanjutkan dengan fungsi asli
                        redirectToEdit();
                    });
                }
            });

            // Optimalkan fungsi redirectToEdit
            function redirectToEdit() {
                const reportId = document.getElementById('id_report').value.trim();
                if (reportId) {
                    window.location.href = "{{ route('scan.edit.report.in', ':id') }}".replace(':id', encodeURIComponent(reportId));
                } else {
                    alert('Harap masukkan Report Number');
                }
            }
        </script>

        <!-- Script untuk mempercepat loading print iframe -->
        @if (session('report'))
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Konfirmasi Print',
                        text: 'Apakah Anda ingin mencetak dokumen ini?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Print',
                        cancelButtonText: 'Tidak',
                        reverseButtons: true,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: true,
                        showLoaderOnConfirm: false,
                        willOpen: () => {
                            playAlertSound(); // Mainkan sebelum alert muncul
                        },
                        didOpen: () => {
                            // Backup jika willOpen tidak bekerja
                            playAlertSound();
                        }

                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Clear any existing print-loading elements
                            const existingPrintLoading = document.getElementById('print-loading');
                            if (existingPrintLoading) {
                                existingPrintLoading.remove();
                            }

                            // Hapus timeout yang mungkin masih ada
                            if (sessionStorage.getItem('printTimeout')) {
                                clearTimeout(parseInt(sessionStorage.getItem('printTimeout')));
                            }

                            // Tampilkan indikator loading untuk print
                            const printLoadingDiv = document.createElement('div');
                            printLoadingDiv.id = 'print-loading';
                            printLoadingDiv.innerHTML = `
                                <div style="position:fixed;top:10px;right:10px;background:rgba(0,0,0,0.7);color:white;padding:10px;border-radius:5px;z-index:9999;">
                                    <span>Mempersiapkan dokumen...</span>
                                    <div class="spinner-border spinner-border-sm text-light ml-2" role="status"></div>
                                </div>
                            `;
                            document.body.appendChild(printLoadingDiv);

                            // Tambahkan timeout untuk menampilkan pesan koneksi lambat
                            const printTimeout = setTimeout(function() {
                                if (document.getElementById('print-loading')) {
                                    document.getElementById('print-loading').innerHTML = `
                                        <div style="position:fixed;top:10px;right:10px;background:rgba(0,0,0,0.7);color:white;padding:10px;border-radius:5px;z-index:9999;">
                                            <span>Koneksi lambat. Masih mempersiapkan dokumen...</span>
                                            <div class="spinner-border spinner-border-sm text-warning ml-2" role="status"></div>
                                        </div>
                                    `;
                                }
                            }, 5000);

                            // Store the timeout ID to be able to clear it if needed
                            sessionStorage.setItem('printTimeout', printTimeout);

                            // Cleanup on page unload
                            window.addEventListener('beforeunload', function() {
                                clearTimeout(parseInt(sessionStorage.getItem('printTimeout')));
                                sessionStorage.removeItem('printTimeout');
                            });

                            // Tambahkan event untuk navigasi kembali
                            window.addEventListener('pageshow', function(event) {
                                // Jika halaman dimuat dari cache (seperti navigasi back)
                                if (event.persisted) {
                                    const printLoading = document.getElementById('print-loading');
                                    if (printLoading) {
                                        printLoading.remove();
                                    }
                                    if (sessionStorage.getItem('printTimeout')) {
                                        clearTimeout(parseInt(sessionStorage.getItem('printTimeout')));
                                        sessionStorage.removeItem('printTimeout');
                                    }
                                }
                            });

                            // Tambahkan retry logic jika fetch gagal
                            let retryCount = 0;
                            const maxRetries = 3;

                            function fetchPrintContent() {
                                fetch(`{{ route('reports.print-content', session('report')) }}`)
                                    .then(res => {
                                        if (!res.ok) {
                                            throw new Error('Server response: ' + res.status);
                                        }
                                        return res.json();
                                    })
                                    .then(data => {
                                        // Bersihkan timeout dan loading indicator
                                        clearTimeout(printTimeout);
                                        if (document.getElementById('print-loading')) {
                                            document.getElementById('print-loading').remove();
                                        }

                                        // Proses data print seperti biasa
                                        const iframe = document.createElement('iframe');
                                        iframe.style.position = 'fixed';
                                        iframe.style.right = '0';
                                        iframe.style.bottom = '0';
                                        iframe.style.width = '0';
                                        iframe.style.height = '0';
                                        iframe.style.border = '0';
                                        iframe.id = 'printIframe';
                                        document.body.appendChild(iframe);

                                        const doc = iframe.contentDocument || iframe.contentWindow.document;
                                        doc.open();
                                        doc.write(`
                                        <html>
                                        <head>
                                            <meta charset="utf-8">
                                            <title>Print</title>
                                            <style>
                                                @media print {
                                                    @page {
                                                        size: 58mm auto;
                                                        margin: 0;
                                                    }
                                                    body {
                                                        margin: 0;
                                                        padding: 0;
                                                    }
                                                }
                                                ${data.styles ?? ''}
                                            </style>
                                        </head>
                                        <body>
                                            ${data.html}
                                            <script>
                                                window.onload = function () {
                                                    window.print();
                                                };
                                            <\/script>
                                        </body>
                                        </html>
                                    `);
                                        doc.close();

                                        // Bersihkan session print setelah 5 detik untuk mencegah print berulang
                                        setTimeout(function() {
                                            fetch("{{ route('reports.clear-session') }}").catch(err =>
                                                console.log(
                                                    'Error clearing session:', err));
                                        }, 5000);
                                    })
                                    .catch(err => {
                                        console.error("Gagal mengambil data print", err);

                                        // Coba lagi jika masih dalam batas retry
                                        if (retryCount < maxRetries) {
                                            retryCount++;
                                            // Update loading message
                                            if (document.getElementById('print-loading')) {
                                                document.getElementById('print-loading').innerHTML = `
                                                <div style="position:fixed;top:10px;right:10px;background:rgba(255,153,0,0.9);color:white;padding:10px;border-radius:5px;z-index:9999;">
                                                    <span>Mencoba kembali (${retryCount}/${maxRetries})...</span>
                                                    <div class="spinner-border spinner-border-sm text-light ml-2" role="status"></div>
                                                </div>
                                            `;
                                            }

                                            // Tunggu sebelum mencoba lagi
                                            setTimeout(fetchPrintContent, 2000);
                                        } else {
                                            // Tampilkan pesan gagal setelah mencoba beberapa kali
                                            if (document.getElementById('print-loading')) {
                                                document.getElementById('print-loading').innerHTML = `
                                                <div style="position:fixed;top:10px;right:10px;background:rgba(220,53,69,0.9);color:white;padding:10px;border-radius:5px;z-index:9999;">
                                                    <span>Gagal menyiapkan dokumen. <a href="{{ route('dailyreport.index') }}" style="color:white;text-decoration:underline;">Coba lagi</a></span>
                                                </div>
                                            `;
                                            }

                                            // Otomatis hilangkan pesan error setelah 10 detik
                                            setTimeout(function() {
                                                if (document.getElementById('print-loading')) {
                                                    document.getElementById('print-loading').remove();
                                                }
                                            }, 10000);
                                        }
                                    });
                            }

                            // Mulai fetch
                            fetchPrintContent();
                        }
                        // Jika user memilih "Tidak", tidak melakukan apa-apa
                    });
                });
            </script>
        @endif

        <!-- Alert messages - tetap gunakan kode yang sudah ada -->
        @if (session('success'))
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                Swal.fire({
                    toast: true,
                    position: "top-end",
                    icon: "success",
                    title: "{{ session('success') }}",
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            </script>
        @endif
        @if (session('info'))
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                Swal.fire({
                    toast: true,
                    position: "top-end",
                    icon: "warning",
                    title: "{{ session('info') }}",
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            </script>
        @endif
    @endpush

    {{-- Main Content --}}
    <div class="container">
        <div class="card p-2 p-md-4 mt-4 shadow-lg">
            <!-- Form Packing -->
            <form action="{{ route('scan.instok') }}" method="POST" id="stoForm">
                @csrf
                <div class="mb-2">
                    <label for="inventory_id" class="form-label" style="font-size: 1.1rem;">Inventory ID (Scan QR)</label>
                    <div class="input-group my-2 my-md-3">
                        <input type="text" name="inventory_id" class="form-control" id="inventory_id"
                            placeholder="Masukkan ID Inventory" required autofocus>
                        <button class="btn btn-secondary" type="button" id="scanPart" onclick="toggleScanner()">
                            <i class="bi bi-camera"></i>
                        </button>
                    </div>
                    <button class="btn btn-primary btn-lg w-100 mt-2" type="submit" id="btnSubmit">Show</button>
                </div>
                <input type="hidden" name="action" value="show" id="actionField">
                {{-- code untuk scann --}}
                <div id="reader" style="display: none;"></div>
                {{-- -------- --}}
            </form>
        </div>
        <div class="card p-2 p-md-4 mt-4 shadow-lg">
            <!-- Form Search -->
            <form action="{{ route('scan.searchin') }}" method="GET" id="searchForm">
                <div class="mb-2">
                    <label for="search_query" class="form-label" style="font-size: 1.1rem;">Cari Part Name Atau
                        Number</label>
                    <div class="input-group my-2 my-md-3">
                        <input type="text" name="query" class="form-control" id="search_query"
                            placeholder="Masukkan Part Name atau Part Number" required>
                    </div>
                    <button class="btn btn-primary btn-lg w-100 mt-2" type="submit" id="btnSubmit">Search</Search></button>
                </div>
            </form>
            <a href="{{ route('dashboardinout.index') }}" class="btn btn-success mt-3">Back</a>
        </div>
    </div>


@endsection
