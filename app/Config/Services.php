<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;

require_once __DIR__ . '/AuthIdentityServices.php';
require_once __DIR__ . '/IamDomainServices.php';
require_once __DIR__ . '/TokenSecurityServices.php';
require_once __DIR__ . '/FileDomainServices.php';
require_once __DIR__ . '/ApiCoreServices.php';
require_once __DIR__ . '/SystemMonitoringServices.php';
require_once __DIR__ . '/RepositoryModelServices.php';

/**
 * Services Configuration file.
 *
 * Service factories are split by domain using traits under app/Config/Services
 * to keep this class focused and maintainable as the template grows.
 */
class Services extends BaseService
{
    use AuthIdentityServices;
    use TokenSecurityServices;
    use FileDomainServices;
    use IamDomainServices;
    use ApiCoreServices;
    use SystemMonitoringServices;
    use RepositoryModelServices;

    /**
     * The Request Service
     *
     * @param \Config\App|bool $getShared
     */
    public static function request($getShared = true): \dcardenasl\Ci4ApiCore\Http\ApiRequest
    {
        if (is_bool($getShared) && $getShared) {
            return static::getSharedInstance('request');
        }

        $config = $getShared instanceof \Config\App ? $getShared : config('App');

        return new \dcardenasl\Ci4ApiCore\Http\ApiRequest(
            $config,
            static::uri(),
            'php://input',
            new \CodeIgniter\HTTP\UserAgent()
        );
    }
}
