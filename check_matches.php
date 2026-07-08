<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tournament = App\Models\Tournament::where('name', 'test single 1')->first();
if (!$tournament) {
    echo "Tournament 'test single 1' not found\n";
    exit;
}

echo "Tournament ID: {$tournament->id}, Name: {$tournament->name}, Subfolder ID: {$tournament->tournament_sub_folder_id}\n";
if ($tournament->subFolder) {
    echo "Subfolder ID: {$tournament->subFolder->id}, Name: {$tournament->subFolder->name}, Assigned Courts: " . json_encode($tournament->subFolder->assigned_courts) . "\n";
} else {
    echo "No Subfolder association!\n";
}

echo "\nMatches:\n";
foreach ($tournament->matches as $m) {
    echo "Match ID: {$m->id}, Round: {$m->round}, Order: {$m->match_order}, Court: " . var_export($m->court_number, true) . ", Scheduled Time: {$m->scheduled_time}, Teams: " . ($m->team1 ? $m->team1->name : 'null') . " vs " . ($m->team2 ? $m->team2->name : 'null') . ", Winner: " . var_export($m->winner_id, true) . "\n";
}
