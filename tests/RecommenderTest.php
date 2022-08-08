<?php

use PHPUnit\Framework\TestCase;

final class RecommenderTest extends TestCase
{
    public function testExplicit()
    {
        $data = Disco\Data::loadMovieLens();
        $recommender = new Disco\Recommender(factors: 20);
        $recommender->fit($data);

        $expected = array_sum(array_map(fn ($v) => $v['rating'], $data)) / count($data);
        $this->assertEqualsWithDelta($expected, $recommender->globalMean(), 0.001);

        $recs = $recommender->itemRecs('Star Wars (1977)');
        $this->assertCount(5, $recs);

        $itemIds = array_map(fn ($r) => $r['item_id'], $recs);
        $this->assertContains('Empire Strikes Back, The (1980)', $itemIds);
        $this->assertContains('Return of the Jedi (1983)', $itemIds);
        $this->assertNotContains('Star Wars (1977)', $itemIds);

        $this->assertEqualsWithDelta(0.9972, $recs[0]['score'], 0.01);

        $this->assertCount(1663, $recommender->itemRecs('Star Wars (1977)', count: null));
        $this->assertCount(942, $recommender->similarUsers(1, count: null));
    }

    public function testImplicit()
    {
        $data = Disco\Data::loadMovieLens();
        foreach ($data as &$r) {
            unset($r['rating']);
        }

        $recommender = new Disco\Recommender(factors: 20);
        $recommender->fit($data);

        $this->assertEqualsWithDelta(0, $recommender->globalMean(), 0.001);

        $recs = array_map(fn ($r) => $r['item_id'], $recommender->itemRecs('Star Wars (1977)', count: 10));
        $this->assertContains('Empire Strikes Back, The (1980)', $recs);
        $this->assertContains('Return of the Jedi (1983)', $recs);
        $this->assertNotContains('Star Wars (1977)', $recs);
    }

    public function testExamples()
    {
        $recommender = new Disco\Recommender();
        $recommender->fit([
            ['user_id' => 1, 'item_id' => 1, 'rating' => 5],
            ['user_id' => 2, 'item_id' => 1, 'rating' => 3]
        ]);
        $recommender->userRecs(1);
        $recommender->itemRecs(1);

        $recommender = new Disco\Recommender();
        $recommender->fit([
            ['user_id' => 1, 'item_id' => 1],
            ['user_id' => 2, 'item_id' => 1]
        ]);
        $recommender->userRecs(1);
        $recommender->itemRecs(1);

        $this->assertTrue(true);
    }

    public function testRated()
    {
        $data = [
            ['user_id' => 1, 'item_id' => 'A'],
            ['user_id' => 1, 'item_id' => 'B'],
            ['user_id' => 1, 'item_id' => 'C'],
            ['user_id' => 1, 'item_id' => 'D'],
            ['user_id' => 2, 'item_id' => 'C'],
            ['user_id' => 2, 'item_id' => 'D'],
            ['user_id' => 2, 'item_id' => 'E'],
            ['user_id' => 2, 'item_id' => 'F']
        ];
        $recommender = new Disco\Recommender();
        $recommender->fit($data);
        $this->assertEquals(['E', 'F'], $this->sort(array_map(fn ($r) => $r['item_id'], $recommender->userRecs(1))));
        $this->assertEquals(['A', 'B'], $this->sort(array_map(fn ($r) => $r['item_id'], $recommender->userRecs(2))));
    }

    public function testItemRecsSameScore()
    {
        $data = [
            ['user_id' => 1, 'item_id' => 'A'],
            ['user_id' => 1, 'item_id' => 'B'],
            ['user_id' => 2, 'item_id' => 'C']
        ];
        $recommender = new Disco\Recommender();
        $recommender->fit($data);
        $this->assertEquals(['B', 'C'], array_map(fn ($r) => $r['item_id'], $recommender->itemRecs('A')));
    }

    public function testSimilarUsers()
    {
        $data = Disco\Data::loadMovieLens();
        $recommender = new Disco\Recommender(factors: 20);
        $recommender->fit($data);

        $this->assertNotEmpty($recommender->similarUsers($data[0]['user_id']));
        $this->assertEmpty($recommender->similarUsers('missing'));
    }

    public function testIds()
    {
        $data = [
            ['user_id' => 1, 'item_id' => 'A'],
            ['user_id' => 1, 'item_id' => 'B'],
            ['user_id' => 2, 'item_id' => 'B']
        ];
        $recommender = new Disco\Recommender();
        $recommender->fit($data);
        $this->assertEquals([1, 2], $recommender->userIds());
        $this->assertEquals(['A', 'B'], $recommender->itemIds());
    }

    public function testFactors()
    {
        $data = [
            ['user_id' => 1, 'item_id' => 'A'],
            ['user_id' => 1, 'item_id' => 'B'],
            ['user_id' => 2, 'item_id' => 'B']
        ];
        $recommender = new Disco\Recommender(factors: 20);
        $recommender->fit($data);

        $this->assertCount(20, $recommender->userFactors(1));
        $this->assertCount(20, $recommender->itemFactors('A'));

        $this->assertNull($recommender->userFactors(3));
        $this->assertNull($recommender->itemFactors('C'));
    }

    public function testValidationSetExplicit()
    {
        $data = Disco\Data::loadMovieLens();
        $trainSet = array_slice($data, 0, 80000);
        $validationSet = array_slice($data, 80000);
        $recommender = new Disco\Recommender(factors: 20, verbose: false);
        $recommender->fit($trainSet, validationSet: $validationSet);
        $this->assertNotEqualsWithDelta(0, $recommender->globalMean(), 0.001);
    }

