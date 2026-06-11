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

namespace App\Ingestion;

use App\Ingestion\Adapters\BiospexJsonSourceAdapter;
use App\Ingestion\Adapters\DigivolJsonSourceAdapter;
use App\Ingestion\Adapters\HttpJsonSourceAdapter;
use App\Ingestion\Contracts\SourceAdapter;
use InvalidArgumentException;

class SourceAdapterManager
{
    public function __construct(
        private readonly HttpJsonSourceAdapter $httpJsonSourceAdapter,
        private readonly BiospexJsonSourceAdapter $biospexJsonSourceAdapter,
        private readonly DigivolJsonSourceAdapter $digivolJsonSourceAdapter,
    ) {}

    public function forType(string $adapterType): SourceAdapter
    {
        return match ($adapterType) {
            'api_json', 'http_json' => $this->httpJsonSourceAdapter,
            'biospex_json' => $this->biospexJsonSourceAdapter,
            'digivol_json' => $this->digivolJsonSourceAdapter,
            default => throw new InvalidArgumentException("Unsupported source adapter type [{$adapterType}]"),
        };
    }
}
