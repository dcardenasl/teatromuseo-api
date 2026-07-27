<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\LegacyMigration\LegacySqlDumpReader;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LegacySqlDumpReaderTest extends TestCase
{
    private string $dumpPath;

    protected function setUp(): void
    {
        $this->dumpPath = tempnam(sys_get_temp_dir(), 'teatromuseo-sql-');
        file_put_contents(
            $this->dumpPath,
            <<<'SQL'
CREATE TABLE `sn_obra` (`id_obra` int, `titulo_obra` text);
-- Volcado de datos
INSERT INTO `sn_obra` (`id_obra`, `titulo_obra`) VALUES
(1, 'Obra, "especial"'),
(2, 'Texto con ''comilla'' y ; punto');
INSERT INTO `sn_other` (`id`) VALUES (99);
SQL
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->dumpPath);
    }

    public function testReadsOnlyRequestedInsertTablesAndDecodesSqlStrings(): void
    {
        $reader = new LegacySqlDumpReader($this->dumpPath);
        $rows = $reader->rowsForTables(['sn_obra']);

        $this->assertCount(2, $rows['sn_obra']);
        $this->assertSame('1', $rows['sn_obra'][0]['id_obra']);
        $this->assertSame('Obra, "especial"', $rows['sn_obra'][0]['titulo_obra']);
        $this->assertSame("Texto con 'comilla' y ; punto", $rows['sn_obra'][1]['titulo_obra']);
    }
}
