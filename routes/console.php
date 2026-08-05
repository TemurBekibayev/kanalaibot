<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CleanOldPostsJob;
use App\Jobs\PublishPostJob;
use App\Models\Post;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-cleanup of expired posts every hour
Schedule::job(new CleanOldPostsJob)->hourly();

// Check and publish scheduled posts every minute
Schedule::call(function () {
    $now = Carbon::now();
    
    // Find posts with scheduled status whose scheduled time has passed
    $pendingPosts = Post::where('status', 'scheduled')
        ->where('scheduled_at', '<=', $now)
        ->get();

    foreach ($pendingPosts as $post) {
        // Dispatch publishing task to queue
        PublishPostJob::dispatch($post->id);
    }
})->everyMinute();
