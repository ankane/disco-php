<?php

namespace Disco;

class Recommender
{
    // TODO make private in 0.2.0
    public $factors;
    public $epochs;
    public $verbose;
    public $userMap;
    public $itemMap;
    public $globalMean;
    public $implicit;
    public $rated;
    public $minRating;
    public $maxRating;
    public $userFactors;
    public $itemFactors;
    public $normalizedUserFactors;
    public $normalizedItemFactors;

    public function __construct($factors = 8, $epochs = 20, $verbose = null)
    {
        $this->factors = $factors;
        $this->epochs = $epochs;
        $this->verbose = $verbose;
        $this->userMap = [];
        $this->itemMap = [];
        $this->globalMean = null;
    }

    public function fit($trainSet, $validationSet = null)
    {
        if (count($trainSet) == 0) {
            throw new \InvalidArgumentException('No training data');
        }

        $this->implicit = true;
        foreach ($trainSet as $v) {
            if (isset($v['rating'])) {
                $this->implicit = false;
                break;
            }
        }

        if (!$this->implicit) {
            $this->checkRatings($trainSet);

            if (!is_null($validationSet)) {
                $this->checkRatings($validationSet);
            }
        }

        $this->userMap = [];
        $this->itemMap = [];
        $this->rated = [];
        $input = new \Libmf\Matrix();
        foreach ($trainSet as $v) {
            // update maps and build matrix in single pass
            $u = ($this->userMap[$v['user_id'] ?? null] ??= count($this->userMap));
            $i = ($this->itemMap[$v['item_id'] ?? null] ??= count($this->itemMap));

            // save rated
            $this->rated[$u] ??= [];
            $this->rated[$u][$i] = true;

            // explicit will always have a value due to checkRatings
            $input->push($u, $i, $this->implicit ? 1 : $v['rating']);
        }

        // much more efficient than checking every value in another pass
        if (isset($this->userMap[null])) {
            throw new \InvalidArgumentException('Missing user_id');
        }
        if (isset($this->itemMap[null])) {
            throw new \InvalidArgumentException('Missing item_id');
        }

        if (!$this->implicit) {
            $ratings = array_map(fn ($o) => $o['rating'], $trainSet);
            $this->minRating = min($ratings);
            $this->maxRating = max($ratings);
        } else {
            $this->minRating = null;
            $this->maxRating = null;
        }

        $evalSet = null;
        if (!is_null($validationSet)) {
            $evalSet = new \Libmf\Matrix();
            foreach ($validationSet as $v) {
                $u = $this->userMap[$v['user_id']] ?? -1;
                $i = $this->itemMap[$v['item_id']] ?? -1;
                $evalSet->push($u, $i, $this->implicit ? 1 : $v['rating']);
            }
        }

        $loss = $this->implicit ? \Libmf\Loss::OneClassL2 : \Libmf\Loss::RealL2;
        $verbose = $this->verbose;
        if (is_null($verbose) && !is_null($validationSet)) {
            $verbose = true;
        }
        $model = new \Libmf\Model(loss: $loss, factors: $this->factors, iterations: $this->epochs, quiet: !$verbose);
        $model->fit($input, $evalSet);

        $this->globalMean = $model->bias();

        $this->userFactors = $model->p();
        $this->itemFactors = $model->q();

        $this->normalizedUserFactors = null;
        $this->normalizedItemFactors = null;
    }

    public function predict($data)
    {
        $this->checkFit();

        $u = array_map(fn ($v) => $this->userMap[$v['user_id']] ?? null, $data);
        $i = array_map(fn ($v) => $this->itemMap[$v['item_id']] ?? null, $data);

        $newIndex = [];
        $count = count($data);
        for ($j = 0; $j < $count; $j++) {
            if (is_null($u[$j]) || is_null($i[$j])) {
                $newIndex[] = $j;
            }
        }
        foreach ($newIndex as $j) {
            $u[$j] = 0;
            $i[$j] = 0;
        }

        // TODO improve performance
        $predictions = [];
        for ($j = 0; $j < $count; $j++) {
            $a = $this->userFactors[$u[$j]];
            $b = $this->itemFactors[$i[$j]];
            $predictions[] = $this->innerProduct($a, $b);
        }
        if (!is_null($this->minRating)) {
            $predictions = array_map(fn ($v) => max(min($v, $this->maxRating), $this->minRating), $predictions);
        }
        foreach ($newIndex as $j) {
            $predictions[$j] = $this->globalMean;
        }
        return $predictions;
    }

