@extends('layouts.app')

@section('title', 'Update Work Days')

@section('content')
    <div class="pagetitle">
        <h1>Update Work Days</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('workdays.index') }}">Work Days</a></li>
                <li class="breadcrumb-item active">Update Work Days</li>
            </ol>
        </nav>
    </div>

    <section class="section">
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

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Form Update Work Days</h5>

                <form action="{{ route('workdays.update', $workDay->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="month" class="form-label">Bulan</label>
                        <input type="month" name="month" id="month" class="form-control"
                            value="{{ old('month', \Carbon\Carbon::parse($workDay->month)->format('Y-m')) }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="hari_kerja" class="form-label">Hari Kerja</label>
                        <input type="number" name="hari_kerja" id="hari_kerja" class="form-control"
                            value="{{ old('hari_kerja', $workDay->hari_kerja) }}" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('workdays.index') }}" class="btn btn-secondary">Kembali</a>
                </form>
            </div>
        </div>
    </section>
@endsection
