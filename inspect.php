<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\Tournament::whereIn('id', [55, 57])->get() as $t) {
    echo "Tournament: {$t->name} (ID: {$t->id}, Status: {$t->status})\n";
    foreach ($t->matches()->orderBy('round')->orderBy('match_order')->get() as $m) {
        echo "  Match: Round {$m->round}, Order {$m->match_order}, Time: {$m->scheduled_time}\n";
    }
}
