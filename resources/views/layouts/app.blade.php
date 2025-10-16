<!DOCTYPE html>
<html lang="id">
@include('layouts.head')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<body>
    @include('layouts.header')

    @include('layouts.sidebar')
    {{-- content body --}}
    <main id="main" class="main">
        @yield('content')
    </main>
    {{-- end conten body --}}
    @include('layouts.footer')
    {{-- ================= --}}
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
            class="bi bi-arrow-up-short"></i></a>
    {{-- ========= --}}
    <!-- Replace the existing logout form/link with this properly configured form -->
    {{-- <form method="POST" action="{{ route('logout') }}" id="logout-form" style="display: none;">
        @csrf
    </form>
    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
        class="nav-link">
        <i class="nav-icon fas fa-sign-out-alt"></i>
        <p>Logout</p>
    </a> --}}
</body>

</html>
