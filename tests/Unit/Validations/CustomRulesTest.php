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

    // ==================== strong_password() TESTS ====================

    public function testStrongPasswordAcceptsValidPassword(): void
    {
        $error = null;
        $this->assertTrue($this->rules->strong_password('ValidPass123!', $error));
        $this->assertNull($error);
    }

    public function testStrongPasswordRejectsNullAndEmpty(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password(null, $error));
        $this->assertFalse($this->rules->strong_password('', $error));
    }

    public function testStrongPasswordRejectsTooShort(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('Ab1!', $error));
        $this->assertNotEmpty($error);
    }

    public function testStrongPasswordRejectsTooLong(): void
    {
        $error = null;
        $tooLong = str_repeat('Aa1!', 33); // 132 chars
        $this->assertFalse($this->rules->strong_password($tooLong, $error));
        $this->assertNotEmpty($error);
    }

    public function testStrongPasswordRejectsMissingLowercase(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('PASSWORD123!', $error));
        $this->assertNotEmpty($error);
    }

    public function testStrongPasswordRejectsMissingUppercase(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('password123!', $error));
        $this->assertNotEmpty($error);
    }

    public function testStrongPasswordRejectsMissingDigit(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('PasswordOnly!', $error));
        $this->assertNotEmpty($error);
    }

    public function testStrongPasswordRejectsMissingSpecialChar(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('Password123', $error));
        $this->assertNotEmpty($error);
    }

    // ==================== valid_email_idn() TESTS ====================

    public function testValidEmailIdnAcceptsPlainAsciiEmail(): void
    {
        $error = null;
        $this->assertTrue($this->rules->valid_email_idn('user@example.com', $error));
        $this->assertNull($error);
    }

    public function testValidEmailIdnAcceptsInternationalDomain(): void
    {
        $error = null;
        $this->assertTrue($this->rules->valid_email_idn('user@münchen.de', $error));
        $this->assertNull($error);
    }

    public function testValidEmailIdnRejectsNullAndEmpty(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_email_idn(null, $error));
        $this->assertFalse($this->rules->valid_email_idn('', $error));
    }

    public function testValidEmailIdnRejectsMalformedEmail(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_email_idn('not-an-email', $error));
        $this->assertNotEmpty($error);
    }

    public function testValidEmailIdnRejectsMissingLocalPart(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_email_idn('@example.com', $error));
        $this->assertNotEmpty($error);
    }

    // ==================== valid_uuid() TESTS ====================

    public function testValidUuidAcceptsUuidV4(): void
    {
        $error = null;
        $this->assertTrue($this->rules->valid_uuid('550e8400-e29b-41d4-a716-446655440000', $error));
        $this->assertNull($error);
    }

    public function testValidUuidRejectsNullAndEmpty(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_uuid(null, $error));
        $this->assertFalse($this->rules->valid_uuid('', $error));
    }

    public function testValidUuidRejectsMalformedString(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_uuid('not-a-uuid', $error));
        $this->assertNotEmpty($error);
    }

    public function testValidUuidRejectsWrongVersion(): void
    {
        $error = null;
        // Version nibble is "1", not "4" — not a valid UUID v4.
        $this->assertFalse($this->rules->valid_uuid('550e8400-e29b-11d4-a716-446655440000', $error));
        $this->assertNotEmpty($error);
    }

    // ==================== valid_token() TESTS ====================

    public function testValidTokenAcceptsDefaultLengthHex(): void
    {
        $error = null;
        $token = bin2hex(random_bytes(32));
        $this->assertTrue($this->rules->valid_token($token, '64', [], $error));
        $this->assertNull($error);
    }

    public function testValidTokenAcceptsCustomLength(): void
    {
        $error = null;
        $token = bin2hex(random_bytes(16));
        $this->assertTrue($this->rules->valid_token($token, '32', [], $error));
    }

    public function testValidTokenRejectsNullAndEmpty(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_token(null, '64', [], $error));
        $this->assertFalse($this->rules->valid_token('', '64', [], $error));
    }

    public function testValidTokenRejectsNonHexCharacters(): void
    {
        $error = null;
        $notHex = str_repeat('z', 64);
        $this->assertFalse($this->rules->valid_token($notHex, '64', [], $error));
        $this->assertNotEmpty($error);
    }

    public function testValidTokenRejectsWrongLength(): void
    {
        $error = null;
        $token = bin2hex(random_bytes(10)); // 20 hex chars, not 64
        $this->assertFalse($this->rules->valid_token($token, '64', [], $error));
        $this->assertNotEmpty($error);
    }

    // ==================== is_list() TESTS ====================

    public function testIsListAcceptsSequentialArray(): void
    {
        $error = null;
        $this->assertTrue($this->rules->is_list([1, 2, 3], $error));
        $this->assertNull($error);
    }

    public function testIsListAcceptsEmptyArray(): void
    {
        $this->assertTrue($this->rules->is_list([]));
    }

    public function testIsListRejectsAssociativeArray(): void
    {
        $error = null;
        $this->assertFalse($this->rules->is_list(['a' => 1, 'b' => 2], $error));
        $this->assertNotEmpty($error);
    }

    public function testIsListRejectsNonArray(): void
    {
        $error = null;
        $this->assertFalse($this->rules->is_list('not-an-array', $error));
        $this->assertNotEmpty($error);
    }
}
