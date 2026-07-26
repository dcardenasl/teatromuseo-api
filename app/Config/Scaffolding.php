<?php

declare(strict_types=1);

namespace Config;

use dcardenasl\Ci4ApiScaffolding\Config\BaseScaffoldingConfig;
use dcardenasl\Ci4ApiScaffolding\Config\ScaffoldingConfig;

class Scaffolding extends BaseScaffoldingConfig
{
    public function build(): ScaffoldingConfig
    {
        return ScaffoldingConfig::defaults(
            protectedRouteFilters: ['jwtauth', 'throttle'],
        );
    }
}
