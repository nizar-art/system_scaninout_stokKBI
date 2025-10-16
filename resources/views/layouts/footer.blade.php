<footer id="footer" class="footer">
    <div class="copyright" style="text-align: center">
        <strong><span> &copy;</span></strong> Sto Management System
    </div>
</footer>
<!-- End Footer -->


<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" defer></script>
<!-- Template Main JS File -->
<script src="{{ asset('assets/js/main.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js" defer></script>

{{-- sweetalert login Berhasil --}}
<script>
    @if (session('login-sukses'))
        Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: '{!! session('login-sukses') !!}',
            // timer: 1500,
            timerProgressBar: true,
            showClass: {
                popup: 'animate__animated animate__bounceInDown' // Menambahkan animasi muncul
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp' // Menambahkan animasi saat ditutup
            },
        });
    @endif
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
<!-- Vendor JS Files -->
<script src="{{ asset('assets/vendor/apexcharts/apexcharts.min.js') }}" defer></script>
<script src="{{ asset('assets/vendor/chart.js/chart.umd.js') }}" defer></script>
<script src="{{ asset('assets/vendor/echarts/echarts.min.js') }}" defer></script>

<script>
    $(document).ready(function() {
        $('.datatable').DataTable();
    });
</script>

<script>
    setInterval(() => {
        fetch("{{ route('keep-alive') }}", {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => console.log(data))
            .catch(error => console.error('Error:', error));
    }, 5 * 60 * 1000); // setiap 5 menit
</script>
