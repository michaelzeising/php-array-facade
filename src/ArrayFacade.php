<?php

namespace MichaelZeising\Language;

use ArrayAccess;
use ArrayObject;
use Countable;
use Error;
use IteratorAggregate;
use JJWare\Util\Optional;
use JsonSerializable;
use Traversable;

/**
 * Wrap PHP's built-in array functions, extend them and support a function, object-oriented style.
 *
 * https://github.com/voku/Arrayy and https://github.com/bocharsky-bw/Arrayzy lack keyBy(),
 * groupBy(), map() etc.
 * https://github.com/me-io/php-lodash and https://github.com/lodash-php/lodash-php lack the oo style
 *
 * IMPORTANT: empty() cannot be called on instances of ArrayFacade
 */
class ArrayFacade implements ArrayAccess, JsonSerializable, Countable, IteratorAggregate
{

    /**
     * @param ArrayFacade|array $collection
     * @return ArrayFacade
     */
    public static function of($collection): self
    {
        if (is_array($collection)) {
            return new self($collection);
        } else if ($collection instanceof self) {
            return new self($collection->elements);
        } else {
            throw new Error('Expected array or ArrayFacade but got ' . gettype($collection));
        }
    }

    public static function ofElement($element): self
    {
        return new self([$element]);
    }

    public static function ofEmpty(): self
    {
        return new self([]);
    }

    /**
     * Wrap a reference to the array
     *
     * @param $collection
     * @return self
     */
    public static function ofReference(&$collection): self
    {
        $c = new self(null);    // a bit strange but we need the constructor because call new self(...) a lot
        $c->setCollectionFromReference($collection);
        return $c;
    }

    /**
     * @var array
     */
    private $elements;

    private function __construct(array $elements)
    {
        $this->elements = $elements;
    }

    /**
     * Replace the wrapped array by a reference to an array
     *
     * @param array $elements
     */
    private function setCollectionFromReference(array &$elements)
    {
        $this->elements = $elements;
    }

