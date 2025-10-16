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
        // Skip jika hari Sabtu/Minggu
        if ($today->isWeekend()) {
            Log::info('Broadcast import email SKIPPED (weekend)', ['date' => $today->toDateString()]);
            return;
        }
        // Skip jika tanggal merah nasional Indonesia (pakai API)
        $response = Http::get('https://api-harilibur.vercel.app/api');
        if ($response->ok()) {
            $holidays = $response->json();
            $todayStr = $today->format('Y-m-d');
            $isNationalHoliday = collect($holidays)->first(function($item) use ($todayStr) {
                return isset($item['holiday_date'], $item['is_national_holiday']) &&
                    $item['holiday_date'] === $todayStr && $item['is_national_holiday'] === true;
            });
            if ($isNationalHoliday) {
                Log::info('Broadcast import email SKIPPED (Indonesian national holiday via API)', ['date' => $today->toDateString()]);
                return;
            }
        }

        $recipients = [
            // testing
            [
                'categories' => ['Finished Good', 'WIP', 'Packaging', 'ChildPart', 'Raw Material'],
                'plant_area' => 'KBI 1',
                'pic' => 'Salman',
                'to' => 'salmanfauzi0512@gmail.com',
                'cc' => 'slmnstudy@gmail.com',
            ],
            // end testing
            // [
            //     'categories' => ['ChildPart', 'Raw Material', 'Packaging'],
            //     'plant_area' => 'KBI 1',
            //     'pic' => 'Herriansyah',
            //     'to' => 'warehouse@kyoraku.co.id',
            //     'cc' => ['planning@kyoraku.co.id','muhammad@kyoraku.co.id','ppic01@kyoraku.co.id','ismail@kyoraku.co.id'],
            // ],
            // [
            //     'categories' => ['ChildPart', 'Raw Material', 'Packaging'],
            //     'plant_area' => 'KBI 2',
            //     'pic' => 'Sungkono',
            //     'to' => 'warehouse2@kyoraku.co.id',
            //     'cc' => ['planning@kyoraku.co.id','muhammad@kyoraku.co.id','ppic01@kyoraku.co.id','ismail@kyoraku.co.id'],
            // ],
            // [
            //     'categories' => ['Finished Good', 'WIP'],
            //     'plant_area' => 'KBI 1',
            //     'pic' => 'Herman P',
            //     'to' => 'planning01@kyoraku.co.id',
            //     'cc' => ['planning02@kyoraku.co.id','muhammad@kyoraku.co.id','ppic01@kyoraku.co.id','ismail@kyoraku.co.id'],
            // ],
            // [
            //     'categories' => ['Finished Good', 'WIP'],
            //     'plant_area' => 'KBI 2',
            //     'pic' => 'Yadi S',
            //     'to' => 'planning03@kyoraku.co.id',
            //     'cc' => ['planning02@kyoraku.co.id','muhammad@kyoraku.co.id','ppic01@kyoraku.co.id','ismail@kyoraku.co.id'],
            // ],
        ];
      
        $today = Carbon::today();
        foreach ($recipients as $rec) {
            $to = $rec['to'] ?? $rec['cc'];
            $cc = $rec['to'] ? $rec['cc'] : null;
            $pic = $rec['pic'] ?? $rec['pic'];
            $plantArea = $rec['plant_area'];
            $now = Carbon::now();
            $allowedHours = [10, 12, 14, 16, 18]; // Only these hours

            if (!in_array($now->hour, $allowedHours) || $now->minute !== 0) {
                Log::info('Reminder: Bukan jam pengiriman ', ['current_time' => $now->toTimeString(), 'allowed_hours' => $allowedHours]);
                return;
            }

            // Tambahan: Batasi pengiriman hanya 3x per hari per penerima
            $cacheKeySendCount = 'reminder_send_count_' . $to . '_' . $plantArea . '_' . $today->toDateString();
            $sendCount = cache()->get($cacheKeySendCount, 0);
            if ($sendCount >= 3) {
                Log::info('Reminder: Sudah terkirim 3x hari ini, skip', [
                    'plant_area' => $plantArea,
                    'to' => $to,
                    'date' => $today->toDateString(),
                    'send_count' => $sendCount
                ]);
                continue;
            }

            // Tambahan: Cek apakah sudah pernah dikirim pada jam ini
            $cacheKeyHour = 'reminder_sent_hour_' . $to . '_' . $plantArea . '_' . $today->toDateString() . '_' . $now->hour;
            if (cache()->has($cacheKeyHour)) {
                Log::info('Reminder: Sudah dikirim pada jam ini, skip', [
                    'plant_area' => $plantArea,
                    'to' => $to,
                    'date' => $today->toDateString(),
                    'hour' => $now->hour
                ]);
                continue;
            }
            $cacheKeyBase = 'reminder_sent_' . $to . '_' . $plantArea . '_' . $today->toDateString();
            $remindedCategories = cache()->get($cacheKeyBase . '_categories', []);
            $notUploadedCategories = [];
            foreach ($rec['categories'] as $category) {
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
            if (count($notUploadedCategories) > 0 && $to) {
                $subject = '[Reminder !] Input Stock Alert';
                $categoryList = '<ul>';
                foreach ($notUploadedCategories as $cat) {
                    $categoryList .= '<li><b>' . $cat . '</b></li>';
                }
                $categoryList .= '</ul>';
                $content = "<div style='font-family: Arial, sans-serif; font-size: 15px;'>"
                    . "<b>Dear  $pic,</b><br>"
                    . "Hari ini anda <b>masih belum menginformasikan stok quantity</b> untuk kategori berikut:<br>"
                    . $categoryList
                    . "Silahkan untuk segera melakukan Upload Stock pada aplikasi webApp STO.<br>"
                    // . "<hr style='border: none; border-top: 1px solid #eee;'>"
                    . "<span style='font-size: 13px; color: #888;'>Jika ada kendala silakan hubungi <a href='mailto:muh.slmnfauzi@gmail.com'>muh.slmnfauzi@gmail.com</a></span><br>"
                    . "<span style='font-size: 13px; color: #888;'>Regards,<br>STO KBI - Administrator</span>"
                    . "</div>";
                Mail::html($content, function ($message) use ($to, $cc, $subject) {
                    $message->to($to);
                    if ($cc) $message->cc($cc);
                    $message->subject($subject);
                    $message->from('administrator@jajaleun.com', 'Administrator System');
                });
                // Update jumlah pengiriman
                cache()->put($cacheKeySendCount, $sendCount + 1, $today->copy()->addHours(36));
                // Tandai sudah kirim pada jam ini
                cache()->put($cacheKeyHour, true, $today->copy()->addHours(36));
                Log::info('Reminder email sent', [
                    'plant_area' => $plantArea,
                    'to' => $to,
                    'cc' => $cc,
                    'pic' => $pic,
                    'categories' => $notUploadedCategories,
                    'date' => $today->toDateString(),
                    'jam' => $now->hour
                ]);
                // Update cache kategori yang sudah diingatkan
                $newReminded = array_merge($remindedCategories, $notUploadedCategories);
                cache()->put($cacheKeyBase . '_categories', array_unique($newReminded), $today->copy()->addHours(36));
            } else {
                Log::info('Tidak ada kategori baru yang perlu diingatkan', [
                    'plant_area' => $plantArea,
                    'to' => $to,
                    'cc' => $cc,
                    'pic' => $pic,
                    'categories' => $notUploadedCategories,
                    'date' => $today->toDateString(),
                    'jam' => $now->hour
                ]);
            }
        }
    }
}