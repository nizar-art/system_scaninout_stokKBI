<aside id="sidebar" class="sidebar hiden">
    <ul class="sidebar-nav" id="sidebar-nav">
        @if (Auth::user()->roles->pluck('name')->contains(function ($role) {
                    return in_array($role, ['SuperAdmin', 'admin', 'view']);
                }))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : 'collapsed' }}"
                    href="{{ route('dashboard') }}">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('forecast.index', 'forecast.create') ? 'active' : 'collapsed' }}"
                    href="{{ route('forecast.index') }}">
                    <i class="bi bi-graph-up"></i>
                    <span>Forecast</span>
                </a>
            </li>

            <li class="nav-heading">Inventory List</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('sto.index', 'sto.create.get', 'sto.edit') ? 'active' : 'collapsed' }}"
                    href="{{ route('sto.index') }}">
                    <i class="bi bi-box-seam-fill"></i>
                    <span>List STO</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('daily-stock.index', 'reports.edit') ? 'active' : 'collapsed' }}"
                    href="{{ route('daily-stock.index') }}">
                    <i class="bi bi-clipboard-fill"></i>
                    <span>Daily Stock</span>
                </a>
            </li>
        @endif
        @if (Auth::user()->roles->pluck('name')->contains(function ($role) {
                    return in_array($role, ['SuperAdmin', 'admin']);
                }))
            <li class="nav-heading">Master Data</li>
            <li class="nav-item">
                <!-- Main "Parts" Menu (always active if any parts.* route) -->
                <a class="nav-link {{ request()->routeIs('parts.*') ? 'active' : 'collapsed' }}"
                    data-bs-target="#charts-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-archive"></i><span>Parts</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>

                <!-- Sub-Menu Dropdown (show if any parts.* route) -->
                <ul id="charts-nav" class="nav-content collapse {{ request()->routeIs('parts.*') ? 'show' : '' }}"
                    data-bs-parent="#sidebar-nav">

                    <!-- By ALL (Active for parts.index & parts.edit) -->
                    <li>
                        <a href="{{ route('parts.index') }}"
                            class="{{ request()->routeIs('parts.index', 'parts.edit') ? 'active' : '' }}">
                            <i class="bi bi-circle"></i><span>By ALL</span>
                        </a>
                    </li>

                    <!-- By Customer (Active for parts.index.customer) -->
                    <li>
                        <a href="{{ route('parts.index.customer') }}"
                            class="{{ request()->routeIs('parts.index.customer') ? 'active' : '' }}">
                            <i class="bi bi-circle"></i><span>By Customer</span>
                        </a>
                    </li>

                    <!-- By Supplier (Active for parts.index.supplier) -->
                    <li>
                        <a href="{{ route('parts.index.supplier') }}"
                            class="{{ request()->routeIs('parts.index.supplier') ? 'active' : '' }}">
                            <i class="bi bi-circle"></i><span>By Supplier</span>
                        </a>
                    </li>
                </ul>
            </li>
            {{-- BOM --}}
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('bom.index') ? 'active' : 'collapsed' }}"
                    href="{{ route('bom.index') }}">
                    <i class="bi bi-card-checklist"></i>
                    <span>BOM</span>
                </a>
            </li>
            {{-- <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('workdays.index', 'workdays.edit') ? 'active' : 'collapsed' }}"
                    href="{{ route('workdays.index') }}">
                    <i class="bi bi-calendar3"></i>
                    <span>Working Days</span>
                </a>
            </li> --}}
        @endif

        @if (Auth::user()->roles->pluck('name')->contains('SuperAdmin'))
            <li class="nav-heading">User Management</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('users.index', 'users.create', 'users.edit') ? 'active' : 'collapsed' }}"
                    href="{{ route('users.index') }}">
                    <i class="bi bi-people-fill"></i>
                    <span>User</span>
                </a>
            </li>
        @endif

        <li class="nav-heading">Auth</li>
        <li class="nav-item">
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
            <a class="nav-link collapsed" href="#" onclick="logoutConfirm()">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>

<!-- End Sidebar-->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function logoutConfirm() {
        Swal.fire({
            title: 'Anda yakin ingin logout?',
            text: "Anda akan keluar dari sesi ini.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, logout!',
            cancelButtonText: 'Batal',
            showClass: {
                popup: 'animate__animated animate__jackInTheBox' // Animasi saat muncul
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp' // Animasi saat menghilang
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('logout-form').submit();
            }
        });
    }
</script>