    /**
     * @param callable|string $iteratee function or 'property' shorthand
     * @return self
     */
    public function map($iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = property($iteratee);
        } else if (!is_callable($iteratee)) {
            throw new Error('Expected string or callable but got '.gettype($iteratee));
        }
        /*
         * $iteratee is invoked with (value, index|key), when array_keys(...) is passed to array_map()
         *
         * IMPORTANT: when passing more than one array to array_map(), the returned array always has sequential integer keys!
         */
        return new self(array_map($iteratee, $this->elements, array_keys($this->elements)));
    }

    /**
     * @param $iteratee callable|string function or property shorthand
     * @return self
     */
    public function flatMap($iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = property($iteratee);
        } else if (!is_callable($iteratee)) {
            throw new Error();
        }
        $flattened = [];
        foreach ($this->elements as $key => $value) {
            $result = $iteratee($value, $key, $this->elements);
            if (is_array($result) || $result instanceof Traversable || $result instanceof self) {
                foreach ($result as $item) {
                    $flattened[] = $item;
                }
            } elseif ($result !== null) {
                $flattened[] = $result;
            }
        }
        return new self($flattened);
    }

    /**
     * @param $iteratee callable|string function or property shorthand
     * @return self
     */
    public function mapValues($iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = property($iteratee);
        } else if (!is_callable($iteratee)) {
            throw new Error();
        }
        /*
         * we can't use array_map() here, because it would return sequential integer keys when passing the keys as a
         * further array (see map())
         */
        $result = [];
        foreach ($this->elements as $key => $value) {
            $result[$key] = $iteratee($value, $key, $this->elements);
        }
        return new self($result);
    }

    /**
     * @param ArrayFacade $other
     * @return self
     */
    public function intersection(ArrayFacade $other): self
    {
        return new self(array_intersect($this->elements, $other->elements));
    }

    /**
     * @param ArrayFacade $other
     * @return ArrayFacade
     */
    public function difference(ArrayFacade $other): self
    {
        /*
         * array_diff() alone would preserve the keys, which is a bit unexpected in most cases
         */
        return new self(array_values(array_diff($this->elements, $other->elements)));
    }

    /**
     * @param ArrayFacade $other
     * @param callable $comparator
     * @return ArrayFacade
     */
    public function differenceWith(ArrayFacade $other, callable $comparator): self
    {
        // TODO do we need array_values() here?
        return new self(array_values(array_udiff($this->elements, $other->elements, $comparator)));
    }

    /**
     * @param callable $iteratee
     * @return $this
     */
    public function walk(callable $iteratee): self
    {
        /*
         * array_walk() invokes $iteratee with (value, index|key)
         */
        array_walk($this->elements, $iteratee);
        return $this;
    }

    /**
     * @return self
     */
    public function uniq(): self
    {
        $uniq = self::ofEmpty();
        foreach ($this->elements as $element) {
            if (!$uniq->includes($element)) {
                $uniq[] = $element;
            }
        }
        return $uniq;
    }

    /**
     * @param callable|string $iteratee
     * @return self
     */
    public function uniqBy($iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = property($iteratee);
        } else if (!is_callable($iteratee)) {
            throw new Error();
        }
        $uniq = self::ofEmpty();
        foreach ($this->elements as $element) {
            $elementResult = $iteratee($element);
            $includedInUniq = $uniq->some(function ($uniqElement) use ($iteratee, $elementResult) {
                return $iteratee($uniqElement) == $elementResult;
            });
            if (!$includedInUniq) {
                $uniq[] = $element;
            }
        }
        return $uniq;
    }

    /**
     * @param string $glue
     * @param string|null $lastGlue possibly different last glue
     * @return string
     */
    public function join(string $glue, string $lastGlue = null): string
    {
        if ($lastGlue === null) {
            return join($glue, $this->elements);
        } else {
            $s = '';
            $n = count($this->elements);
            for ($i = 0; $i < $n; $i++) {
                $s .= $this->elements[$i];
                if ($i === $n - 2) {
                    $s .= $lastGlue;
                } else if ($i < $n - 2) {
                    $s .= $glue;
                }
            }
            return $s;
        }
    }

    /**
     * @param callable|array|string $predicate
     * @return Optional
     */
    public function find($predicate): Optional
    {
        if (is_array($predicate)) {
            $predicate = matches($predicate);
        } else if (is_string($predicate)) {
            $predicate = property($predicate);
        } else if (!is_callable($predicate)) {
            throw new Error('Expected array, string or callable but got '.gettype($predicate));
        }
        foreach ($this->elements as $element) {
            if ($predicate($element)) {
                return Optional::of($element);
            }
        }
        return Optional::empty();
    }

    /**
     * @param callable|array|string $predicate
     * @return bool
     */
    public function some($predicate): bool
    {
        if (is_array($predicate)) {
            $predicate = matches($predicate);
        } else if (is_string($predicate)) {
            $predicate = property($predicate);
        } else if (!is_callable($predicate)) {
            throw new Error('Expected array, string or callable but got '.gettype($predicate));
        }
        foreach ($this->elements as $element) {
            if ($predicate($element)) {
                return true;
            }
        }
        return false;
    }

    /**
     * This may be called in PHP list syntax:
     *
     * list($positive, $negative) = $a->partition(...);
     *
     * @param callable|string|array $predicate
     * @return self
     */
    public function partition($predicate): self
    {
        if (is_array($predicate)) {
            $predicate = matches($predicate);
        } else if (is_string($predicate)) {
            $predicate = property($predicate);
        } else if (!is_callable($predicate)) {
            throw new Error('Expected array, string or callable but got '.gettype($predicate));
        }

        $positive = [];
        $negative = [];
        foreach ($this->elements as $element) {
            if ($predicate($element)) {
                $positive[] = $element;
            } else {
                $negative[] = $element;
            }
        }
        return new self([new self($positive), new self($negative)]);
    }

    /**
     * @param callable|string ...$iteratees
     * @return self
     */
    public function sortBy(...$iteratees): self
    {
        $iteratees = self::of($iteratees)->map(function ($it) {
            if (is_string($it)) {
                return property($it);
            } else if (is_callable($it)) {
                return $it;
            }
            throw new Error('Expected string or callable but got '.gettype($it));
        });
        $elements = (new ArrayObject($this->elements))->getArrayCopy();
        usort($elements, function ($l, $r) use ($iteratees) {
            foreach ($iteratees as $iteratee) {
                if ($iteratee($l) == $iteratee($r)) {
                    continue;
                } else if ($iteratee($l) > $iteratee($r)) {
                    return 1;
                } else if ($iteratee($l) < $iteratee($r)) {
                    return -1;
                }
            }
            return 0;
        });
        return self::of($elements);
    }

    /**
     * @param callable|array|string $predicate
     * @return self
     */
    public function filter($predicate): self
    {
        if (is_array($predicate)) {
            $predicate = matches($predicate);
        } else if (is_string($predicate)) {
            $predicate = property($predicate);
        } else if (!is_callable($predicate)) {
            throw new Error();
        }
        /*
         * array_filter() retains the keys which is not expected in most cases
         */
        return new self(array_values(array_filter($this->elements, $predicate)));
    }

    /**
     * @param string $idKey
     * @param string $parentIdKey
     * @param string $childrenKey
     * @param null|mixed $parentIdValue
     * @return self
     */
    public function groupByRecursive(string $idKey, string $parentIdKey, string $childrenKey = 'children', $parentIdValue = null): self
    {
        // separate direct children from others
        list($children, $notChildren) = $this->partition([$parentIdKey => $parentIdValue]);

        // handle direct children recursively
        $children->walk(function (&$child) use ($childrenKey, $notChildren, $parentIdKey, $idKey) {
            $child[$childrenKey] = $notChildren->groupByRecursive($idKey, $parentIdKey, $childrenKey, $child[$idKey]);
        });

        // return direct children
        return $children;
    }

    /**
     * Convert list to tree
     *
     * IMPORTANT: resulting levels are instances of ArrayFacade
     *
     * @param string $idKey
     * @param string $parentIdKey
     * @param string $childrenKey
     * @return self
     */
    public function toTree(string $idKey, string $parentIdKey, string $childrenKey = 'children'): self
    {
        // find root(s)
        $rootIds = $this->map($parentIdKey)->uniq()->difference($this->map($idKey)->uniq());
        // group children recursively
        if ($rootIds->count() == 1) {
            return $this->groupByRecursive($idKey, $parentIdKey, $childrenKey, $rootIds->head()->get());
        } else if ($rootIds->count() > 1) {
            return $rootIds->map(function ($rootId) use ($idKey, $parentIdKey, $childrenKey) {
                return $this->groupByRecursive($idKey, $parentIdKey, $childrenKey, $rootId);
            });
        } else {
            throw new Error('No roots found');
        }
    }

    /**
     * @return Optional
     */
    public function head(): Optional
    {
        if ($this->isEmpty()) {
            return Optional::empty();
        }
        return Optional::of($this->elements[0]);
    }

    /**
     * @param array $values
     * @return self
     */
    public function concat(... $values): self
    {
        $result = $this->elements;
        foreach ($values as $value) {
            if (is_array($value)) {
                $result = array_merge($result, $value);
            } else if ($value instanceof self) {
                $result = array_merge($result, $value->elements);
            } else {
                $result[] = $value;
            }
        }
        return new self($result);
    }

    /**
     * @param $value
     * @return bool
     */
    public function includes($value): bool
    {
        return in_array($value, $this->elements, false);
    }

    /**
     * @param callable|string $iteratee
     * @return self
     */
    public function groupBy($iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = property($iteratee);
        } else if (!is_callable($iteratee)) {
            throw new Error('Expected string or callable but got '.gettype($iteratee));
        }
        $result = [];
        foreach ($this->elements as $element) {
            $key = $iteratee($element);
            if (array_key_exists($key, $result)) {
                $result[$key][] = $element;
            } else {
                $result[$key] = new self([$element]);
            }
        }
        return new self($result);
    }

    /**
     * The corresponding value of each key is the last element responsible for generating the key.
     *
     * @param callable|string $iteratee
     * @return ArrayFacade
     */
    public function keyBy($iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = property($iteratee);
        } else if (!is_callable($iteratee)) {
            throw new Error();
        }
        $result = [];
        foreach ($this->elements as $element) {
            $result[$iteratee($element)] = $element;
        }
        return new self($result);
    }

    /**
     * Whether a offset exists
     * @param mixed $offset
     * @return boolean true on success or false on failure
     */
    public function offsetExists($offset): bool
    {
        return isset($this->elements[$offset]);
    }

    /**
     * Offset to retrieve
     * @param mixed $offset
     * @return mixed Can return all value types
     */
    public function offsetGet($offset)
    {
        return $this->offsetExists($offset)
            ? $this->elements[$offset]
            : null;
    }

    /**
     * Offset to set
     * @param mixed $offset The offset to assign the value to
     * @param mixed $value The value to set
     */
    public function offsetSet($offset, $value): void
    {
        if (isset($offset)) {
            $this->elements[$offset] = $value;
        } else {
            $this->elements[] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->elements[$offset]);
    }

    public function jsonSerialize()
    {
        return $this->elements;
    }

    public function count(): int
    {
        return count($this->elements);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() == 0;
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->elements);
    }

    /**
     * @param $key
     * @return bool
     */
    public function containsKey($key): bool
    {
        return array_key_exists($key, $this->elements);
    }

    /**
     * @param ArrayFacade $other
     * @return bool whether the wrapped elements equal the other's by identity (===)
     */
    public function equals(ArrayFacade $other): bool {
        $n = $this->count();
        if ($n !== $other->count()) {
            return false;
        }
        while ($n > 0) {
            if ($this->elements[--$n] !== $other->elements[$n]) {
                return false;
            }
        }
        return true;
    }

    public function toArray(): array
    {
        return $this->elements;
    }

    /**
     * @return array reference to the elements for manipulating them in-place
     */
    public function &toArrayReference()
    {
        return $this->elements;
    }

    public function __toString(): string
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }
}