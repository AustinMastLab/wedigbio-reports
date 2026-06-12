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
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add an application-enforced single-live-event constraint.
     * Since generated columns with IF() aren't portable across SQLite and MySQL,
     * we rely on app-layer validation in Filament forms to prevent duplicate live events.
     *
     * For production MySQL environments, consider adding a trigger:
     * CREATE TRIGGER enforce_single_live_event BEFORE INSERT ON events
     * FOR EACH ROW BEGIN
     *   IF NEW.is_live = 1 AND (SELECT COUNT(*) FROM events WHERE is_live = 1) > 0 THEN
     *     SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one event can be live at a time';
     *   END IF;
     * END;
     */
    public function up(): void
    {
        // For now, rely on app-level validation. If using MySQL in production,
        // consider adding a database trigger via raw SQL:
        // DB::statement('CREATE TRIGGER enforce_single_live_event BEFORE INSERT ON events FOR EACH ROW ...')

        // This migration is a placeholder for future DB-level constraints if needed.
    }

    public function down(): void
    {
        // Nothing to roll back as we didn't add any schema changes.
        // If triggers are later added, drop them in the down() method.
    }
};
