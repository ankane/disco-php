# Disco PHP

:fire: Recommendations for PHP using collaborative filtering

- Supports user-based and item-based recommendations
- Works with explicit and implicit feedback
- Uses high-performance matrix factorization

[![Build Status](https://github.com/ankane/disco-php/workflows/build/badge.svg?branch=master)](https://github.com/ankane/disco-php/actions)

## Installation

Run:

```sh
composer require ankane/disco
```

## Getting Started

Create a recommender

```php
$recommender = new Disco\Recommender();
```

If users rate items directly, this is known as explicit feedback. Fit the recommender with:

```php
$recommender->fit([
    ['user_id' => 1, 'item_id' => 1, 'rating' => 5],
    ['user_id' => 2, 'item_id' => 1, 'rating' => 3]
]);
```

> IDs can be integers or strings

If users don’t rate items directly (for instance, they’re purchasing items or reading posts), this is known as implicit feedback. Leave out the rating.

```php
$recommender->fit([
    ['user_id' => 1, 'item_id' => 1],
    ['user_id' => 2, 'item_id' => 1]
]);
```

> Each `user_id`/`item_id` combination should only appear once

Get user-based recommendations - “users like you also liked”

```php
$recommender->userRecs($userId);
```

Get item-based recommendations - “users who liked this item also liked”

```php
$recommender->itemRecs($itemId);
```

Use the `count` option to specify the number of recommendations (default is 5)

```php
$recommender->userRecs($userId, count: 3);
```

Get predicted ratings for specific users and items

```php
$recommender->predict([['user_id' => 1, 'item_id' => 2], ['user_id' => 2, 'item_id' => 4]]);
```

Get similar users

```php
$recommender->similarUsers($userId);
```

## Examples

### MovieLens

Load the data

```php
$data = Disco\Data::loadMovieLens();
```

Create a recommender and get similar movies

```php
$recommender = new Disco\Recommender(factors: 20);
$recommender->fit($data);
$recommender->itemRecs('Star Wars (1977)');
```

## Algorithms

Disco uses high-performance matrix factorization.

- For explicit feedback, it uses [stochastic gradient descent](https://www.csie.ntu.edu.tw/~cjlin/papers/libmf/libmf_journal.pdf)
- For implicit feedback, it uses [coordinate descent](https://www.csie.ntu.edu.tw/~cjlin/papers/one-class-mf/biased-mf-sdm-with-supp.pdf)

Specify the number of factors and epochs

```php
new Disco\Recommender(factors: 8, epochs: 20);
```

If recommendations look off, trying changing `factors`. The default is 8, but 3 could be good for some applications and 300 good for others.

## Validation

Pass a validation set with:

```php
$recommender->fit($data, validationSet: $validationSet);
```

## Cold Start

Collaborative filtering suffers from the [cold start problem](https://en.wikipedia.org/wiki/Cold_start_(recommender_systems)). It’s unable to make good recommendations without data on a user or item, which is problematic for new users and items.

```php
$recommender->userRecs($newUserId); // returns empty array
```

There are a number of ways to deal with this, but here are some common ones:

- For user-based recommendations, show new users the most popular items.
- For item-based recommendations, make content-based recommendations.

## Reference

Get ids

```php
$recommender->userIds();
$recommender->itemIds();
```

Get the global mean

```php
$recommender->globalMean();
```

Get factors

```php
$recommender->userFactors($userId);
$recommender->itemFactors($itemId);
```

## Credits

Thanks to [LIBMF](https://github.com/cjlin1/libmf) for providing high performance matrix factorization

## History

View the [changelog](https://github.com/ankane/disco-php/blob/master/CHANGELOG.md)

## Contributing

Everyone is encouraged to help improve this project. Here are a few ways you can help:

- [Report bugs](https://github.com/ankane/disco-php/issues)
- Fix bugs and [submit pull requests](https://github.com/ankane/disco-php/pulls)
- Write, clarify, or fix documentation
- Suggest or add new features

To get started with development:

```sh
git clone https://github.com/ankane/disco-php.git
cd disco-php
composer install
composer test
```
