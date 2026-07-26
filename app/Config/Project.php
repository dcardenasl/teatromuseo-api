<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Project metadata (single source of truth).
 */
class Project extends BaseConfig
{
    public const NAME = 'ci4-website-builder Hub';
    public const DESCRIPTION = 'RESTful API built with CodeIgniter 4, featuring JWT authentication, standardized responses, and comprehensive documentation.';
    public const VERSION = '2.2.2';

    public string $name = 'ci4-website-builder Hub';
    public string $description = 'RESTful API built with CodeIgniter 4, featuring JWT authentication, standardized responses, and comprehensive documentation.';
    public string $version = '2.2.2';
}
