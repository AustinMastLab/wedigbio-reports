<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AppDeployFilesCommandTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storagePath = sys_get_temp_dir().'/wedigbio-deploy-files-'.uniqid();
        $this->app->useStoragePath($this->storagePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    public function test_renders_supervisor_config_for_the_given_current_path(): void
    {
        $this->artisan('app:deploy-files', ['--current-path' => '/data/web/wedigbio-reports/current/'])
            ->assertSuccessful();

        $config = File::get($this->storagePath.'/app/supervisor/wedigbio-ingest.conf');

        $this->assertStringContainsString("directory=/data/web/wedigbio-reports/current\n", $config);
        $this->assertStringContainsString('stdout_logfile=/data/web/wedigbio-reports/current/storage/logs/wedigbio-ingest.log', $config);
        $this->assertStringContainsString('environment=APP_ENV="testing"', $config);
        $this->assertStringNotContainsString('{{', $config);
    }

    public function test_defaults_the_current_path_to_the_app_base_path(): void
    {
        $this->artisan('app:deploy-files')->assertSuccessful();

        $config = File::get($this->storagePath.'/app/supervisor/wedigbio-ingest.conf');

        $this->assertStringContainsString('directory='.base_path()."\n", $config);
    }
}
