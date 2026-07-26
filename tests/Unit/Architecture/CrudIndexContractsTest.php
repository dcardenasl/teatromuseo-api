<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;
use ReflectionMethod;

/**
 * Guardrails for paginated CRUD index return contracts.
 */
class CrudIndexContractsTest extends CIUnitTestCase
{
    /**
     * @return array<int, class-string>
     */
    private function indexedContracts(): array
    {
        return [
            \dcardenasl\Ci4ApiCore\Services\CrudServiceContract::class,
            \App\Interfaces\Tokens\ApiKeyServiceInterface::class,
            \dcardenasl\Ci4ApiCore\Services\AuditServiceInterface::class,
            \App\Interfaces\Files\FileServiceInterface::class,
            \App\Interfaces\Users\UserServiceInterface::class,
            \dcardenasl\Ci4ApiCore\Services\BaseCrudService::class,
        ];
    }

    public function testIndexContractsReturnDataTransferObjectInterface(): void
    {
        $violations = [];

        foreach ($this->indexedContracts() as $class) {
            $method = new ReflectionMethod($class, 'index');
            $returnType = $method->getReturnType();
            $typeName = $returnType !== null ? $returnType->getName() : '';

            if ($typeName !== \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface::class) {
                $violations[] = "{$class}::index must return " . \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface::class;
            }
        }

        $this->assertSame([], $violations, "CRUD index return contract violations:\n- " . implode("\n- ", $violations));
    }
}
