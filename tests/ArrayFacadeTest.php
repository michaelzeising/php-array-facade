<?php

use MichaelZeising\Language\ArrayFacade as A;
use PHPUnit\Framework\TestCase;

class ArrayFacadeTest extends TestCase
{
    public function testSortBy(): void
    {
        $a = A::of([['id' => 2], ['id' => 1]]);
        $b = $a->sortBy('id');
        $this->assertEquals(2, $a[0]['id']);
        $this->assertEquals(1, $b[0]['id']);
    }

    public function testRef(): void
    {
        $a = A::of([['id' => 1], ['id' => 2]]);

        $b = &$a;

        $b->walk(function (&$bi) {
            $bi['x'] = 3;
        });

        // TODO test
        echo 'a=' . $a . "\n";
        echo 'b=' . $b . "\n";
    }

    public function testMapValues(): void
    {
        $a = A::of(['a' => 1, 'b' => 2]);
        // TODO test
        echo $a->mapValues(
            function ($v, $k) {
                echo "Call ${k} => ${v}\n";
                return $v * $v;
            }
        );
    }

    public function testEquals(): void
    {
        self::assertTrue(A::of([1, 2, 3])->equals(A::of([1, 2, 3])));
        self::assertTrue(A::of([['a' => 1], ['b' => 2]])->equals((A::of([['a' => 1], ['b' => 2]]))));
        self::assertFalse(A::of([1, 2, 3])->equals(A::of(['1', 2, 3])));
    }

    public function testPaths(): void
    {
        self::assertTrue(
            A::of([['a' => 1], ['a' => 2]])
                ->map('a')
                ->equals(A::of([1, 2])));
        self::assertTrue(
            A::of([['a' => ['b' => 1]], ['a' => ['b' => 2]]])
                ->map('a.b')
                ->equals(A::of([1, 2])));
        self::assertTrue(
            A::of([['a' => ['b' => ['c' => 1]]], ['a' => ['b' => ['c' => 2]]]])
                ->map('a.b.c')
                ->equals(A::of([1, 2])));
    }

    public function testToTree(): void {
        $a = A::of([
            ['id' => 1, 'parent_id' => null],
            ['id' => 2, 'parent_id' => null],
            ['id' => 3, 'parent_id' => 1],
            ['id' => 4, 'parent_id' => 1],
            ['id' => 5, 'parent_id' => 2]
        ]);
        print_r($a->toTree('id', 'parent_id', 'children'));
    }

    public function testGroupByObject(): void {
        $a = A::of([
            ['id' => 'o1', 'type' => ['id' => 't1']],
            ['id' => 'o2', 'type' => ['id' => 't1']],
            ['id' => 'o3', 'type' => ['id' => 't2']],
            ['id' => 'o4', 'type' => null],
            ['id' => 'o5'],
        ])->groupByObject('type', 'id', 'objects');
        print_r($a);
    }
}