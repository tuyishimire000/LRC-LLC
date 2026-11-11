<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('lrc:about', function () {
    $this->info('LRC LLC Real Estate Management Platform');
    $this->line('Currency: RWF');
    $this->line('Timezone: Africa/Kigali');
})->purpose('Display LRC platform configuration');
