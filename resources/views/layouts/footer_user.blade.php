<script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}" defer></script>
<!-- Template Main JS File -->
<script src="{{ asset('assets/js/main.js') }}" defer></script>
{{-- js select2 dan sweetalert2 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" defer></script>
<script src="https://printjs-4de6.kxcdn.com/print.min.js"></script>
{{-- sweetalert confirm --}}
<script>
    function confirmLogout() {
        Swal.fire({
            title: 'Apakah Anda yakin ingin keluar?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, keluar!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('logoutForm').submit();
            }
        });
    }
</script>
{{-- edit get daily --}}
<script>
    function redirectToEdit() {
        const id = document.getElementById('id_report').value.trim();

        if (!id) {
            alert('Masukkan nomor report terlebih dahulu.');
            return;
        }

        window.location.href = '/sto/edit-log/' + id;
    }

    function redirectToEdit() {
        const id = document.getElementById('id_report').value;
        if (id) {
            const url = `{{ url('/sto/edit-log/') }}/${id}`;
            window.location.href = url;
        } else {
            alert('Silakan masukkan ID terlebih dahulu.');
        }
    }
    setInterval(() => {
        fetch("{{ url('/keep-alive') }}", {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
    }, 5 * 60 * 1000); // setiap 5 menit
</script>

{{-- scan  --}}
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", {
            fps: 24,
            qrbox: function(viewfinderWidth, viewfinderHeight) {
                const isSmallDevice = viewfinderWidth < 600;
                const edge = isSmallDevice ? Math.min(viewfinderWidth, viewfinderHeight) : 500;

                return {
                    width: edge * 0.9,
                    height: edge * 0.5
                };
            }
        },
        false
    );


    function toggleScanner() {
        const reader = document.getElementById('reader');
        if (reader.style.display === 'none') {
            reader.style.display = 'block';
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        } else {
            html5QrcodeScanner.clear().then(() => {
                reader.style.display = 'none';
            }).catch(err => {
                console.error("Clear scanner error:", err);
            });
        }
    }

    function showLoading() {
        let submitButton = document.querySelector('#btnSubmit');
        submitButton.innerHTML =
            `<span class="spinner-border spinner-border-sm me-1 btn-info"></span> Checking Inventory...`;
        submitButton.disabled = true;
    }

    function onScanSuccess(decodedText) {
        document.getElementById('inventory_id').value = decodedText;
        document.getElementById('stoForm').submit();
        showLoading();
    }

    function onScanFailure(error) {
        console.warn(`Scan error: ${error}`);
    }
</script>
