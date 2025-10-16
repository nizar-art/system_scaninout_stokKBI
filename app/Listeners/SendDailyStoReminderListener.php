<?php

namespace App\Listeners;

use App\Events\DailyStoReminderEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SendDailyStoReminderListener
{
    public function handle(DailyStoReminderEvent $event)
    {
        $today = Carbon::today();
        
        // Skip weekend
        if ($today->isWeekend()) {
            Log::info('Broadcast import email SKIPPED (weekend)', ['date' => $today->toDateString()]);
            return;
        }
        
        // Skip hari libur nasional
        $response = Http::get('https://api-harilibur.vercel.app/api');
        if ($response->ok()) {
            $holidays = $response->json();
            $todayStr = $today->format('Y-m-d');
            $isNationalHoliday = collect($holidays)->first(function($item) use ($todayStr) {
                return isset($item['holiday_date'], $item['is_national_holiday']) &&
                    $item['holiday_date'] === $todayStr && 
                    $item['is_national_holiday'] === true;
            });
            if ($isNationalHoliday) {
                Log::info('Broadcast import email SKIPPED (Indonesian national holiday via API)', ['date' => $today->toDateString()]);
                return;
            }
        }

        $recipients = [
            // KBI 1 - Finished Good & WIP
            // [
            //     'categories' => ['Finished Good', 'WIP'],
            //     'plant_area' => 'KBI 1',
            //     'pic' => 'Herman P',
            //     'to' => 'planning01@kyoraku.co.id',
            //     'cc' => ['planning02@kyoraku.co.id','muhammad@kyoraku.co.id','ppic01@kyoraku.co.id','ismail@kyoraku.co.id'],
            // ],
            // // KBI 2 - Finished Good & WIP
            // [
            //     'categories' => ['Finished Good', 'WIP'],
            //     'plant_area' => 'KBI 2',
            //     'pic' => 'Yadi S',
            //     'to' => 'planning03@kyoraku.co.id',
            //     'cc' => ['planning02@kyoraku.co.id','muhammad@kyoraku.co.id','ppic01@kyoraku.co.id','ismail@kyoraku.co.id'],
            // ],
            // // KBI 1 - ChildPart, Raw Material, Packaging
            // [
            //     'categories' => ['ChildPart', 'Raw Material', 'Packaging'],
            //     'plant_area' => 'KBI 1',
            //     'pic' => 'Heriansyah',
            //     'to' => 'warehouse@kyoraku.co.id',
            //     'cc' => ['planning@kyoraku.co.id','muhammad@kyoraku.co.id','ppic01@kyoraku.co.id','ismail@kyoraku.co.id'],
            // ],
            // // KBI 2 - ChildPart, Raw Material, Packaging
            // [
            //     'categories' => ['ChildPart', 'Raw Material', 'Packaging'],
            //     'plant_area' => 'KBI 2',
            //     'pic' => 'Sungkono',
            //     'to' => 'warehouse2@kyoraku.co.id',
            //     'cc' => ['planning@kyoraku.co.id','muhammad@kyoraku.co.id','ppic01@kyoraku.co.id','ismail@kyoraku.co.id'],
            // ],
            // Testing
            [
                'categories' => ['Finished Good', 'WIP', 'Packaging', 'ChildPart', 'Raw Material'],
                'plant_area' => 'KBI 1',
                'pic' => 'Salman',
                'to' => 'salmanfauzi0512@gmail.com',
                'cc' => 'slmnstudy@gmail.com',
            ],
        ];
        
        $now = Carbon::now();
        $allowedHours = $event->allowedHours;
        
        // Tambahkan lock untuk mencegah multiple execution pada waktu yang berdekatan
        $lockKey = 'reminder_processing_lock_' . $today->toDateString() . '_' . $now->hour;
        if (cache()->has($lockKey)) {
            Log::info('Reminder: Proses pengiriman email masih berlangsung atau sudah selesai pada jam ini', [
                'hour' => $now->hour,
                'date' => $today->toDateString()
            ]);
            return;
        }
        
        // Set lock selama 10 menit untuk mencegah multiple execution
        cache()->put($lockKey, true, now()->addMinutes(10));
        
        try {
            foreach ($recipients as $rec) {
                $to = $rec['to'];
                $cc = $rec['cc'];
                $pic = $rec['pic'];
                $plantArea = $rec['plant_area'];
                $categories = $rec['categories'];
                
                // 1. Cek apakah sesuai jadwal (10,12,14,16,18)
                if (!in_array($now->hour, $allowedHours)) {
                    Log::info('Reminder: Bukan jam pengiriman', [
                        'current_time' => $now->toTimeString(), 
                        'allowed_hours' => $allowedHours
                    ]);
                    continue;
                }
                
                // Buat kunci untuk jam saat ini
                $hourlyLockKey = 'reminder_hourly_' . $to . '_' . $plantArea . '_' . $today->toDateString() . '_' . $now->hour;
                if (cache()->has($hourlyLockKey)) {
                    Log::info('Reminder: Email sudah dikirim pada jam ini, skip', [
                        'plant_area' => $plantArea,
                        'to' => $to,
                        'hour' => $now->hour,
                        'date' => $today->toDateString()
                    ]);
                    continue;
                }
                
                $cacheKeySendCount = 'reminder_send_count_' . $to . '_' . $plantArea . '_' . $today->toDateString();
                $sendCount = cache()->get($cacheKeySendCount, 0);
                
                // 2. Cek sudah 3x kirim hari ini?
                if ($sendCount >= 3) {
                    Log::info('Reminder: Sudah terkirim 3x hari ini, skip', [
                        'plant_area' => $plantArea,
                        'to' => $to,
                        'date' => $today->toDateString(),
                        'send_count' => $sendCount
                    ]);
                    continue;
                }
                
                // Cek waktu terakhir kali email dikirim untuk interval 2 jam
                $lastSentKey = 'reminder_last_sent_' . $to . '_' . $plantArea . '_' . $today->toDateString();
                $lastSentHour = cache()->get($lastSentKey);
                
                // Jika sudah pernah dikirim hari ini, periksa apakah sudah 2 jam sejak pengiriman terakhir
                if (!is_null($lastSentHour)) {
                    // Jika belum 2 jam dari pengiriman terakhir, skip
                    if ($now->hour - $lastSentHour < 2) {
                        Log::info('Reminder: Belum 2 jam dari pengiriman terakhir, skip', [
                            'plant_area' => $plantArea,
                            'to' => $to,
                            'last_sent' => $lastSentHour,
                            'current_hour' => $now->hour,
                            'date' => $today->toDateString()
                        ]);
                        continue;
                    }
                }
                
                // 4. Cek kategori yang belum di-upload (selalu cek status terbaru)
                $notUploadedCategories = [];
                
                foreach ($categories as $category) {
                    $uploaded = DB::table('tbl_daily_stock_logs as dsl')
                        ->join('tbl_head_area as ha', 'dsl.id_area_head', '=', 'ha.id')
                        ->join('tbl_plan as p', 'ha.id_plan', '=', 'p.id')
                        ->join('tbl_part as part', 'dsl.id_inventory', '=', 'part.id')
                        ->join('tbl_category as c', 'part.id_category', '=', 'c.id')
                        ->whereDate('dsl.created_at', $today)
                        ->where('c.name', $category)
                        ->where('p.name', $plantArea)
                        ->exists();
                    
                    if (!$uploaded) {
                        $notUploadedCategories[] = $category;
                    }
                }
                
                // 5. Kirim email jika ada kategori yang belum di-upload
                if (count($notUploadedCategories) > 0) {
                    // Lock untuk jam ini sebelum mengirim
                    cache()->put($hourlyLockKey, true, $today->copy()->addDay());
                    
                    $subject = '[Reminder !] Input Stock Alert - ' . $plantArea;
                    $categoryList = '<ul>';
                    foreach ($notUploadedCategories as $cat) {
                        $categoryList .= '<li><b>' . $cat . '</b></li>';
                    }
                    $categoryList .= '</ul>';
                    
                    // Tambahkan informasi jam pengiriman
                    // $reminderCountText = '';
                    // if ($sendCount > 0) {
                    //     $reminderCountText = "<br><span style='color: #ff0000; font-size: 13px;'>Ini adalah pengingat ke-" . ($sendCount + 1) . " hari ini</span>";
                    // }
                    
                    $content = "<div style='font-family: Arial, sans-serif; font-size: 15px;'>"
                        . "<b>Dear $pic,</b><br>"
                        . "Hari ini anda <b>masih belum menginformasikan stok quantity</b> untuk kategori berikut di <b>$plantArea</b>:<br>"
                        . $categoryList
                        . "Silahkan untuk segera melakukan Upload Stock pada aplikasi webApp STO."
                        // . $reminderCountText
                        . "<br><span style='font-size: 13px; color: #888;'>Jika ada kendala silakan hubungi <a href='mailto:muh.slmnfauzi@gmail.com'>muh.slmnfauzi@gmail.com</a></span><br>"
                        . "<span style='font-size: 13px; color: #888;'>Regards,<br>STO KBI - Administrator</span>"
                        . "</div>";
                    
                    try {
                        Mail::html($content, function ($message) use ($to, $cc, $subject) {
                            $message->to($to);
                            if (!empty($cc)) {
                                if (is_array($cc)) {
                                    $message->cc($cc);
                                } else {
                                    $message->cc([$cc]);
                                }
                            }
                            $message->subject($subject);
                            $message->from('administrator@jajaleun.com', 'Administrator System');
                        });
                        
                        // Set cache untuk jam terakhir email dikirim
                        cache()->put($lastSentKey, $now->hour, $today->copy()->addDay());
                        
                        // Update counter
                        cache()->put($cacheKeySendCount, $sendCount + 1, $today->copy()->addDay());
                        
                        Log::info('Reminder email sent', [
                            'plant_area' => $plantArea,
                            'to' => $to,
                            'cc' => $cc,
                            'pic' => $pic,
                            'categories' => $notUploadedCategories,
                            'date' => $today->toDateString(),
                            'jam' => $now->hour,
                            'send_count' => $sendCount + 1
                        ]);
                        
                    } catch (\Exception $e) {
                        Log::error('Gagal mengirim email reminder', [
                            'error' => $e->getMessage(),
                            'to' => $to,
                            'plant_area' => $plantArea
                        ]);
                        // Hapus lock jika gagal
                        cache()->forget($hourlyLockKey);
                    }
                } else {
                    Log::info('Semua kategori sudah diupload', [
                        'plant_area' => $plantArea,
                        'to' => $to,
                        'categories' => $categories
                    ]);
                    
                    // Reset counter jika semua kategori sudah diupload
                    cache()->forget($cacheKeySendCount);
                    cache()->forget($lastSentKey);
                }
            }
        } finally {
            // Hapus lock global setelah selesai, tapi set waktu berlaku lock (untuk mencegah multiple execution dalam jam yang sama)
            // Biarkan tetap terkunci selama sisa jam ini, tapi dengan catatan bahwa proses telah selesai
            cache()->put($lockKey, 'completed', now()->addMinutes(60 - $now->minute));
        }
    }
}