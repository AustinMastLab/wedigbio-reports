<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_checkpoints', function (Blueprint $table): void {
            $table->timestamp('first_failed_at')->nullable()->after('last_error');
            $table->timestamp('failure_alert_sent_at')->nullable()->after('first_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('source_checkpoints', function (Blueprint $table): void {
            $table->dropColumn(['first_failed_at', 'failure_alert_sent_at']);
        });
    }
};
