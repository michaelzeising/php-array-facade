<?php

use MichaelZeising\Language\ArrayFacade as A;
use PHPUnit\Framework\TestCase;

class ArrayFacadeTest extends TestCase
{
    function testSortBy(): void
    {
        $a = A::of([['id' => 2], ['id' => 1]]);
        $b = $a->sortBy('id');
        $this->assertEquals(2, $a[0]['id']);
        $this->assertEquals(1, $b[0]['id']);
    }

    function testRef(): void
    {
        $a = A::of([['id' => 1], ['id' => 2]]);

        $b = &$a;

        $b->walk(function (&$bi) {
            $bi['x'] = 3;
        });

        echo 'a=' . $a . "\n";
        echo 'b=' . $b . "\n";
    }

    function testMapValues(): void
    {
        $a = A::of(['a' => 1, 'b' => 2]);
        echo $a->mapValues(
            function ($v, $k) {
                echo "Call ${k} => ${v}\n";
                return $v * $v;
            }
        );
    }

    function testEquals(): void
    {
        self::assertTrue(A::of([1, 2, 3])->equals(A::of([1, 2, 3])));
        self::assertTrue(A::of([['a' => 1], ['b' => 2]])->equals((A::of([['a' => 1], ['b' => 2]]))));
        self::assertFalse(A::of([1, 2, 3])->equals(A::of(['1', 2, 3])));
    }

    function testPaths(): void
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
}