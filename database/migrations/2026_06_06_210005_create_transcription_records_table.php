<?php

/*
Copyright (C) 2026 - $today.year, WeDigBio
wedigbio@gmail.com
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundaation, either version 3 of the License, or
(at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transcription_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('source_guid')->nullable();
            $table->string('dedupe_key')->unique();
            $table->string('center')->index();
            $table->string('project')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('timestamp_utc')->index();
            $table->decimal('work_unit', 10, 4);
            $table->unsignedTinyInteger('raw_count')->default(1);
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'timestamp_utc']);
            $table->index(['event_id', 'center', 'timestamp_utc']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcription_records');
    }
};
