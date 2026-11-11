<?php
use App\Http\Middleware\EnsureUserHasAnyRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
return Application::configure(basePath:dirname(__DIR__))
 ->withRouting(web:__DIR__.'/../routes/web.php',commands:__DIR__.'/../routes/console.php',health:'/up')
 ->withCommands([__DIR__.'/../app/Console/Commands'])
 ->withSchedule(function (Schedule $schedule): void {
     $schedule->command('lrc:rent-generate')->monthlyOn(1, '00:15')->timezone('Africa/Kigali')->withoutOverlapping();
 })
 ->withMiddleware(function(Middleware $middleware):void{$middleware->alias(['role'=>EnsureUserHasAnyRole::class]);})
 ->withExceptions(function(Exceptions $exceptions):void{})->create();
