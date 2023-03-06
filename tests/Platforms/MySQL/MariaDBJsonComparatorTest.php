<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\MySQL;

use Doctrine\DBAL\Platforms\MariaDB1043Platform;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;

use function sprintf;

class MariaDBJsonComparatorTest extends TestCase
{
    protected Comparator $comparator;

    /** @var Table[] */
    private array $tables = [];

    protected function setUp(): void
    {
        $this->comparator = new Comparator(
            new MariaDB1043Platform(),
            new class implements CharsetMetadataProvider {
                public function getDefaultCharsetCollation(string $charset): ?string
                {
                    return null;
                }
            },
            new class implements CollationMetadataProvider {
                public function getCollationCharset(string $collation): ?string
                {
                    return null;
                }
            },
            new DefaultTableOptions('utf8mb4', 'utf8mb4_general_ci'),
        );

        // TableA has collation set at table level and various column collations
        $this->tables['A'] = new Table(
            'foo',
            [],
            [],
            [],
            [],
            ['charset' => 'latin1', 'collation' => 'latin1_swedish_ci'],
        );

        $this->tables['A']->addColumn('json_1', 'json')->setPlatformOption('collation', 'latin1_swedish_ci');
        $this->tables['A']->addColumn('json_2', 'json')->setPlatformOption('collation', 'utf8_general_ci');
        $this->tables['A']->addColumn('json_3', 'json');

        // TableB has no table-level collation and various column collations
        $this->tables['B'] = new Table('foo');
        $this->tables['B']->addColumn('json_1', 'json')->setPlatformOption('collation', 'latin1_swedish_ci');
        $this->tables['B']->addColumn('json_2', 'json')->setPlatformOption('collation', 'utf8_general_ci');
        $this->tables['B']->addColumn('json_3', 'json');

        // Table C has no table-level collation and column collations as MariaDb would return for columns declared
        // as JSON
        $this->tables['C'] = new Table('foo');
        $this->tables['C']->addColumn('json_1', 'json')->setPlatformOption('collation', 'utf8mb4_bin');
        $this->tables['C']->addColumn('json_2', 'json')->setPlatformOption('collation', 'utf8mb4_bin');
        $this->tables['C']->addColumn('json_3', 'json')->setPlatformOption('collation', 'utf8mb4_bin');

        // Table D has no table or column collations set
        $this->tables['D'] = new Table('foo');
        $this->tables['D']->addColumn('json_1', 'json');
        $this->tables['D']->addColumn('json_2', 'json');
        $this->tables['D']->addColumn('json_3', 'json');
    }

    /** @return array{string, string}[] */
    public static function providerTableComparisons(): iterable
    {
        return [
            ['A', 'B'],
            ['A', 'C'],
            ['A', 'D'],
            ['B', 'C'],
            ['B', 'D'],
            ['C', 'D'],
        ];
    }

    /** @dataProvider providerTableComparisons */
    public function testJsonColumnComparison(string $table1, string $table2): void
    {
        self::assertTrue(
            $this->comparator->compareTables($this->tables[$table1], $this->tables[$table2])->isEmpty(),
            sprintf('Tables %s and %s should be identical', $table1, $table2),
        );
    }
}