    public function testValidationSetImplicit()
    {
        $data = Disco\Data::loadMovieLens();
        foreach ($data as &$r) {
            unset($r['rating']);
        }
        $trainSet = array_slice($data, 0, 80000);
        $validationSet = array_slice($data, 80000);
        $recommender = new Disco\Recommender(factors: 20, verbose: false);
        $recommender->fit($trainSet, validationSet: $validationSet);
        $this->assertEqualsWithDelta(0, $recommender->globalMean(), 0.001);
    }

    public function testUserRecsItemIds()
    {
        $recommender = new Disco\Recommender();
        $recommender->fit([
            ['user_id' => 1, 'item_id' => 1, 'rating' => 5],
            ['user_id' => 1, 'item_id' => 2, 'rating' => 3]
        ]);
        $this->assertEquals([2], array_map(fn ($r) => $r['item_id'], $recommender->userRecs(1, itemIds: [2])));
    }

    public function testUserRecsNewUser()
    {
        $recommender = new Disco\Recommender();
        $recommender->fit([
            ['user_id' => 1, 'item_id' => 1, 'rating' => 5],
            ['user_id' => 1, 'item_id' => 2, 'rating' => 3]
        ]);
        $this->assertEmpty($recommender->userRecs(1000));
    }

    // only return items that exist
    public function testUserRecsNewItem()
    {
        $recommender = new Disco\Recommender();
        $recommender->fit([
            ['user_id' => 1, 'item_id' => 1, 'rating' => 5],
            ['user_id' => 1, 'item_id' => 2, 'rating' => 3]
        ]);
        $this->assertEmpty($recommender->userRecs(1, itemIds: [1000]));
    }

    public function testPredict()
    {
        $data = Disco\Data::loadMovieLens();
        // TODO seed?
        shuffle($data);

        $trainSet = array_slice($data, 0, 80000);
        $validSet = array_slice($data, 80000);

        $recommender = new Disco\Recommender(factors: 20, verbose: false);
        $recommender->fit($trainSet, validationSet: $validSet);

        $predictions = $recommender->predict($validSet);
        $this->assertEqualsWithDelta(0.91, Disco\Metrics::rmse(array_map(fn ($r) => $r['rating'], $validSet), $predictions), 0.015);
    }

    public function testPredictNewUser()
    {
        $data = Disco\Data::loadMovieLens();
        $recommender = new Disco\Recommender(factors: 20);
        $recommender->fit($data);
        $this->assertEquals([$recommender->globalMean()], $recommender->predict([['user_id' => 100000, 'item_id' => 'Star Wars (1977)']]));
    }

    public function testPredictNewItem()
    {
        $data = Disco\Data::loadMovieLens();
        $recommender = new Disco\Recommender(factors: 20);
        $recommender->fit($data);
        $this->assertEquals([$recommender->globalMean()], $recommender->predict([['user_id' => 1, 'item_id' => 'New movie']]));
    }

    public function testPredictUserRecsConsistent()
    {
        $data = Disco\Data::loadMovieLens();
        $recommender = new Disco\Recommender(factors: 20);
        $recommender->fit($data);

        $expected = array_map(fn ($v) => $recommender->userRecs($v['user_id'], itemIds: [$v['item_id']])[0]['score'], array_slice($data, 0, 5));
        $predictions = $recommender->predict(array_slice($data, 0, 5));
        for ($i = 0; $i < 5; $i++) {
            $this->assertEqualsWithDelta($expected[$i], $predictions[$i], 0.001);
        }
    }

    public function testNoTrainingData()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No training data');

        $recommender = new Disco\Recommender();
        $recommender->fit([]);
    }

    public function testMissingUserId()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing user_id');

        $recommender = new Disco\Recommender();
        $recommender->fit([['item_id' => 1, 'rating' => 5]]);
    }

    public function testMissingItemId()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing item_id');

        $recommender = new Disco\Recommender();
        $recommender->fit([['user_id' => 1, 'rating' => 5]]);
    }

    public function testMissingRating()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing rating');

        $recommender = new Disco\Recommender();
        $recommender->fit([['user_id' => 1, 'item_id' => 1, 'rating' => 5], ['user_id' => 1, 'item_id' => 2]]);
    }

    public function testMissingRatingValidationSet()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing rating');

        $recommender = new Disco\Recommender();
        $recommender->fit([['user_id' => 1, 'item_id' => 1, 'rating' => 5]], validationSet: [['user_id' => 1, 'item_id' => 2]]);
    }

    public function testInvalidRating()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rating must be numeric');

        $recommender = new Disco\Recommender();
        $recommender->fit([['user_id' => 1, 'item_id' => 1, 'rating' => 'invalid']]);
    }

    public function testInvalidRatingValidationSet()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rating must be numeric');

        $recommender = new Disco\Recommender();
        $recommender->fit([['user_id' => 1, 'item_id' => 1, 'rating' => 5]], validationSet: [['user_id' => 1, 'item_id' => 1, 'rating' => 'invalid']]);
    }

    public function testNotFit()
    {
        $this->expectException(Disco\Exception::class);
        $this->expectExceptionMessage('Not fit');

        $recommender = new Disco\Recommender();
        $recommender->userRecs(1);
    }

    public function testFitMultiple()
    {
        $recommender = new Disco\Recommender();
        $recommender->fit([['user_id' => 1, 'item_id' => 1, 'rating' => 5]]);
        $recommender->fit([['user_id' => 2, 'item_id' => 2]]);
        $this->assertEquals([2], $recommender->userIds());
        $this->assertEquals([2], $recommender->itemIds());
        $this->assertLessThan(1, $recommender->predict([['user_id' => 2, 'item_id' => 2]])[0]);
    }

    private function sort($v)
    {
        sort($v);
        return $v;
    }
}
