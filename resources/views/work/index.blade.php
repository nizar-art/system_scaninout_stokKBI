@extends('layouts.app')

@section('title', 'Work Days')

@section('content')
    <div class="pagetitle animate__animated animate__fadeInLeft">
        <h1>Work Days</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Work Days</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->
    {{-- =================alert ================ --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            {{ session('error') }}
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
    @if (session('import_logs'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <strong>Detail Workdays:</strong>
            <ul>
                @foreach (session('import_logs') as $log)
                    <li>{{ $log }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    {{-- ============================ --}}

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title animate__animated animate__fadeInLeft">
                            Work Days Data {{ now()->year }}
                        </h5>

                        <div class="mb-2">
                            @if (Auth::user()->roles->pluck('name')->intersect(['SuperAdmin'])->isNotEmpty())
                                <button type="button" class="btn btn-success btn-sm  mb-1 " data-bs-toggle="modal"
                                    data-bs-target="#importModal">
                                    <i class="bi bi-file-earmark-spreadsheet-fill"></i> Import Excel
                                </button>
                            @endif
                        </div>
                        {{-- =========info update========= --}}
                        @php
                            $currentYear = now()->year;

                            $fromLastYear = $workDays->filter(
                                fn($wd) => \Carbon\Carbon::parse($wd->updated_at)->year < $currentYear,
                            );
                            $fromCurrentYear = $workDays->filter(
                                fn($wd) => \Carbon\Carbon::parse($wd->updated_at)->year == $currentYear,
                            );

                            $isAllFromLastYear = $fromCurrentYear->isEmpty() && $fromLastYear->isNotEmpty();
                            $isPartialFromLastYear = $fromCurrentYear->isNotEmpty() && $fromLastYear->isNotEmpty();

                            // Ambil daftar bulan dari data tahun sebelumnya
                            $bulanLama = $fromLastYear
                                ->map(fn($w) => \Carbon\Carbon::parse($w->month)->translatedFormat('F'))
                                ->unique()
                                ->implode(', ');
                            $tahunLama = $fromLastYear->first()?->updated_at->format('Y');
                        @endphp

                        @if ($workDays->isEmpty())
                            <div class="alert alert-warning">
                                <strong>Warning!</strong> Data work days belum tersedia.
                            </div>
                        @elseif ($isAllFromLastYear)
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                                <ul>
                                    <strong>Info!</strong> Seluruh data work days yang ditampilkan masih berasal dari tahun
                                    {{ $tahunLama }}.
                                </ul>
                            </div>
                        @elseif ($isPartialFromLastYear)
                            <div class="alert alert-info alert-dismissible fade show" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                                <ul>
                                    <strong>Info!</strong> Beberapa data work days masih menggunakan data dari tahun
                                    {{ $tahunLama }} untuk bulan:
                                    {{ $bulanLama }}.
                                </ul>
                            </div>
                        @endif


                        {{-- =================== --}}

                        <div class="table-responsive animate__animated animate__fadeInUp">
                            <table class="table table-striped table-bordered datatable">
                                <thead>
                                    <tr>
                                        <th class="text-center">No</th>
                                        <th class="text-center">Month</th>
                                        <th class="text-center">Work Day</th>
                                        @if (Auth::user()->roles->pluck('name')->intersect(['SuperAdmin'])->isNotEmpty())
                                            <th class="text-center">Action</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($workDays as $index => $workDay)
                                        <tr>
                                            <td class="text-center">{{ $index + 1 }}</td>
                                            <td class="text-center">
                                                {{ $workDay->month ? \Carbon\Carbon::parse($workDay->month)->translatedFormat('F') : '-' }}
                                            </td>
                                            <td class="text-center">{{ $workDay->hari_kerja }}</td>
                                            @if (Auth::user()->roles->pluck('name')->intersect(['SuperAdmin'])->isNotEmpty())
                                                <td class="text-center">
                                                    <a href="{{ route('workdays.edit', $workDay->id) }}"
                                                        class="btn btn-warning btn-sm"
                                                        style="font-size: 0.875rem; padding: 4px 8px;">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- modal import Excel --}}
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Work Days from Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('workdays.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="file" class="form-label">Upload Excel File</label>
                            <input type="file" name="file" class="form-control" id="file" required
                                accept=".xls,.xlsx">
                            <small class="text-danger">*Download Template Excel Import: <a
                                    href="{{ asset('file/template_workdays_import.xlsx') }}" download> <i
                                        class="bi bi-download"></i> klik di sini</a></small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success">Import</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    {{-- end --}}
@endsection
