<?php

namespace MichaelZeising\Language;

/**
 * @param string $path
 * @return callable a function that returns the value at path of a given object
 */
function property(string $path): callable {
    return function ($element) use ($path) {
        return $element[$path];                 // TODO support a.b
    };
}

/**
 * @param array $source
 * @return callable a function that performs a partial comparison between a given object and source, returning true if the given object has equivalent property values, else false
 */
function matches(array $source): callable {
    return function ($object) use ($source) {
        foreach ($source as $key => $value) {
            if ($object[$key] != $value) {      // TODO extract comparison
                return false;
            }
        }
        return true;
    };
}
