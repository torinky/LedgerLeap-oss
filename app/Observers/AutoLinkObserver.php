<?php

namespace App\Observers;

use App\Models\AutoLink;
use Illuminate\Support\Facades\Cache;

class AutoLinkObserver
{
    /**
     * Handle the AutoLink "saved" event.
     */
    public function saved(AutoLink $autoLink): void
    {
        Cache::tags('auto_links')->flush();
    }

    /**
     * Handle the AutoLink "deleted" event.
     */
    public function deleted(AutoLink $autoLink): void
    {
        Cache::tags('auto_links')->flush();
    }
}
