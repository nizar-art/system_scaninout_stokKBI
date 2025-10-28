
<nav class="navbar navbar-form shadow-sm fixed-top">
    <div class="container-fluid d-flex justify-content-between align-items-center px4">

        <div class="d-flex align-items-center" style="margin-top: -8px;">
            <a href="{{ route('dashboardinout.index') }}" class="text-white text-decoration-none d-flex align-items-center">
                <img src="{{ asset('assets/img/icon-kbi.png') }}" alt="Logo" 
                    style="height: 35px; width: auto; margin-right: 10px;">
                <h5 class="m-0 fw-bold text-white d-none d-md-block">PT Kyoraku Blowmolding Indonesia</h5>
                <h5 class="m-0 fw-bold text-white d-block d-md-none">PT Kyoraku</h5>
            </a>
        </div>


        {{-- Kanan: User info + Hamburger --}}
        <div class="d-flex align-items-center " style="margin-top: -8px;">
            <div class="dropdown me-3 d-none d-md-block">
                <a class="text-white dropdown-toggle" href="#" id="userDropdown"
                data-bs-toggle="dropdown" aria-expanded="false">
                    <small><i class="fas fa-user me-1 text-success"></i> {{ Auth::user()->first_name ?? 'Guest' }}</small>
                </a>
                <ul class="dropdown-menu dropdown-menu-end bg-dark text-white" aria-labelledby="userDropdown">
                    <li>
                        <form action="{{ route('logout.user') }}" method="POST">@csrf
                            <button type="submit" class="dropdown-item text-white bg-dark">Log Out</button>
                        </form>
                    </li>
                </ul>
            </div>

            {{-- Hamburger menu (HP) --}}
            <button class="btn text-white d-md-none" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
                <i class="fas fa-bars fa-lg"></i>
            </button>
        </div>
    </div>
</nav>

{{-- MENU BAWAH UNTUK DESKTOP --}}
<div class="shadow-sm desktop-menu fixed-top" style="top: 56px; background-color: #032950;">
    <div class="container-fluid d-flex justify-content-start align-items-center" style="gap: 20px; padding: 8px 35px;">
        <a href="{{ route('dashboardinout.index') }}" class="text-white text-decoration-none">
            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
        </a>
        <a href="{{ route('ImportIn.index')}}" class="text-white text-decoration-none">
            <i class="fas fa-boxes me-1"></i> Import In Stok
        </a>
        <a href="{{ route('historyscan.index')}}" class="text-white text-decoration-none">
            <i class="fas fa-history me-1"></i> History Transaksi
        </a>
    </div>
</div>

{{-- SIDEBAR (OFFCANVAS) UNTUK HP --}}
<div class="offcanvas offcanvas-end mobile-sidebar" tabindex="-1" id="mobileSidebar"
     aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header border-bottom border-light">
        <h5 class="offcanvas-title d-flex align-items-center text-white" id="mobileSidebarLabel">
            <img src="{{ asset('assets/img/icon-kbi.png') }}" alt="Logo" 
                 style="height: 28px; width: auto; margin-right: 10px;">
            <span>Scan In&Out</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body text-white">
        <ul class="list-unstyled mb-0">
            <li class="mb-3">
                <a href="{{ route('dashboardinout.index') }}" class="text-white text-decoration-none d-flex align-items-center">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            <li class="mb-3">
                <a href="{{ route('ImportIn.index')}}" class="text-white text-decoration-none d-flex align-items-center">
                    <i class="fas fa-boxes me-2"></i> Import In Stok
                </a>
            </li>
            <li class="mb-3">
                <a href="{{ route('historyscan.index')}}" class="text-white text-decoration-none d-flex align-items-center">
                    <i class="fas fa-history me-2"></i> History Transaksi
                </a>
            </li>
            <li class="border-top border-light pt-3 mt-3">
                <form action="{{ route('logout.user') }}" method="POST">@csrf
                    <button type="submit" class="btn btn-outline-light w-100 d-flex align-items-center justify-content-center">
                        <i class="fas fa-sign-out-alt me-2"></i> Log Out
                    </button>
                </form>
            </li>
        </ul>
    </div>
</div>

<style>
/* ===== NAVBAR ===== */
.navbar {
    background-color: #2e3e4e;
    color: white;
    height: 56px;
    z-index: 1050;
}

/* ===== MENU BAWAH (DESKTOP) ===== */
.desktop-menu {
    z-index: 1040;
}

/* ===== MOBILE RESPONSIVE ===== */
@media (max-width: 768px) {
    .desktop-menu {
        display: none;
    }

    .btn[data-bs-toggle="offcanvas"] {
        display: inline-block;
    }
}

/* ===== CUSTOM SIDEBAR ===== */
.mobile-sidebar {
    background-color: #032950 !important;
    color: #fff !important;
    border-left: 2px solid #013070 !important;
}

/* ===== KONTEN DI BAWAH NAVBAR ===== */
body {
    padding-top: 100px; /* beri jarak biar konten ga ketimpa navbar/menu */
}
</style>