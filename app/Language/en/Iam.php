<?php

declare(strict_types=1);

return [
    'cannotGrantUnownedPermission'      => 'You cannot grant a permission you do not hold.',
    'cannotModifySystemRole'            => 'System roles cannot be modified.',
    'cannotModifySelf'                  => 'You cannot modify your own membership or account.',
    'cannotActOnSuperAdmin'             => 'You cannot operate on a super administrator.',
    'cannotPerformSuperAdminOperation'  => 'This operation requires super administrator privileges.',
    'cannotModifyEmail'                 => 'Only a super administrator may modify a user email.',
    'apiKeyHasNoApplication'            => 'The API key is not bound to an application and cannot issue service tokens.',
    'applicationNotFound'               => 'The application bound to this API key no longer exists.',
];
