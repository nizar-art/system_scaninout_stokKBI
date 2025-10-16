<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DailyStoImportBroadcastEvent
{
    use Dispatchable, SerializesModels;
    
    public $preparedBy;
    public $categories;
    public $plant;
    public $timestamp;
    
    public function __construct($preparedBy, $categories, $plant, $timestamp = null)
    {
        $this->preparedBy = $preparedBy;
        $this->categories = $categories;
        $this->plant = $plant;
        $this->timestamp = $timestamp ?: time();
    }
}