<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        if (ini_get('zlib.output_compression')) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

/**
 * --------------------------------------------------------------------
 * User Domain Events
 * --------------------------------------------------------------------
 */
Events::on('user.created', static function ($user, $context = null, $locale = null): void {
    try {
        $invitationService = \Config\Services::userInvitationService();
        $resolvedLocale = null;
        if ($context instanceof \dcardenasl\Ci4ApiCore\Dto\SecurityContext) {
            $resolvedLocale = is_string($context->metadata['locale'] ?? null) ? (string) $context->metadata['locale'] : null;
        }

        if ($resolvedLocale === null && is_string($locale) && $locale !== '') {
            $resolvedLocale = $locale;
        }

        if ($resolvedLocale === null) {
            $resolvedLocale = (string) service('request')->getLocale();
        }

        $invitationService->sendInvitation($user, null, $resolvedLocale);
    } catch (\Throwable $e) {
        log_message('error', 'Failed to send invitation email for user ' . $user->id . ': ' . $e->getMessage());
    }
});
