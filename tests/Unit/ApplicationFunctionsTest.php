<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Stringable;
use Tests\Support\TestEnvironment;

#[Group('unit')]
final class ApplicationFunctionsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once TestEnvironment::projectRoot() . '/config/application.php';
    }

    public function testEscapeHtmlEncodesSpecialCharactersAndQuotes(): void
    {
        $encoded = escapeHtml('<script>alert(\'x\') & "y"</script>');

        self::assertSame('&lt;script&gt;alert(&#039;x&#039;) &amp; &quot;y&quot;&lt;/script&gt;', $encoded);
    }

    public function testEscapeHtmlSubstitutesInvalidUtf8Sequences(): void
    {
        $encoded = escapeHtml("\xC3\x28");

        self::assertStringContainsString('�', $encoded);
        self::assertStringContainsString('(', $encoded);
    }

    public function testEscapeHtmlSupportsNullScalarsAndStringableValues(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'Valeur testable';
            }
        };

        self::assertSame('', escapeHtml(null));
        self::assertSame('42', escapeHtml(42));
        self::assertSame('1', escapeHtml(true));
        self::assertSame('0', escapeHtml(false));
        self::assertSame('Valeur testable', escapeHtml($stringable));
    }

    public function testValidateCitizenIdentifierNormalizesValidInput(): void
    {
        $result = validateCitizenIdentifier('  tsta0001 ');

        self::assertNull($result['error']);
        self::assertSame('TSTA0001', $result['value']);
    }

    public function testValidateCitizenIdentifierRejectsInvalidInput(): void
    {
        $result = validateCitizenIdentifier('bad-id');

        self::assertSame('Le CNIE doit contenir un préfixe alphabétique suivi de chiffres.', $result['error']);
        self::assertSame('', $result['value']);
    }

    public function testValidateCitizenSubmissionAcceptsFrenchAccentedAndArabicNames(): void
    {
        $result = validateCitizenSubmission([
            'cnieCitoyen' => 'tstc0009',
            'nomCit' => 'Él Idrissi',
            'prenomCit' => 'سارة',
            'adresseCit' => '15 rue Exemple',
            'villeCit' => 'Fès',
            'activiteCit' => 'Santé',
            'dateNaissanceCit' => '1991-04-23',
            'exCovid' => '1',
            'idCentre' => '3',
        ]);

        self::assertSame([], $result['errors']);
        self::assertSame([], $result['fatal']);
        self::assertSame('TSTC0009', $result['data']['cnieCitoyen']);
        self::assertSame('Él Idrissi', $result['data']['nomCit']);
        self::assertSame('سارة', $result['data']['prenomCit']);
    }

    public function testValidateCitizenSubmissionRejectsUnexpectedFieldStructures(): void
    {
        $result = validateCitizenSubmission([
            'cnieCitoyen' => 'TSTX0001',
            'nomCit' => ['bad'],
            'prenomCit' => 'Nom',
            'adresseCit' => 'Rue Test',
            'villeCit' => 'Rabat',
            'activiteCit' => 'Santé',
            'dateNaissanceCit' => '1990-10-10',
            'exCovid' => '0',
            'idCentre' => '1',
        ]);

        self::assertNotEmpty($result['fatal']);
    }

    public function testValidateCitizenSubmissionRejectsMissingAndInvalidFields(): void
    {
        $result = validateCitizenSubmission([
            'cnieCitoyen' => 'TSTX0001',
            'nomCit' => '   ',
            'prenomCit' => "Test\x01",
            'adresseCit' => '<bad>',
            'villeCit' => '',
            'activiteCit' => 'Invalide',
            'dateNaissanceCit' => '2999-01-01',
            'exCovid' => '9',
            'idCentre' => 'abc',
        ]);

        self::assertArrayHasKey('nomCit', $result['errors']);
        self::assertArrayHasKey('prenomCit', $result['errors']);
        self::assertArrayHasKey('adresseCit', $result['errors']);
        self::assertArrayHasKey('villeCit', $result['errors']);
        self::assertArrayHasKey('activiteCit', $result['errors']);
        self::assertArrayHasKey('dateNaissanceCit', $result['errors']);
        self::assertArrayHasKey('exCovid', $result['errors']);
        self::assertArrayHasKey('idCentre', $result['errors']);
    }

    public function testValidateCitizenSubmissionRejectsOverlongPayloadsAndNullBytes(): void
    {
        $result = validateCitizenSubmission([
            'cnieCitoyen' => 'TSTX0001',
            'nomCit' => str_repeat('A', 51),
            'prenomCit' => "Ali\0",
            'adresseCit' => str_repeat('B', 101),
            'villeCit' => str_repeat('C', 51),
            'activiteCit' => 'Santé',
            'dateNaissanceCit' => '1985-05-05',
            'exCovid' => '0',
            'idCentre' => '1',
        ]);

        self::assertArrayHasKey('nomCit', $result['errors']);
        self::assertArrayHasKey('prenomCit', $result['errors']);
        self::assertArrayHasKey('adresseCit', $result['errors']);
        self::assertArrayHasKey('villeCit', $result['errors']);
    }
}
