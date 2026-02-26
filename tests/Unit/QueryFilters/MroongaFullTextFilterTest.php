<?php

namespace Tests\Unit\QueryFilters;

use App\Models\Ledger;
use App\QueryFilters\MroongaFullTextFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(MroongaFullTextFilter::class)]
class MroongaFullTextFilterTest extends TestCase
{
    // MroongaFullTextFilter は Eloquent\Builder を受け取る
    // Ledger::query() で Eloquent\Builder を生成し、toSql() / getBindings() で検証する

    public function test_single_keyword_generates_correct_match_against(): void
    {
        $filter = new MroongaFullTextFilter(['title']);
        $query = Ledger::query();

        ($filter)($query, '東京', 'title');

        $sql = $query->toSql();
        $this->assertStringContainsString('match(`title`) against', $sql);
        $this->assertStringContainsString('BOOLEAN MODE', $sql);
        $this->assertContains('+東京', $query->getBindings());
    }

    public function test_multiple_keywords_are_and_searched(): void
    {
        $filter = new MroongaFullTextFilter(['title']);
        $query = Ledger::query();

        ($filter)($query, '東京 大阪', 'title');

        $bindings = $query->getBindings();
        $this->assertCount(1, $bindings);
        $this->assertEquals('+東京 +大阪', $bindings[0]);
    }

    public function test_comma_separated_keywords_are_split(): void
    {
        $filter = new MroongaFullTextFilter(['title']);
        $query = Ledger::query();

        ($filter)($query, 'a,b', 'title');

        $this->assertEquals('+a +b', $query->getBindings()[0]);
    }

    public function test_mixed_space_and_comma_are_split(): void
    {
        $filter = new MroongaFullTextFilter(['title']);
        $query = Ledger::query();

        ($filter)($query, 'a, b c', 'title');

        $this->assertEquals('+a +b +c', $query->getBindings()[0]);
    }

    public function test_multiple_columns_are_joined_with_backticks(): void
    {
        $filter = new MroongaFullTextFilter(['title', 'body']);
        $query = Ledger::query();

        ($filter)($query, 'test', 'q');

        $sql = $query->toSql();
        $this->assertStringContainsString('match(`title`, `body`) against', $sql);
    }

    public function test_empty_value_does_not_add_where_clause(): void
    {
        $filter = new MroongaFullTextFilter(['title']);
        $query = Ledger::query();
        $original = $query->toSql();

        ($filter)($query, '', 'title');

        $this->assertEquals($original, $query->toSql());
    }

    public function test_whitespace_only_does_not_add_where_clause(): void
    {
        $filter = new MroongaFullTextFilter(['title']);
        $query = Ledger::query();
        $original = $query->toSql();

        ($filter)($query, '   ', 'title');

        $this->assertEquals($original, $query->toSql());
    }
}
