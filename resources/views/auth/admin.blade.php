    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="utf-8">
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">

        <title>Login Admin</title>
        <meta content="" name="description">
        <meta content="" name="keywords">

        <!-- Favicons -->
        <link rel="icon" href="{{ asset('assets/img/icon-kbi.png') }}" loading="lazy" alt="logo"
            type="image/png">
        <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

        <!-- Google Fonts -->
        <link href="https://fonts.gstatic.com" rel="preconnect">
        <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">

        <link href="{{ asset('assets/vendor/font-awesome/css/all.min.css') }}" rel="stylesheet">

        <!-- Vendor CSS Files -->
        <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/vendor/boxicons/css/boxicons.min.css') }}" rel="stylesheet">

        <!-- Template Main CSS File -->
        <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/css/custom-auth.css') }}" rel="stylesheet">


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
                                    <img src="{{ asset('assets/img/kyoraku-baru.png') }}" loading="lazy"
                                        alt="Logo Kyoraku" class="logo-auth">
                                </div>
                                <!-- End Logo -->

                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="pt-4 pb-2">
                                            <h5 class="card-title text-center pb-0 fs-4">Login To Admin</h5>
                                            <p class="text-center small">Enter your username & password to login</p>
                                        </div>

                                        <form class="row g-3" novalidate action="{{ route('admin.login.post') }}"
                                            method="POST">
                                            @csrf
                                            <div class="col-12">
                                                <label for="yourUsername" class="form-label">Username</label>
                                                <div class="input-group has-validation">
                                                    <span class="input-group-text" id="inputGroupPrepend">
                                                        <i class="bi bi-person"></i>
                                                    </span>
                                                    <input type="text" name="username"
                                                        class="form-control @error('username') is-invalid @enderror"
                                                        id="yourUsername" value="{{ old('username') }}"
                                                        autocomplete="off">
                                                    @error('username')
                                                        <div class="invalid-feedback">
                                                            {{ $message }}
                                                        </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <label for="yourPassword" class="form-label">Password</label>
                                                <div class="input-group has-validation">
                                                    <span class="input-group-text" id="inputGroupPrepend">
                                                        <i class="bi bi-key"></i>
                                                    </span>
                                                    <input type="password" name="password"
                                                        class="form-control @error('password') is-invalid @enderror"
                                                        id="yourPassword" autocomplete="off">
                                                    <span class="input-group-text" id="togglePassword">
                                                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                                    </span>
                                                    @error('password')
                                                        <div class="invalid-feedback">
                                                            {{ $message }}
                                                        </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <button type="submit" class="btn btn-primary fw-bold w-100"
                                                    style="font-size: 0.875rem; padding: 4px 8px;">Login</button>
                                            </div>

                                            <div class="col-12">
                                                <a href="{{ route('user.login') }}"
                                                    class="btn btn-outline-secondary w-100"
                                                    style="font-size: 0.875rem; padding: 4px 8px;">
                                                    <i class="bi bi-box-arrow-in-right"></i> Login User
                                                </a>
                                            </div>
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
        </main><!-- End #main -->

        <!-- Vendor JS Files -->
        <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}" defer></script>

        <!-- Template Main JS File -->
        <script src="{{ asset('assets/js/main.js') }}" defer></script>

        <!-- SweetAlert JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>
            const togglePassword = document.querySelector('#togglePassword');
            const password = document.querySelector('#yourPassword');
            const togglePasswordIcon = document.querySelector('#togglePasswordIcon');

            togglePassword.addEventListener('click', function(e) {
                // toggle the type attribute
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);

                // toggle the eye / eye-slash icon
                togglePasswordIcon.classList.toggle('bi-eye');
                togglePasswordIcon.classList.toggle('bi-eye-slash');
            });
        </script>

        @if (session('warning'))
            <script>
                Swal.fire({
                    icon: 'warning',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown' // Menambahkan animasi muncul
                    },
                    title: 'Gagal Login',
                    text: '{{ session('warning') }}',
                });
            </script>
        @endif


        @if (session('success.logout'))
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

    </body>

    </html>
