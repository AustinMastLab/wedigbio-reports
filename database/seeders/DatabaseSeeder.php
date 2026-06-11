<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Admin user
        User::firstOrCreate(['email' => 'admin@wedigbio.org'], [
            'name'     => 'WeDigBio Admin',
            'password' => bcrypt('password'),
        ]);

        // Demo events, sources, and sample data
        $this->call(DemoSeeder::class);

        // Canonical source/platform records from historical WeDigBio integrations
        $this->call(LegacySourcesSeeder::class);
    }
}
