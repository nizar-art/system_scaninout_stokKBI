   <nav class="navbar navbar-form shadow-sm">
       <div class="container container-fluid">
           <div class="d-flex flex-column align-items-start">
               <div class="d-flex align-items-center">
                   <a href="{{ route('scanInStok.index') }}" class="text-white">
                       <h5> <i class="fas fa-clipboard-check me-2"></i><strong>Scan In Stock </strong></h5>
                   </a>
               </div>
               @if (isset($inventory))
                   @php
                       $invId = $inventory->Inv_id ?? null;

                       // Hitung jumlah customer unik yg pakai inv_id ini
                       $customerCount = \App\Models\Part::where('Inv_id', $invId)
                           ->distinct('id_customer')
                           ->count('id_customer');

                       // Cek apakah sedang di halaman scan
                       $isScanPage = Request::is('daily-report/scan');
                   @endphp

                   {{-- Inventory ID tetap ditampilkan --}}
                   <p class="colom mt-1" style="font-size: 15px; margin-bottom: -1px; color:rgb(255, 255, 255);">
                       <i class="fas fa-file-invoice"></i>&nbsp;&nbsp;Inventory ID&nbsp;:&nbsp;
                       <strong
                           style="width: 5px; font-size: 15px; color:rgb(255, 225, 0); padding: 1px; text-transform: uppercase;">
                           {{ $invId ?? '-' }}
                       </strong>
                   </p>

                   {{-- Customer hanya tampil jika:
                            - Tidak sedang di halaman scan
                            - Atau, jumlah customer hanya 1
                            --}}
               @endif

           </div>
           <div class="d-flex flex-column align-items-end">
               <div class="dropdown">
                   <div class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userDropdown"
                       data-bs-toggle="dropdown" aria-expanded="false" role="button">
                       <small class="d-block" style="font-size: 13px;">
                           <i class="fas fa-user me-1" style="color:#1abc9c;"></i>
                           {{ Auth::user()->first_name ?? 'Guest' }}
                       </small>
                   </div>
                   <ul class="dropdown-menu dropdown-menu-end bg-dark text-white" aria-labelledby="userDropdown">
                       <li>
                           <form action="{{ route('logout.user') }}" method="POST" id="logoutForm">
                               @csrf
                               <button type="button" class="dropdown-item text-white bg-dark"
                                   onclick="confirmLogout()">Log Out</button>
                           </form>
                       </li>
                   </ul>
               </div>
           </div>

       </div>
   </nav>
