<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Wipes everything and re-seeds the sample crew. Runs nightly on a demo server so whatever
 * visitors posted or uploaded disappears. Refuses to run unless DEMO_MODE=true.
 */
#[Signature('aviate:reset-demo {--force : Skip the confirmation question}')]
#[Description('Erase all data and uploads and restore the demo crew (demo mode only)')]
class ResetDemo extends Command
{
    public function handle(): int
    {
        if (! config('aviate.demo.enabled')) {
            $this->error('Refusing to reset: DEMO_MODE is not true. This command erases every account and post.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This erases ALL accounts, posts, messages and photos. Continue?')) {
            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        foreach (['posts', 'avatars'] as $dir) {
            $disk->deleteDirectory($dir);
            $disk->makeDirectory($dir);
        }

        $this->call('migrate:fresh', ['--force' => true, '--seed' => true]);

        $this->info('Demo reset: fresh database and sample crew restored.');

        return self::SUCCESS;
    }
}
