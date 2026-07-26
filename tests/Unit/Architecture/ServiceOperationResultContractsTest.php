<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;
use ReflectionMethod;

/**
 * Guardrail for command-style service operations that must return OperationResult.
 */
class ServiceOperationResultContractsTest extends CIUnitTestCase
{
    /**
     * @return array<string, array<int, string>>
     */
    private function contractMap(): array
    {
        return [
            \App\Services\Auth\AuthService::class => ['loginWithGoogleToken'],
            \App\Services\Tokens\AuthTokenService::class => ['revokeToken', 'revokeAllUserTokens'],
            \App\Services\System\MetricsService::class => ['record'],
            \App\Services\Tokens\RefreshTokenService::class => ['revoke', 'revokeAllUserTokens'],
        ];
    }

    public function testMappedServiceMethodsReturnOperationResult(): void
    {
        $violations = [];

        foreach ($this->contractMap() as $class => $methods) {
            foreach ($methods as $methodName) {
                $method = new ReflectionMethod($class, $methodName);
                $returnType = $method->getReturnType();
                $typeName = $returnType !== null ? $returnType->getName() : '';

                if ($typeName !== \dcardenasl\Ci4ApiCore\Support\OperationResult::class) {
                    $violations[] = "{$class}::{$methodName} must return " . \dcardenasl\Ci4ApiCore\Support\OperationResult::class;
                }
            }
        }

        $this->assertSame([], $violations, "OperationResult contract violations:\n- " . implode("\n- ", $violations));
    }
}
