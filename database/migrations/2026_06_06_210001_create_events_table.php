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
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('year')->index();
            $table->enum('season', ['spring', 'fall'])->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_live')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->string('display_alias')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_public', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
