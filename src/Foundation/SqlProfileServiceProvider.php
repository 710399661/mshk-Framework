<?php
namespace Discuz\Foundation;

use Discuz\Foundation\SqlProfileListener;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Events\QueryExecuted;

class SqlProfileServiceProvider extends ServiceProvider
{
    protected $listen = [];

    public function register()
    {
        $events = app('events'); 
        $events->listen(QueryExecuted::class, SqlProfileListener::class); 
    }
}