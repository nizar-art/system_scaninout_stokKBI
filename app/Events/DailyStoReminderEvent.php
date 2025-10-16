<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DailyStoReminderEvent
{
    use Dispatchable, SerializesModels;
    public $allowedHours;
    public function __construct($allowedHours = [10, 12, 14, 16, 18])
    {
        $this->allowedHours = $allowedHours;
    }
}