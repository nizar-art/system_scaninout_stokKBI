@extends('layouts.app')

@section('title', 'Data Users')

@section('content')
    <div class="pagetitle animate__animated animate__fadeInLeft">
        <h1>Data Users</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Data Users</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->
    {{-- =============== alert ========================== --}}
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
    {{-- ================================== --}}

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title animate__animated animate__fadeInLeft">Data Users</h5>
                        <div class="mb-3">
                            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i
                                    class="bi bi-plus-square"></i> Create New User</a>
                        </div>

                        <div class="table-responsive animate__animated animate__fadeInUp">
                            <table class="table table-striped table-bordered datatable">
                                <thead class="thead-light">
                                    <tr>
                                        <th scope="col" class="text-center">NO</th>
                                        <th scope="col" class="text-center">Name</th>
                                        <th scope="col" class="text-center">Username</th>
                                        {{-- <th scope="col" class="text-center">Email</th> --}}
                                        <th scope="col" class="text-center">Role</th>
                                        <th scope="col" class="text-center">Nik</th>
                                        <th scope="col" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($users as $user)
                                        <tr>
                                            <td class="text-center">{{ $loop->iteration }}</td>
                                            <td class="text-center">{{ $user->first_name }} {{ $user->last_name ?? '-' }}
                                            </td>
                                            <td class="text-center">{{ $user->username }}</td>
                                            {{-- <td class="text-center">{{ $user->email ?? '-' }}</td> --}}
                                            <td class="text-center">
                                                @if ($user->roles && $user->roles->count())
                                                    {{ $user->roles->pluck('name')->implode(', ') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $user->nik ? $user->nik : '-' }}</td>
                                            <td class="text-center">
                                                <a href="{{ route('users.edit', $user->id) }}"
                                                    class="btn btn-success btn-sm">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <form action="{{ route('users.destroy', $user) }}" method="POST"
                                                    id="delete-form-{{ $user->id }}" style="display:inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" onclick="confirmDelete({{ $user->id }})"
                                                        class="btn btn-danger btn-sm">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
                                            </td>
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
    {{-- confirm delete --}}
    <script>
        function confirmDelete(userId) {
            Swal.fire({
                title: 'Apakah kamu yakin?',
                text: "Data ini akan dihapus secara permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal',
                width: '50%', // Atur lebar
                showClass: {
                    popup: 'animate__animated animate__jackInTheBox', // Animasi saat popup muncul
                    icon: 'animate__animated animate__shakeY' // Animasi pada ikon peringatan
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp' // Animasi saat popup menghilang
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-form-' + userId).submit();
                }
            });
        }
    </script>
@endsection
