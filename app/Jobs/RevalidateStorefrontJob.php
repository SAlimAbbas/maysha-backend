<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RevalidateStorefrontJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The cache tags to revalidate on the Next.js storefront.
     *
     * @var array<string>
     */
    public array $tags;

    /**
     * Create a new job instance.
     */
    public function __construct(array|string $tags)
    {
        $this->tags = (array) $tags;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $url = env('REVALIDATE_URL');
        $secret = env('REVALIDATE_SECRET');

        if (! $url || ! $secret) {
            return;
        }

        try {
            Http::timeout(5)
                ->withHeaders([
                    'x-revalidate-secret' => $secret,
                ])
                ->post($url, [
                    'tags' => $this->tags,
                ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to trigger storefront ISR revalidation at {$url}: {$e->getMessage()}");
        }
    }
}
