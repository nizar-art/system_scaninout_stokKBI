@extends('layouts.app_userinout')

@section('title', 'Scan In&Out Stock')

@section('contents')
<div class="container d-flex flex-column justify-content-center align-items-center" style="min-height: 80vh;">
    <!-- Judul -->
    <div class="text-center mb-5">
        <h1 class="fw-bold text-white">Scan QRCode Stock</h1>
        <p class="text-white fs-4">Silakan pilih menu di bawah ini</p>
    </div>
    <!-- Menu Cards -->
    <div class="row g-4 text-center w-100 justify-content-center">
        <!-- In Stock -->
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('scanInStok.index') }}" class="text-decoration-none">
                <div class="card shadow-lg border-0 h-100 bg-primary">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center p-5">
                        <i class="bi bi-box-seam display-1 text-white mb-3"></i>
                        <h3 class="fw-bold text-white">In Stock</h3>
                    </div>
                </div>
            </a>
        </div>
        <!-- Out Stock -->
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('scanOutStok.index') }}" class="text-decoration-none">
                <div class="card shadow-lg border-0 h-100 bg-danger">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center p-5">
                        <i class="bi bi-box-arrow-up display-1 text-white mb-3"></i>
                        <h3 class="fw-bold text-white">Out Stock</h3>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection
