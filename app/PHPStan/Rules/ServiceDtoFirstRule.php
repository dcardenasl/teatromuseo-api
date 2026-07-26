<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
class ServiceDtoFirstRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // 1. Only analyze files in app/Services
        $file = $scope->getFile();
        if (!str_contains($file, 'app/Services/')) {
            return [];
        }

        // 2. Only check public methods
        if (!$node->isPublic()) {
            return [];
        }

        // 3. Skip magic methods (__construct, __destruct, etc.)
        $methodName = $node->name->name;
        if (str_starts_with($methodName, '__')) {
            return [];
        }

        // 4. Skip common inherited framework/core methods that are forced to receive/return arrays
        $ignoredMethods = ['beforeStore', 'beforeUpdate', 'afterStore', 'afterUpdate', 'log', 'buildEvent', 'sanitize'];
        if (in_array($methodName, $ignoredMethods, true)) {
            return [];
        }

        $errors = [];

        // 5. Check parameters for raw array type-hint
        foreach ($node->params as $param) {
            if ($param->type instanceof Node\Identifier && $param->type->name === 'array') {
                $paramName = $param->var instanceof Node\Expr\Variable && is_string($param->var->name)
                    ? '$' . $param->var->name
                    : 'parameter';

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Method %s() in DTO-First Service has array type-hint for %s. Use a DTO or specific object class instead.',
                    $methodName,
                    $paramName
                ))->identifier('dtoFirst.arrayParameter')->build();
            }
        }

        // 6. Check return type for raw array type-hint
        if ($node->returnType instanceof Node\Identifier && $node->returnType->name === 'array') {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Method %s() in DTO-First Service has array return type-hint. Use a DTO or specific object class instead.',
                $methodName
            ))->identifier('dtoFirst.arrayReturn')->build();
        }

        return $errors;
    }
}
