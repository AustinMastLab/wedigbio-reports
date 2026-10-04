<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AppDeployFilesCommand extends Command
{
    protected $signature = 'app:deploy-files
        {--current-path= : Path Supervisor runs the app from (defaults to this app\'s base path)}';

    protected $description = 'Render resources/supervisor templates into storage/app/supervisor';

    /**
     * Render each Supervisor template with this environment's values.
     *
     * Output goes to shared storage so the server's Supervisor [include] of
     * current/storage/app/supervisor/*.conf sees it before the release is published.
     */
    public function handle(): int
    {
        $templates = File::files(resource_path('supervisor'));

        if ($templates === []) {
            $this->error('No Supervisor templates found in '.resource_path('supervisor'));

            return self::FAILURE;
        }

        $replacements = [
            '{{APP_CURRENT_PATH}}' => rtrim($this->option('current-path') ?: base_path(), '/'),
            '{{APP_ENV}}' => (string) config('app.env'),
        ];

        $targetDirectory = storage_path('app/supervisor');
        File::ensureDirectoryExists($targetDirectory);

        foreach ($templates as $template) {
            $targetPath = $targetDirectory.'/'.$template->getFilename();

            File::put($targetPath, strtr(File::get($template->getPathname()), $replacements), true);

            $this->info("Rendered {$targetPath}");
        }

        return self::SUCCESS;
    }
}
