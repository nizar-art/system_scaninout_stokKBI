@extends('layouts.app')

@section('title', 'Edit Data User')

@section('content')
    <div class="pagetitle">
        <h1>Edit User</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Data User</a></li>
                <li class="breadcrumb-item active">Edit User</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->
    {{-- ========== alert ========== --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    {{-- ===================== --}}
    <section class="section">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Edit User</h5>
                <form class="row g-3 needs-validation" novalidate method="POST"
                    action="{{ route('users.update', $user->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="col-md-4">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username"
                            class="form-control @error('username') is-invalid @enderror"
                            value="{{ old('username', $user->username) }}" placeholder="Silahkan inputkan username">
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" name="first_name" id="first_name"
                            class="form-control @error('first_name') is-invalid @enderror"
                            value="{{ old('first_name', $user->first_name) }}" placeholder="Input nama depan">
                        @error('first_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="last_name"
                            class="form-control @error('last_name') is-invalid @enderror"
                            value="{{ old('last_name', $user->last_name) }}" placeholder="Input nama belakang">
                        @error('last_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="ID-card" class="form-label">ID Card</label>
                        <input type="text" name="ID-card" id="ID-card"
                            class="form-control @error('ID-card') is-invalid @enderror"
                            value="{{ old('ID-card', $user->nik) }}" placeholder="Silahkan inputkan ID-card">
                        @error('ID-card')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="yourPassword" class="form-label">Password</label>
                        <div class="input-group has-validation">
                            <input type="password" name="password"
                                class="form-control @error('password') is-invalid @enderror" id="yourPassword">
                            <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                            </button>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <small class="text-muted">*Biarkan kosong jika tidak ingin mengganti</small>
                    </div>

                    {{-- <div class="col-md-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email"
                            class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}"
                            placeholder="Silahkan inputkan email">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div> --}}

                    <div class="col-md-3">
                        <label class="form-label">Role</label>
                        <div>
                            @foreach ($roles as $role)
                                @php
                                    $userHasRole = $user->roles->pluck('name')->contains($role->name);
                                @endphp
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="role[]"
                                        id="role_{{ $role->id }}" value="{{ $role->name }}"
                                        {{ $userHasRole ? 'checked disabled' : (collect(old('role', []))->contains($role->name) ? 'checked' : '') }}>
                                    <label class="form-check-label" for="role_{{ $role->id }}">
                                        {{ ucfirst($role->name) }}
                                        @if ($userHasRole)
                                            <span class="badge bg-secondary">sudah dimiliki</span>
                                        @endif
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted">*Centang untuk menambah role baru. Role yang sudah dimiliki tidak bisa
                            diubah.</small>
                        @error('role')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-6">
                        <button class="btn btn-primary" type="submit">Update User</button>
                    </div>
                </form>

            </div>
        </div>
    </section>

    {{-- js hidden+show PW --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const idCardInput = document.querySelector('input[name="ID-card"]');
            const passwordInput = document.getElementById('yourPassword');

            idCardInput.addEventListener('input', function() {
                passwordInput.value = idCardInput.value;
            });

            // Toggle Password Visibility
            document.getElementById('togglePassword').addEventListener('click', function() {
                const passwordInput = document.getElementById('yourPassword');
                const passwordIcon = document.getElementById('togglePasswordIcon');

                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    passwordIcon.classList.remove('bi-eye-slash');
                    passwordIcon.classList.add('bi-eye');
                } else {
                    passwordInput.type = 'password';
                    passwordIcon.classList.remove('bi-eye');
                    passwordIcon.classList.add('bi-eye-slash');
                }
            });
        });
    </script>
@endsection
