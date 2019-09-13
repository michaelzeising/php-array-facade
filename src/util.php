<?php

namespace MichaelZeising\Language;

/**
 * @param string $path
 * @return callable a function that returns the value at path of a given object
 */
function property(string $path): callable {
    $pathElements = explode('.', $path);
    return function ($element) use ($pathElements) {
        $val = $element;
        foreach ($pathElements as $pathElement) {
            $val = $val[$pathElement];
        }
        return $val;
    };
}

/**
 * @param array $source
 * @return callable a function that performs a partial comparison between a given object and source, returning true if the given object has equivalent property values, else false
 */
function matches(array $source): callable {
    return function ($object) use ($source) {
        foreach ($source as $key => $value) {
            if ($object[$key] != $value) {      // TODO don't use fixed comparison
                return false;
            }
        }
        return true;
    };
}
