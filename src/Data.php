<?php

namespace Disco;

class Data
{
    public static function loadMovieLens()
    {
        $itemPath = self::downloadFile(
            'ml-100k/u.item',
            'https://files.grouplens.org/datasets/movielens/ml-100k/u.item',
            '553841ebc7de3a0fd0d6b62a204ea30c1e651aacfb2814c7a6584ac52f2c5701'
        );

        $dataPath = self::downloadFile(
            'ml-100k/u.data',
            'https://files.grouplens.org/datasets/movielens/ml-100k/u.data',
            '06416e597f82b7342361e41163890c81036900f418ad91315590814211dca490'
        );

        $movies = [];
        if (($handle = fopen($itemPath, 'r')) !== false) {
            while (($row = fgetcsv($handle, separator: '|')) !== false) {
                $movies[$row[0]] = mb_convert_encoding($row[1], 'UTF-8', 'ISO-8859-1');
            }
            fclose($handle);
        }

        $data = [];
        if (($handle = fopen($dataPath, 'r')) !== false) {
            while (($row = fgetcsv($handle, separator: "\t")) !== false) {
                $data[] = [
                    'user_id' => intval($row[0]),
                    'item_id' => $movies[$row[1]],
                    'rating'  => intval($row[2])
                ];
            }
            fclose($handle);
        }

        return $data;
    }

    private static function downloadFile($fname, $origin, $fileHash)
    {
        $home = getenv('HOME');
        if ($home == false) {
            throw new Exception('No HOME');
        }

        $dest = "$home/.disco/$fname";
        if (file_exists($dest)) {
            return $dest;
        }

        if (!file_exists(dirname($dest))) {
            mkdir(dirname($dest), 0777, true);
        }

        echo "Downloading data from $origin\n";
        $contents = file_get_contents($origin);

        $checksum = hash('sha256', $contents);
        if ($checksum != $fileHash) {
            throw new Exception("Bad checksum: $checksum");
        }
        echo "✔ Success\n";

        file_put_contents($dest, $contents);

        return $dest;
    }
}
