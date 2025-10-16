<style>
    .avatar-container {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .avatar-container img {
        object-fit: cover;
        border: 2px solid #d9d4d4;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .avatar-text {
        width: 39px;
        height: 39px;
        border-radius: 50%;
        background-color: #6c757d;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
</style>
<?php
use Laravolt\Avatar\Facade as Avatar;
?>
<header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
        <div class="logo d-flex align-items-center">
            <span class="d-none d-lg-block"><strong>Kyoraku Blowmolding Indonesia</strong></span>
        </div>
        <i class="bi bi-list toggle-sidebar-btn"></i>
    </div><!-- End Logo -->

    <nav class="header-nav ms-auto">
        <ul class="d-flex align-items-center">
            @auth
                <li class="nav-item pe-3">
                    <div class="avatar-container">
                        @php
                            try {
                                $avatarImage = Avatar::create(auth()->user()->username)->toBase64();
                            } catch (\Exception $e) {
                                $avatarImage = null;
                            }
                        @endphp

                        @if ($avatarImage)
                            <img src="{{ $avatarImage }}" alt="Profile" class="rounded-circle" width="39"
                                height="39">
                        @else
                            <div class="avatar-text">
                                {{ substr(auth()->user()->username, 0, 1) }}
                            </div>
                        @endif
                        <span class="d-none d-md-block ps-0">
                            {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
                        </span>
                    </div>
                </li>
            @endauth
        </ul>
    </nav>
</header><!-- End Header -->
