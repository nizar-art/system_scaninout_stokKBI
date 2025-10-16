    <!DOCTYPE html>
    <html lang="id">
    @include('layouts.head_user')

    <body>
        <section class="section">
            @include('layouts.navbar_user')

            {{-- alert --}}
            @if (session('notfound'))
                <div class="container mt-3">
                    <div id="alertWarning" class="alert alert-warning alert-dismissible fade show" role="alert">
                        {{ session('notfound') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            @endif
            {{-- berhasil di simpan --}}
            @if (session('berhasil'))
                <audio id="alertAudio" src="{{ asset('sounds/sukses.mp3') }}" preload="auto"></audio>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    {{ session('berhasil') }}
                </div>
                <script>
                    function playAlertSound() {
                        const audio = document.getElementById('alertAudio');
                        audio.currentTime = 0; // Rewind ke awal
                        audio.play().catch(e => console.log('Audio play failed:', e));
                    }
                </script>
            @endif

            @if ($errors->any())
                <div class="container mt-3">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Terjadi kesalahan:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            @endif


            @if (session('error'))
                <audio id="errorSound" src="{{ asset('sounds/error.mp3') }}" preload="auto"></audio>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <script>
                    // Fungsi untuk memutar suara error
                    function playErrorSound() {
                        try {
                            const audio = document.getElementById('errorSound');
                            if (audio) {
                                audio.currentTime = 0; // Reset ke awal
                                audio.play().catch(e => console.error('Audio error:', e));
                            }
                        } catch (e) {
                            console.error('Sound play failed:', e);
                        }
                    }

                    Swal.fire({
                        toast: true,
                        position: "top-end",
                        icon: "error",
                        title: "{{ session('error') }}",
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        willOpen: () => {
                            playErrorSound(); // Mainkan sebelum alert muncul
                        },
                        didOpen: () => {
                            // Backup jika willOpen tidak bekerja
                            playErrorSound();
                        }
                    });
                </script>
            @endif
            {{-- === --}}

            {{-- content --}}
            @yield('contents')

            <div class="credits" style="margin-top: 20px; text-align: center;">
                &copy; Sto Management System
            </div>
        </section>
        @include('layouts.footer_user')

        @stack('scripts')

    </body>

    </html>
