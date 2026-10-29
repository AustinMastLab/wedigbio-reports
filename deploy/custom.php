<?php

/*
 * WeDigBio Reports CUSTOM DEPLOYMENT TASKS
 *
 * This file contains custom deployment tasks for the WeDigBio Reports project.
 *
 * KEY FEATURES:
 * - CI/CD artifact deployment (no server-side building)
 * - Environment-specific configuration uploads
 * - Custom Laravel Artisan commands integration
 *
 * Copyright (c) 2026. WeDigBio Reports
 * wedigbio@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Deployer;

/*
 * =============================================================================
 * CUSTOM LARAVEL ARTISAN TASKS
 * =============================================================================
 */

/**
 * Run Laravel package discovery
 */
desc('Run Laravel package discovery');
task('artisan:package:discover', function () {
    cd('{{release_or_current_path}}');
    run('php artisan package:discover --ansi');
});

/**
 * Set proper file permissions for the application
 * Sets ownership to ubuntu:www-data and clears the Laravel log file
 */
desc('Setting permissions...');
task('set:permissions', function () {
    run('sudo chown -R ubuntu.www-data {{deploy_path}}');
    run('sudo truncate -s 0 {{release_or_current_path}}/storage/logs/*.log');
});

// deploy:ci-artifacts, deploy:verify-structure and supervisor:reload come from
// deployer-recipes/recipe/ci-artifacts.php (required in deploy.php).

/**
 * Clear package discovery cache before running composer operations
 * This prevents conflicts when packages are removed (like Nova)
 */
desc('Clear package discovery cache...');
task('clear:package-cache', function () {
    cd('{{release_or_current_path}}');

    // Remove cached package manifests that might reference removed packages
    run('rm -f bootstrap/cache/packages.php');
    run('rm -f bootstrap/cache/services.php');
    run('rm -f bootstrap/cache/config.php');

    // Clear any cached views that might reference removed packages
    run('rm -rf storage/framework/views/*');
    run('rm -rf storage/framework/cache/data/*');

    writeln('✅ Package discovery cache cleared');
});

desc('Ensure Supervisor log directory exists');
task('supervisor:ensure-log-dir', function () {
    // Supervisor (root) writes the worker's stdout_logfile here; see resources/supervisor/wedigbio-ingest.conf
    $logDir = '/var/log/supervisor/wedigbio-reports';

    run("sudo mkdir -p {$logDir}");
    run("sudo chmod 755 {$logDir}");

    writeln("✅ Supervisor log directory ready: {$logDir}");
});

/**
 * Run one-off data update queries after migrations.
 * Safe no-op when update:queries has no configured entries.
 */
desc('Run deploy update queries');
task('artisan:update-queries', function () {
    cd('{{release_or_current_path}}');
    run('php artisan update:queries --ansi');
});

/**
 * Publish Filament assets
 */
desc('Publish Filament assets');
task('artisan:filament:assets', function () {
    cd('{{release_or_current_path}}');
    run('php artisan filament:assets');
    writeln('✅ Filament assets published');
});

desc('Optimize Filament resources and assets');
task('artisan:filament:optimize', function () {
    cd('{{release_or_current_path}}');
    run('php artisan filament:optimize --ansi');
    writeln('✅ Filament optimization completed');
});

/*
 * =============================================================================
 * SUPERVISOR CONFIGURATION GENERATION
 * =============================================================================
 */

desc('Render Supervisor configs from resources/supervisor into shared storage');
task('artisan:app:deploy-files', function () {
    // The server's Supervisor [include] reads current/storage/app/supervisor/*.conf;
    // storage is shared, so the reload before publish already sees the new file
    cd('{{release_or_current_path}}');
    run('php artisan app:deploy-files --current-path={{current_path}} --ansi');
});