    public function userRecs($userId, $count = 5, $itemIds = null)
    {
        $this->checkFit();
        $u = $this->userMap[$userId] ?? null;

        if (!is_null($u)) {
            $rated = !is_null($itemIds) ? [] : ($this->rated[$u] ?? []);

            if (!is_null($itemIds)) {
                $ids = array_filter(array_map(fn ($i) => $this->itemMap[$i] ?? null, $itemIds), fn ($v) => !is_null($v));
                if (count($ids) == 0) {
                    return [];
                }

                $aa = array_map(fn ($i) => $this->itemFactors[$i], $ids);
                $b = $this->userFactors[$u];
                $predictions = array_map(fn ($a) => $this->innerProduct($a, $b), $aa);
                $indexes = $this->argmax($predictions);
                if (!is_null($count)) {
                    $indexes = array_slice($indexes, 0, min($count + count($rated), count($indexes)));
                }
                $predictions = array_map(fn ($i) => $predictions[$i], $indexes);
                $ids = array_map(fn ($i) => $ids[$i], $indexes);
            } else {
                $b = $this->userFactors[$u];
                $predictions = array_map(fn ($a) => $this->innerProduct($a, $b), $this->itemFactors);
                $indexes = $this->argmax($predictions);
                if (!is_null($count)) {
                    $indexes = array_slice($indexes, 0, min($count + count($rated), count($indexes)));
                }
                $predictions = array_map(fn ($i) => $predictions[$i], $indexes);
                $ids = $indexes;
            }

            if (!is_null($this->minRating)) {
                $predictions = array_map(fn ($v) => max(min($v, $this->maxRating), $this->minRating), $predictions);
            }

            $keys = array_keys($this->itemMap);
            $result = [];
            foreach ($ids as $i => $itemId) {
                if (isset($rated[$itemId])) {
                    continue;
                }

                $result[] = ['item_id' => $keys[$itemId], 'score' => $predictions[$i]];
                if (count($result) == $count) {
                    break;
                }
            }
            return $result;
        } else {
            return [];
        }
    }

    public function itemRecs($itemId, $count = 5)
    {
        $this->checkFit();
        return $this->similar($itemId, 'item_id', $this->itemMap, $this->normalizedItemFactors(), $count);
    }

    public function similarUsers($userId, $count = 5)
    {
        $this->checkFit();
        return $this->similar($userId, 'user_id', $this->userMap, $this->normalizedUserFactors(), $count);
    }

    public function userIds()
    {
        return array_keys($this->userMap);
    }

    public function itemIds()
    {
        return array_keys($this->itemMap);
    }

    public function globalMean()
    {
        return $this->globalMean;
    }

    public function userFactors($userId)
    {
        $u = $this->userMap[$userId] ?? null;
        if (!is_null($u)) {
            return $this->userFactors[$u];
        } else {
            return null;
        }
    }

    public function itemFactors($itemId)
    {
        $i = $this->itemMap[$itemId] ?? null;
        if (!is_null($i)) {
            return $this->itemFactors[$i];
        } else {
            return null;
        }
    }

    private function normalizedUserFactors()
    {
        return ($this->normalizedUserFactors ??= $this->normalize($this->userFactors));
    }

    private function normalizedItemFactors()
    {
        return ($this->normalizedItemFactors ??= $this->normalize($this->itemFactors));
    }

    private function normalize($factors)
    {
        return array_map(
            function ($row) {
                $norm = $this->norm($row);
                if ($norm == 0) {
                    return $row;
                }
                return array_map(fn ($v) => $v / $norm, $row);
            },
            $factors
        );
    }

    private function similar($id, $key, $map, $normFactors, $count)
    {
        $i = $map[$id] ?? null;

        if (!is_null($i)) { // && norm_factors.shape[0] > 1
            $b = $normFactors[$i];
            $predictions = array_map(fn ($a) => $this->innerProduct($a, $b), $normFactors);
            $indexes = $this->argmax($predictions);
            if (!is_null($count)) {
                $indexes = array_slice($indexes, 0, min($count + 1, count($indexes)));
            }
            $predictions = array_map(fn ($i) => $predictions[$i], $indexes);
            $ids = $indexes;

            $keys = array_keys($map);

            $result = [];
            // items can have the same score
            // so original item may not be at index 0
            foreach ($ids as $j => $id) {
                if ($id == $i) {
                    continue;
                }

                $result[] = [$key => $keys[$id], 'score' => $predictions[$j]];
            }
            return $result;
        } else {
            return [];
        }
    }

    private function checkRatings($ratings)
    {
        foreach ($ratings as $r) {
            if (!isset($r['rating'])) {
                throw new \InvalidArgumentException('Missing rating');
            }
        }
        foreach ($ratings as $r) {
            if (!is_numeric($r['rating'])) {
                throw new \InvalidArgumentException('Rating must be numeric');
            }
        }
    }

    private function checkFit()
    {
        if (!isset($this->implicit)) {
            throw new Exception('Not fit');
        }
    }

    private function innerProduct($a, $b)
    {
        $sum = 0;
        for ($i = 0; $i < $this->factors; $i++) {
            $sum += $a[$i] * $b[$i];
        }
        return $sum;
    }

    private function norm($row)
    {
        $sum = 0;
        foreach ($row as $v) {
            $sum += $v * $v;
        }
        return sqrt($sum);
    }

    private function argmax($arr)
    {
        arsort($arr);
        return array_keys($arr);
    }
}
