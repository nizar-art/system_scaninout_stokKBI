<?php

namespace App\Listeners;

use App\Events\DailyStoImportBroadcastEvent;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendImportBroadcastListener
{
    public function handle(DailyStoImportBroadcastEvent $event)
    {
        // Skip jika preparedBy adalah superAdmin (case-insensitive, trim spasi)
        if (isset($event->preparedBy) && strtolower(trim($event->preparedBy)) === 'superadmin') {
            Log::info('Broadcast import email SKIPPED (preparedBy is superAdmin)', ['preparedBy' => $event->preparedBy]);
            return;
        }
        
        // Ensure categories is always an array
        $eventCategories = is_array($event->categories) ? $event->categories : [$event->categories];
        
        Log::info('Processing import broadcast event', [
            'preparedBy' => $event->preparedBy,
            'plant' => $event->plant,
            'categories' => $eventCategories,
            'categoriesType' => gettype($event->categories)
        ]);
        
        $recipients = [
            // testing
            [
                // 'categories' => ['Finished Good', 'WIP'],
                'categories' => ['ChildPart', 'Raw Material', 'Packaging'],
                'plant_area' => 'KBI 2',
                'pic' => 'Salman',
                'to' => 'salmanfauzi0512@gmail.com',
                'cc' => 'slmnstudy@gmail.com',
            ],
            
            [
                // 'categories' => ['Finished Good', 'WIP'],
                'categories' => ['ChildPart', 'Raw Material', 'Packaging'],
                'plant_area' => 'KBI 1',
                'pic' => 'Heriansyah',
                'to' => 'salmanfauzi0512@gmail.com',
                'cc' => ['slmnstudy@gmail.com'],
            ],

            // end testing
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
            // [
            //     'categories' => ['ChildPart', 'Raw Material', 'Packaging'],
            //     'plant_area' => 'KBI 1',
            //     'pic' => 'Heriansyah',
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

        ];
        $today = Carbon::today();
        foreach ($recipients as $rec) {
            // First check - Verify if plant area matches
            if ($rec['plant_area'] != $event->plant) {
                Log::debug('Plant tidak cocok - melewati pemberitahuan', [
                    'recipient_plant' => $rec['plant_area'],
                    'event_plant' => $event->plant,
                    'pic' => $rec['pic']
                ]);
                continue; // Lewati jika plant tidak cocok
            }
            
            // Pemeriksaan kedua - Verifikasi apakah ada kategori yang cocok
            $matchingCategories = $this->getMatchingCategories($rec['categories'], $eventCategories);
            
            Log::debug('Hasil pencocokan kategori', [
                'recipient_categories' => $rec['categories'],
                'event_categories' => $eventCategories,
                'matching_categories' => $matchingCategories,
                'plant' => $rec['plant_area'],
                'pic' => $rec['pic']
            ]);
            
            if (empty($matchingCategories)) {
                Log::info('Tidak ada kategori yang cocok ditemukan - melewati pemberitahuan', [
                    'recipient_categories' => $rec['categories'],
                    'event_categories' => $eventCategories,
                    'plant' => $rec['plant_area'],
                    'pic' => $rec['pic']
                ]);
                continue; //Lewati jika tidak ada kategori yang cocok
            }

            // Pada titik ini, plant dan kategori cocok - lanjutkan dengan mengirim email
            $to = $rec['to'] ?? $rec['cc'];
            $cc = $rec['to'] ? $rec['cc'] : null;
            $pic = $rec['pic'] ?? $rec['pic'];
            
            // Gunakan hanya kategori yang cocok (kasus dipertahankan dari acara)
            $categories = $matchingCategories; 


            $categoriesKey = implode(',', array_map('strtolower', $categories));
            $cacheKey = 'import_broadcast_' . md5($event->preparedBy . '|' . $rec['plant_area']  . '|' . $categoriesKey . '|' . $to);
            
            // if (cache()->has($cacheKey)) {
            //     Log::info('Broadcast import email SKIPPED (already sent today)', [
            //         'plant_area' => $rec['plant_area'],
            //         'to' => $to,
            //         'categories' => $categories,
            //         'prepared_by' => $event->preparedBy,
            //     ]);
            //     continue;
            // }
            
            // Create HTML list with all matched categories
            $categoryList = '<ul style="margin-top: 10px; margin-bottom: 15px;">';
            foreach ($categories as $cat) {
                $categoryList .= '<li><b>' . htmlspecialchars($cat) . '</b></li>';
            }
            $categoryList .= '</ul>';
            
            $subject = '[INFO] Import Stock Berhasil';
            $content = "<div style='font-family: Arial, sans-serif; font-size: 15px;'>"
                . "<b>Dear $pic,</b><br>"
                . "Data stock untuk kategori berikut telah berhasil diimport hari ini:<br>"
                . $categoryList
                . "<b>Prepared by:</b> <span style='color:#007bff;'>" . htmlspecialchars($event->preparedBy) . "</span><br>"
                . "Terima kasih atas kerjasama dan dedikasinya.<br>"
                . "Silakan cek aplikasi webApp STO .<br>"
                . "<span style='font-size: 13px; color: #888;'>Jika ada kendala silakan hubungi <a href='mailto:muh.slmnfauzi@gmail.com'>muh.slmnfauzi@gmail.com</a></span><br>"
                . "<span style='font-size: 13px; color: #888;'>Regards,<br>STO KBI - Administrator</span>"
                . "</div>";
            
            Mail::html($content, function ($message) use ($to, $cc, $subject) {
                $message->to($to);
                if ($cc) $message->cc($cc);
                $message->subject($subject);
                $message->from('administrator@jajaleun.com', 'Administrator System');
            });
            
            Log::info('Email impor Broadcast  terkirim', [
                'plant_area' => $rec['plant_area'],
                'to' => $to,
                'cc' => $cc,
                'pic' => $pic,
                'categories' => $categories,  // Log full array of categories
                'prepared_by' => $event->preparedBy,
                'date' => $today->toDateString()
            ]);
            
            // Set cache to prevent duplicate emails (expires after 24 hours)
            cache()->put($cacheKey, true, now()->addDay());
        }
    }
    
    /**
     * Get matching categories using case-insensitive comparison
     */
    private function getMatchingCategories(array $recipientCategories, array $eventCategories): array
    {
        $matchingCategories = [];
        $lowerRecipientCategories = array_map(function($cat) {
            return strtolower(trim($cat));
        }, $recipientCategories);
        
        foreach ($eventCategories as $eventCategory) {
            $lowerEventCategory = strtolower(trim($eventCategory));
            
            // If the lowercase version matches any recipient category
            if (in_array($lowerEventCategory, $lowerRecipientCategories)) {
                // Add the original case-preserved version to matches
                $matchingCategories[] = $eventCategory;
            }
        }
        
        return $matchingCategories;
    }
}