# php-array-facade

Wraps PHP's built-in array functions, extends them and supports a function, object-oriented style. Most of the functionl elements are inspired by the [Lodash](https://www.lodash.com) library.

## Why another library?

* https://github.com/voku/Arrayy and https://github.com/bocharsky-bw/Arrayzy lack keyBy(), groupBy(), map() and so on
* https://github.com/me-io/php-lodash and https://github.com/lodash-php/lodash-php don't support the OO style 

## Known limitations
* empty() cannot be called for objects of ```ArrayFacade```
