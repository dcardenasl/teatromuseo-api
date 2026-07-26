<?php

declare(strict_types=1);

namespace Tests\Unit\Validations;

use App\Validations\Rules\CustomRules;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Custom Validation Rules Tests
 */
class CustomRulesTest extends CIUnitTestCase
{
    private CustomRules $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = new CustomRules();
    }

    // ==================== boolean_like() TESTS ====================

    public function testBooleanLikeAcceptsTrueBoolean(): void
    {
        $error = null;
        $this->assertTrue($this->rules->boolean_like(true, $error));
        $this->assertNull($error);
    }

    public function testBooleanLikeAcceptsFalseBoolean(): void
    {
        $error = null;
        $this->assertTrue($this->rules->boolean_like(false, $error));
        $this->assertNull($error);
    }

    public function testBooleanLikeAcceptsZeroAndOneInts(): void
    {
        $this->assertTrue($this->rules->boolean_like(0));
        $this->assertTrue($this->rules->boolean_like(1));
    }

    public function testBooleanLikeRejectsOtherIntegers(): void
    {
        $error = null;
        $this->assertFalse($this->rules->boolean_like(2, $error));
        $this->assertNotEmpty($error);
    }

    public function testBooleanLikeAcceptsStringDigits(): void
    {
        $this->assertTrue($this->rules->boolean_like('0'));
        $this->assertTrue($this->rules->boolean_like('1'));
    }

    public function testBooleanLikeAcceptsTruthyAndFalsyStrings(): void
    {
        foreach (['true', 'false', 'yes', 'no', 'on', 'off'] as $value) {
            $this->assertTrue($this->rules->boolean_like($value), sprintf('Expected "%s" to be valid', $value));
        }
    }

    public function testBooleanLikeIsCaseInsensitive(): void
    {
        foreach (['TRUE', 'False', 'YES', 'No', 'ON', 'Off'] as $value) {
            $this->assertTrue($this->rules->boolean_like($value), sprintf('Expected "%s" to be valid', $value));
        }
    }

    public function testBooleanLikeRejectsArbitraryString(): void
    {
        $error = null;
        $this->assertFalse($this->rules->boolean_like('banana', $error));
    }

    public function testBooleanLikeRejectsNull(): void
    {
        $error = null;
        $this->assertFalse($this->rules->boolean_like(null, $error));
        $this->assertNotEmpty($error);
    }

    public function testBooleanLikeRejectsArray(): void
    {
        $error = null;
        $this->assertFalse($this->rules->boolean_like(['true'], $error));
        $this->assertNotEmpty($error);
    }
}
