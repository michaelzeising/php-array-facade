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
 */
class ArrayFacade implements ArrayAccess, JsonSerializable, Countable, IteratorAggregate
{

    /**
     * @param self|array $collection
     * @return self
     */
    static function of($collection): self
    {
        if (is_array($collection)) {
            return new self($collection);
        } else if ($collection instanceof self) {
            return new self($collection->elements);
        } else {
            throw new Error('Unexpected type: ' . gettype($collection));
        }
    }

    static function ofElement($element): self
    {
        return new self([$element]);
    }

    static function ofEmpty(): self
    {
        return new self([]);
    }

    /**
     * Wrap a reference to the array
     *
     * @param $collection
     * @return self
     */
    static function ofReference(&$collection): self
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
    function map($iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = property($iteratee);
        } else if (!is_callable($iteratee)) {
            throw new Error();
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
    function flatMap($iteratee): self
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
    function mapValues($iteratee): self
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
     * @param self $other
     * @return self
     */
    function intersection(ArrayFacade $other): self
    {
        return new self(array_intersect($this->elements, $other->elements));
    }

    /**
     * @param self $other
     * @return self
     */
    function difference(ArrayFacade $other): self
    {
        /*
         * array_diff() alone would preserve the keys, which is a bit unexpected in most cases
         */
        return new self(array_values(array_diff($this->elements, $other->elements)));
    }

    /**
     * @param self $other
     * @param callable $comparator
     * @return self
     */
    function differenceWith(ArrayFacade $other, callable $comparator): self
    {
        // TODO do we need array_values() here?
        return new self(array_values(array_udiff($this->elements, $other->elements, $comparator)));
    }

    /**
     * @param callable $iteratee
     * @return $this
     */
    function walk(callable $iteratee): self
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
    function uniq(): self
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
    function uniqBy($iteratee): self
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
     * @return string
     */
    function join(string $glue): string
    {
        return join($glue, $this->elements);
    }

    /**
     * @param callable|array|string $predicate
     * @return Optional
     * @see https://lodash.com/docs/4.17.11#find
     */
    function find($predicate): Optional
    {
        if (is_array($predicate)) {
            $predicate = matches($predicate);
        } else if (is_string($predicate)) {
            $predicate = property($predicate);
        } else if (!is_callable($predicate)) {
            throw new Error();
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
     * @see https://lodash.com/docs/4.17.11#some
     */
    function some($predicate): bool
    {
        if (is_array($predicate)) {
            $predicate = matches($predicate);
        } else if (is_string($predicate)) {
            $predicate = property($predicate);
        } else if (!is_callable($predicate)) {
            throw new Error();
        }
        foreach ($this->elements as $element) {
            if ($predicate($element)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Die Methode kann in PHP mit der list-Syntax aufgerufen werden:
     *
     * list($positive, $negative) = $a->partition(...);
     *
     * @param callable|string|array $predicate
     * @return self
     * @see https://lodash.com/docs/4.17.11#partition
     */
    function partition($predicate): self
    {
        if (is_array($predicate)) {
            $predicate = matches($predicate);
        } else if (is_string($predicate)) {
            $predicate = property($predicate);
        } else if (!is_callable($predicate)) {
            throw new Error();
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
     * @see https://lodash.com/docs/4.17.11#sortBy
     */
    function sortBy(...$iteratees): self
    {
        $iteratees = self::of($iteratees)->map(function ($it) {
            if (is_string($it)) {
                return property($it);
            } else if (is_callable($it)) {
                return $it;
            }
            throw new Error('Typ nicht zulässig für sortBy: ' . gettype($it));
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
     * @see https://lodash.com/docs/4.17.11#filter
     */
    function filter($predicate): self
    {
        if (is_array($predicate)) {
            $predicate = matches($predicate);
        } else if (is_string($predicate)) {
            $predicate = property($predicate);
        } else if (!is_callable($predicate)) {
            throw new Error();
        }
        /*
         * array_filter() behält die Schlüssel bei, was zu unerwarteten Ergebnissen führt
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
    function groupByRecursive(string $idKey, string $parentIdKey, string $childrenKey = 'children', $parentIdValue = null): self
    {
        // direkte Kinder und andere trennen
        list($children, $notChildren) = $this->partition([$parentIdKey => $parentIdValue]);

        // direkte Kinder rekursiv behandeln
        $children->walk(function (&$child) use ($childrenKey, $notChildren, $parentIdKey, $idKey) {
            $child[$childrenKey] = $notChildren->groupByRecursive($idKey, $parentIdKey, $childrenKey, $child[$idKey]);
        });

        // direkt Kinder zurückgeben
        return $children;
    }

    /**
     * Liste in einen Baum umwandeln
     *
     * ACHTUNG: die Ebene sind wieder Objekte von ArrayFacade!
     *
     * @param string $idKey
     * @param string $parentIdKey
     * @param string $childrenKey
     * @return self
     */
    function toTree(string $idKey, string $parentIdKey, string $childrenKey = 'children'): self
    {
        // Wurzel(n) finden
        $rootIds = $this->map($parentIdKey)->uniq()->difference($this->map($idKey)->uniq());
        // Kinder rekursiv gruppieren
        if ($rootIds->count() == 1) {
            return $this->groupByRecursive($idKey, $parentIdKey, $childrenKey, $rootIds->head()->get());
        } else if ($rootIds->count() > 1) {
            return $rootIds->map(function ($rootId) use ($idKey, $parentIdKey, $childrenKey) {
                return $this->groupByRecursive($idKey, $parentIdKey, $childrenKey, $rootId);
            });
        } else {
            throw new Error('Keine Wurzel(n)');
        }
    }

    /**
     * @return Optional
     * @see https://lodash.com/docs/4.17.11#head
     */
    function head(): Optional
    {
        if ($this->isEmpty()) {
            return Optional::empty();
        }
        return Optional::of($this->elements[0]);
    }

    /**
     * @param array $values
     * @return self
     * @see https://lodash.com/docs/4.17.11#concat
     */
    function concat(... $values): self
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
     * @see https://lodash.com/docs/4.17.11#includes
     */
    function includes($value): bool
    {
        return in_array($value, $this->elements, false);
    }

    /**
     * @param callable|string $iteratee
     * @return self
     * @see https://lodash.com/docs/4.17.11#groupBy
     */
    function groupBy($iteratee, $removeKey = false): self
    {
        if (is_string($iteratee)) {
            $iteratee = property($iteratee);
        } else if (!is_callable($iteratee)) {
            throw new Error();
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
     * @see https://lodash.com/docs/4.17.11#keyBy
     */
    function keyBy($iteratee): self
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
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    function offsetExists($offset): bool
    {
        return isset($this->elements[$offset]);
    }

    /**
     * Offset to retrieve
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    function offsetGet($offset)
    {
        return $this->offsetExists($offset)
            ? $this->elements[$offset]
            : null;
    }

    /**
     * Offset to set
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    function offsetSet($offset, $value): void
    {
        if (isset($offset)) {
            $this->elements[$offset] = $value;
        } else {
            $this->elements[] = $value;
        }
    }

    /**
     * Offset to unset
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    function offsetUnset($offset): void
    {
        unset($this->elements[$offset]);
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        return $this->elements;
    }

    /**
     * Count elements of an object
     * @link https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    function count(): int
    {
        return count($this->elements);
    }

    /**
     * @return bool
     */
    function isEmpty(): bool
    {
        return $this->count() == 0;
    }

    /**
     * Retrieve an external iterator
     * @link https://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    function getIterator(): Traversable
    {
        return new \ArrayIterator($this->elements);
    }

    /**
     * @param $key
     * @return bool
     * @see https://docs.oracle.com/javase/8/docs/api/java/util/Map.html#containsKey-java.lang.Object-
     */
    function containsKey($key): bool
    {
        return array_key_exists($key, $this->elements);
    }

    function toArray(): array
    {
        return $this->elements;
    }

    /**
     * @return array Referenz auf die Elemente, um sie in-place verändern zu können
     */
    function &toArrayReference()
    {
        return $this->elements;
    }

    function __toString(): string
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }
}