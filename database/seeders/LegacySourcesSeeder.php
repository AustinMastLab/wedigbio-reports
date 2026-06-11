<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class LegacySourcesSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            [
                'slug' => 'digivol',
                'name' => 'DigiVol',
                'base_url' => 'http://volunteer.ala.org.au/ws/transcriptionFeed.json',
                'adapter_type' => 'digivol_json',
                'supports_weighting' => false,
                'is_active' => true,
                'notes' => 'Legacy WeDigBio endpoint from historical R scripts.',
                'auth_type' => null,
                'auth_config' => null,
            ],
            [
                'slug' => 'les-herbonautes',
                'name' => 'Les Herbonautes',
                'base_url' => 'http://lesherbonautes.mnhn.fr/contributions/interval/json',
                'adapter_type' => 'api_json',
                'supports_weighting' => true,
                'is_active' => false,
                'notes' => 'Legacy WeDigBio endpoint from historical R scripts.',
                'auth_type' => null,
                'auth_config' => null,
            ],
            [
                'slug' => 'smithsonian',
                'name' => 'Smithsonian Transcription Center',
                'base_url' => 'https://transcription.si.edu/transcribr_wedigbio/activity-feed',
                'adapter_type' => 'api_json',
                'supports_weighting' => false,
                'is_active' => false,
                'notes' => 'Requires secret key query parameter for legacy feed access.',
                'auth_type' => 'query_secret',
                'auth_config' => ['param' => 'secret_key', 'value' => ''],
            ],
            [
                'slug' => 'notes-from-nature',
                'name' => 'Notes From Nature (BioSpex)',
                'base_url' => 'https://api.biospex.org/v1/wedigbio-dashboard',
                'adapter_type' => 'biospex_json',
                'supports_weighting' => false,
                'is_active' => true,
                'notes' => 'BioSpex v1 endpoint for Notes From Nature activity.',
                'auth_type' => 'bearer_token',
                'auth_config' => ['token' => ''],
            ],
            [
                'slug' => 'doedat',
                'name' => 'DoeDat',
                'base_url' => 'https://www.doedat.be/ws/transcriptionFeed.json',
                'adapter_type' => 'api_json',
                'supports_weighting' => false,
                'is_active' => false,
                'notes' => 'Legacy WeDigBio endpoint from historical R scripts.',
                'auth_type' => null,
                'auth_config' => null,
            ],
            [
                'slug' => 'citsciscribe',
                'name' => 'CitSciScribe',
                'base_url' => 'http://citsciscribe.org/api/WeDigBioAPI',
                'adapter_type' => 'api_json',
                'supports_weighting' => false,
                'is_active' => false,
                'notes' => 'Legacy WeDigBio endpoint from historical R scripts.',
                'auth_type' => null,
                'auth_config' => null,
            ],
        ];

        foreach ($sources as $source) {
            $model = Source::firstOrNew(['slug' => $source['slug']]);

            $model->name = $model->name ?: $source['name'];
            $model->base_url = $model->base_url ?: $source['base_url'];
            $model->adapter_type = $model->adapter_type ?: $source['adapter_type'];
            $model->supports_weighting = (bool) ($model->exists ? $model->supports_weighting : $source['supports_weighting']);
            $model->is_active = (bool) ($model->exists ? $model->is_active : $source['is_active']);
            $model->notes = $model->notes ?: $source['notes'];
            $model->auth_type = $model->auth_type ?: $source['auth_type'];

            if ((blank($model->auth_config) || $model->auth_config === []) && is_array($source['auth_config'])) {
                $model->auth_config = $source['auth_config'];
            }

            $model->save();
        }
    }
}

