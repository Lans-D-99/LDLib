<?php
namespace LDLib\Utils;

use Ds\Map;
use Random\{Randomizer,IntervalBoundary};

class MapUtils {
    public static function incr(Map $map, string $k) {
        if ($map->hasKey($k)) $map[$k] = ++$map[$k];
        else $map[$k] = 1;
    }

    public static function weightedMap_GetRandomKey(Map $map) {
        $totalWeight = 0;
        $a = $map->toArray();

        foreach ($a as $k => $v) $totalWeight += $v;
        $randV = (new Randomizer())->getFloat(0,$totalWeight,IntervalBoundary::OpenOpen);

        $cumWeight = 0;
        foreach ($a as $k => $v) {
            $cumWeight += $v;
            if ($cumWeight > $randV) return $k;
        }

        throw new \Exception("Weighted Map Random Key Failure (totWeight: $totalWeight | cumWeight: $cumWeight)");
    }
}
?>