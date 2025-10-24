<!-- ======= Sidebar (Bawah Navbar) ======= -->
<div class="sub-navbar bg-light border-top py-2 shadow-sm">
  <div class="container-fluid d-flex align-items-center gap-3 px-4">
    <!-- Menu 1 -->
    <a href="#" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2">
      <i class="bi bi-grid-1x2-fill"></i>
      <span>Dashboard</span>
    </a>

    <!-- Menu 2 -->
    <a href="#" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2">
      <i class="bi bi-graph-up"></i>
      <span>Forecast</span>
    </a>
  </div>
</div>

<!-- Optional CSS -->
<style>
  .sub-navbar {
    position: sticky;
    top: 56px; /* Sesuaikan dengan tinggi navbar kamu */
    z-index: 1020;
    background-color: #fff;
  }

  .sub-navbar a {
    transition: all 0.2s ease-in-out;
  }

  .sub-navbar a:hover,
  .sub-navbar a.active {
    background-color: #7367f0;
    color: #fff !important;
    border-color: #7367f0;
  }
</style>
