<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title>Login In&Out Stock</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <!-- Favicons -->
    <link rel="icon" href="{{ asset('assets/img/icon-kbi.png') }}" loading="lazy" alt="logo" type="image/png">

    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <!-- Template Main CSS File -->
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/custom-auth.css') }}" rel="stylesheet">

    <!-- CSS Kustom untuk Select2 -->
    <style>
        /* Perbaikan tampilan Select2 */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
            padding: 5px 12px;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-left: 0;
            line-height: 1.5;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }

        .select2-container--bootstrap-5 .select2-dropdown {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
        }

        /* Penyesuaian untuk input group */
        .input-group .select2-container--bootstrap-5 {
            flex: 1 1 auto;
            width: 1% !important;
        }

        .input-group .select2-selection {
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
            border-left: 0 !important;
        }

        /* Modal scanner barcode */
        .modal-scanner {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-scanner.active {
            display: flex;
        }

        .modal-scanner-content {
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            max-width: 350px;
            width: 95vw;
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .modal-scanner-close {
            position: absolute;
            top: 8px;
            right: 12px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #888;
            cursor: pointer;
        }

        #reader {
            width: 100% !important;
            min-height: 220px;
        }

        @media (max-width: 500px) {
            .modal-scanner-content {
                padding: 0.5rem;
            }
        }
    </style>
</head>

<body>

    <main>
        <div class="container">
            <section
                class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">
                            <div class="logo-wrapper text-center py-3">
                                <img src="{{ asset('assets/img/kyoraku-baru.png') }}" alt="Logo Kyoraku"
                                    class="logo-auth" loading="lazy">
                            </div>

                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="pt-4 pb-2">
                                        <h5 class="card-title text-center pb-0 fs-4">User In & Out Stock</h5>
                                        <p class="text-center small">Enter your Id Card Number to login</p>
                                    </div>

                                    <form class="row g-3 needs-validation" novalidate
                                        action="{{ route('user.login.post') }}" method="POST">
                                        @csrf
                                        <!-- ID Card -->
                                        <div class="col-12">
                                            <label for="yournik" class="form-label">Id Card Number</label>
                                            <div class="input-group has-validation">
                                                <span class="input-group-text" id="inputGroupPrepend">
                                                    <i class="bi bi-person-vcard-fill"></i>
                                                </span>
                                                <input type="text" name="nik"
                                                    class="form-control @error('nik') is-invalid @enderror"
                                                    id="yournik" value="" autocomplete="off" autofocus>
                                                <!-- Tombol scan barcode -->
                                                <button type="button" class="btn btn-outline-secondary"
                                                    id="btn-scan-barcode" title="Scan Barcode" tabindex="-1">
                                                    <i class="bi bi-upc-scan"></i>
                                                </button>
                                                @error('nik')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                @enderror
                                            </div>
                                        </div>
                                        <!-- Plan Selector -->
                                        <div class="col-12">
                                            <label for="plan_select" class="form-label">Plant</label>
                                            <div class="input-group has-validation">
                                                <span class="input-group-text">
                                                    <i class="bi bi-map-fill"></i>
                                                </span>
                                                <select name="plan_id"
                                                    class="form-select select2-plan @error('plan_id') is-invalid @enderror"
                                                    id="plan_select" required>
                                                    <option value="">-Pilih Plant-</option>
                                                    @foreach ($plans as $plan)
                                                        @if ($plan)
                                                            {{-- Check if plan exists --}}
                                                            <option value="{{ $plan->id }}"
                                                                {{ old('plan_id') == $plan->id ? 'selected' : '' }}>
                                                                {{ $plan->name }}
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                                @error('plan_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                @enderror
                                            </div>
                                        </div>
                                        <!-- Tombol Submit -->
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary fw-bold w-100">Login</button>
                                        </div>
                                        {{-- <div class="col-12">
                                            <a href="{{ route('admin.login') }}"
                                                class="btn btn-outline-secondary w-100">
                                                <i class="bi bi-box-arrow-in-right"></i> Login Admin
                                            </a>
                                        </div> --}}
                                    </form>
                                </div>
                            </div>

                            <div class="last-update-text">
                                Last Update: 19 September 2025
                            </div>
                            <div class="credits">
                                &copy; Sto Management System
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Vendor JS Files -->
    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    {{-- @if (session('success.logout'))
        <script>
            Swal.fire({
                icon: 'success',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown' // Menambahkan animasi muncul
                },
                title: 'Login Berhasil',
                text: '{{ session('success.logout') }}',

            });
        </script>
    @endif --}}
    {{-- plant+area --}}
    <script>
        $(document).ready(function() {
            // Inisialisasi Select2 untuk plan dan area
            $('.select2-plan').select2({
                theme: 'bootstrap-5',
                placeholder: '-- Pilih Plant --',
                allowClear: true,
                width: '100%'
            });

            $('.select2-area').select2({
                theme: 'bootstrap-5',
                placeholder: '-- Pilih Area --',
                allowClear: true,
                width: '100%',
                dropdownParent: $('.select2-area').parent(),
                minimumResultsForSearch: 5,
                language: {
                    noResults: function() {
                        return "Data tidak ditemukan";
                    }
                }
            });

            // Event ketika plan berubah
            $('#plan_select').on('change', function() {
                var planId = $(this).val();
                var areaSelect = $('#area_select');

                // Kosongkan opsi area sebelumnya
                areaSelect.empty().append('<option value="">-- Pilih Area --</option>');

                if (planId) {
                    // Filter area berdasarkan plan yang dipilih
                    var areas = {!! json_encode($areas->toArray()) !!};
                    var filteredAreas = areas.filter(function(area) {
                        return area.id_plan == planId;
                    });

                    // Tambahkan opsi area yang sesuai
                    filteredAreas.forEach(function(area) {
                        areaSelect.append(
                            '<option value="' + area.id + '">' +
                            area.nama_area +
                            (area.plan ? ' - ' + area.plan.name : '') +
                            '</option>'
                        );
                    });

                    // Trigger perubahan untuk memperbarui tampilan Select2
                    areaSelect.trigger('change');
                }
            });

            // Jika ada nilai plan yang sudah dipilih (misalnya karena validasi gagal)
            @if (old('plan_id'))
                $('#plan_select').val('{{ old('plan_id') }}').trigger('change');
                // Setelah area dimuat, set nilai area yang lama jika ada
                @if (old('area_id'))
                    setTimeout(function() {
                        $('#area_select').val('{{ old('area_id') }}').trigger('change');
                    }, 500);
                @endif
            @endif
        });
    </script>

    <!-- Template Main JS File -->
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <!-- SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @if (session('warning'))
        <script>
            Swal.fire({
                icon: 'warning',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                title: 'Gagal Login',
                text: '{{ session('warning') }}',
            });
        </script>
    @endif

    @if (session('expired'))
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Session Expired',
                text: '{{ session('expired') }}',
            });
        </script>
    @endif

    {{-- @if (session('success.logout'))
        <script>
            Swal.fire({
                icon: 'success',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown' // Menambahkan animasi muncul
                },
                title: 'Login Berhasil',
                text: '{{ session('success.logout') }}',

            });
        </script>
    @endif --}}

    <div class="modal-scanner" id="modal-scanner">
        <div class="modal-scanner-content">
            <button class="modal-scanner-close" id="close-scanner" aria-label="Close">&times;</button>
            <div class="mb-2 fw-bold text-center">Scan Barcode ID Card</div>
            <div id="reader"></div>
            <div class="text-center mt-2">
                <small class="text-muted">Arahkan barcode ID Card ke kamera</small>
            </div>
        </div>
    </div>

    <!-- html5-qrcode JS CDN -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Barcode scanner logic
            let html5QrCode = null;
            const modalScanner = document.getElementById('modal-scanner');
            const btnScanBarcode = document.getElementById('btn-scan-barcode');
            const closeScanner = document.getElementById('close-scanner');
            const nikInput = document.getElementById('yournik');
            const readerDiv = document.getElementById('reader');

            // Verifikasi library sudah dimuat
            console.log("HTML5-QRCode library loaded:", typeof Html5Qrcode !== 'undefined');

            // Tambahkan fungsi untuk menampilkan notifikasi error
            function showScanError(message) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Mengakses Kamera',
                    text: message,
                    confirmButtonText: 'Tutup'
                });
            }

            btnScanBarcode.addEventListener('click', function() {
                // Verifikasi library tersedia sebelum memulai
                if (typeof Html5Qrcode === 'undefined') {
                    showScanError(
                        'Library scanner tidak dimuat dengan benar. Refresh halaman dan coba lagi.');
                    return;
                }

                modalScanner.classList.add('active');

                // Hapus elemen reader dan buat ulang
                readerDiv.innerHTML = '';

                // Buat instance baru setiap kali
                try {
                    html5QrCode = new Html5Qrcode("reader");

                    // Tambahkan elemen loader
                    readerDiv.innerHTML =
                        '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Memuat kamera...</p></div>';

                    // Cek kamera yang tersedia dengan penanganan error yang lebih baik
                    Html5Qrcode.getCameras()
                        .then(devices => {
                            // Hapus loader
                            readerDiv.innerHTML = '';

                            if (!devices || devices.length === 0) {
                                showScanError('Tidak ada kamera yang terdeteksi.');
                                modalScanner.classList.remove('active');
                                return;
                            }

                            // Pilih kamera belakang jika ada (label mengandung 'back' atau 'rear')
                            let selectedCamera = devices[0].id;
                            for (let device of devices) {
                                if (device.label && /(back|rear)/i.test(device.label)) {
                                    selectedCamera = device.id;
                                    break;
                                }
                            }

                            // Config untuk scanner - disableFlip agar tidak mirror
                            const config = {
                                fps: 10,
                                qrbox: {
                                    width: 250,
                                    height: 150
                                },
                                formatsToSupport: Html5QrcodeSupportedFormats.ALL_FORMATS,
                                disableFlip: true // <-- Tambahkan ini agar tidak mirror
                            };

                            html5QrCode.start(
                                selectedCamera,
                                config,
                                (decodedText) => {
                                    // Success callback
                                    nikInput.value = decodedText.trim();
                                    html5QrCode.stop()
                                        .then(() => modalScanner.classList.remove('active'))
                                        .catch(() => modalScanner.classList.remove('active'));
                                },
                                () => {
                                    /* Ignore errors */
                                }
                            ).catch(err => {
                                showScanError('Gagal mengakses kamera: ' + (err.message ||
                                    err));
                                modalScanner.classList.remove('active');
                            });
                        })
                        .catch(err => {
                            showScanError('Gagal mendapatkan daftar kamera: ' + (err.message || err));
                            modalScanner.classList.remove('active');
                        });
                } catch (error) {
                    showScanError('Error saat inisialisasi scanner: ' + error.message);
                    modalScanner.classList.remove('active');
                }
            });

            function closeScannerModal() {
                modalScanner.classList.remove('active');
                if (html5QrCode) {
                    try {
                        if (html5QrCode.isScanning) {
                            html5QrCode.stop().catch(() => {});
                        }
                    } catch (err) {
                        console.error("Error closing scanner:", err);
                    }
                }
            }

            closeScanner.addEventListener('click', closeScannerModal);

            modalScanner.addEventListener('click', function(e) {
                if (e.target === modalScanner) {
                    closeScannerModal();
                }
            });
        });
    </script>
</body>

</html>
