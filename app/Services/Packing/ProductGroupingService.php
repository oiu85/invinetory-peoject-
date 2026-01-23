<?php

namespace App\Services\Packing;

class ProductGroupingService
{
    /**
     * Group products by dimensions.
     *
     * @param array $items Array of items with: product_id, width, depth, height, quantity
     * @return array{groups: array, strategy: string}
     */
    public function groupByDimensions(array $items): array
    {
        $groups = [];
        $tolerance = 5.0; // 5cm tolerance for grouping

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId) continue;

            $width = (float)($item['width'] ?? 0);
            $depth = (float)($item['depth'] ?? 0);
            $height = (float)($item['height'] ?? 0);

            // Find matching group
            $matched = false;
            foreach ($groups as &$group) {
                $groupWidth = $group['width'];
                $groupDepth = $group['depth'];
                $groupHeight = $group['height'];

                if (abs($width - $groupWidth) <= $tolerance &&
                    abs($depth - $groupDepth) <= $tolerance &&
                    abs($height - $groupHeight) <= $tolerance) {
                    $group['items'][] = $item;
                    $group['quantity'] += ($item['quantity'] ?? 1);
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $groups[] = [
                    'width' => $width,
                    'depth' => $depth,
                    'height' => $height,
                    'items' => [$item],
                    'quantity' => ($item['quantity'] ?? 1),
                    'base_area' => $width * $depth,
                    'volume' => $width * $depth * $height,
                ];
            }
        }

        return [
            'groups' => $groups,
            'strategy' => 'dimension_based',
            'group_count' => count($groups),
        ];
    }

    /**
     * Group products by aspect ratio.
     *
     * @param array $items
     * @return array{groups: array, strategy: string}
     */
    public function groupByAspectRatio(array $items): array
    {
        $groups = [];
        $ratioTolerance = 0.2; // 20% tolerance for aspect ratio

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId) continue;

            $width = (float)($item['width'] ?? 0);
            $depth = (float)($item['depth'] ?? 0);
            $height = (float)($item['height'] ?? 0);

            if ($depth <= 0) continue;

            $aspectRatio = $width / $depth;

            // Find matching group
            $matched = false;
            foreach ($groups as &$group) {
                $groupRatio = $group['aspect_ratio'];
                if (abs($aspectRatio - $groupRatio) <= $ratioTolerance) {
                    $group['items'][] = $item;
                    $group['quantity'] += ($item['quantity'] ?? 1);
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $groups[] = [
                    'aspect_ratio' => $aspectRatio,
                    'width' => $width,
                    'depth' => $depth,
                    'height' => $height,
                    'items' => [$item],
                    'quantity' => ($item['quantity'] ?? 1),
                    'base_area' => $width * $depth,
                    'volume' => $width * $depth * $height,
                ];
            }
        }

        return [
            'groups' => $groups,
            'strategy' => 'aspect_ratio_based',
            'group_count' => count($groups),
        ];
    }

    /**
     * Group products by quantity (high-volume products get priority).
     *
     * @param array $items
     * @return array{groups: array, strategy: string}
     */
    public function groupByQuantity(array $items): array
    {
        // Sort by quantity descending
        $sorted = $items;
        usort($sorted, function ($a, $b) {
            $qtyA = (int)($a['quantity'] ?? 0);
            $qtyB = (int)($b['quantity'] ?? 0);
            return $qtyB <=> $qtyA;
        });

        // Group into high, medium, low volume
        $highVolume = [];
        $mediumVolume = [];
        $lowVolume = [];

        $totalQuantity = array_sum(array_column($items, 'quantity'));
        $avgQuantity = $totalQuantity / max(count($items), 1);

        foreach ($sorted as $item) {
            $quantity = (int)($item['quantity'] ?? 0);
            if ($quantity >= $avgQuantity * 1.5) {
                $highVolume[] = $item;
            } elseif ($quantity >= $avgQuantity * 0.5) {
                $mediumVolume[] = $item;
            } else {
                $lowVolume[] = $item;
            }
        }

        $groups = [];
        if (!empty($highVolume)) {
            $groups[] = [
                'priority' => 'high',
                'items' => $highVolume,
                'total_quantity' => array_sum(array_column($highVolume, 'quantity')),
            ];
        }
        if (!empty($mediumVolume)) {
            $groups[] = [
                'priority' => 'medium',
                'items' => $mediumVolume,
                'total_quantity' => array_sum(array_column($mediumVolume, 'quantity')),
            ];
        }
        if (!empty($lowVolume)) {
            $groups[] = [
                'priority' => 'low',
                'items' => $lowVolume,
                'total_quantity' => array_sum(array_column($lowVolume, 'quantity')),
            ];
        }

        return [
            'groups' => $groups,
            'strategy' => 'quantity_based',
            'group_count' => count($groups),
        ];
    }

    /**
     * Group products for optimal fit.
     * Tries multiple strategies and returns the best one.
     *
     * @param array $items
     * @return array{groups: array, strategy: string, score: float}
     */
    public function groupForOptimalFit(array $items): array
    {
        $strategies = [
            'dimensions' => fn() => $this->groupByDimensions($items),
            'aspect_ratio' => fn() => $this->groupByAspectRatio($items),
            'quantity' => fn() => $this->groupByQuantity($items),
        ];

        $bestResult = null;
        $bestScore = -1;

        foreach ($strategies as $strategyName => $strategyFn) {
            $result = $strategyFn();
            $score = $this->scoreGrouping($result);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestResult = $result;
                $bestResult['strategy'] = $strategyName;
                $bestResult['score'] = $score;
            }
        }

        return $bestResult ?: [
            'groups' => [['items' => $items]],
            'strategy' => 'none',
            'score' => 0,
        ];
    }

    /**
     * Score a grouping strategy.
     */
    private function scoreGrouping(array $grouping): float
    {
        $groupCount = $grouping['group_count'] ?? 1;
        $totalItems = count($grouping['groups'] ?? []);

        if ($totalItems === 0) {
            return 0;
        }

        // Prefer fewer groups (better consolidation)
        $consolidationScore = max(0, 1 - ($groupCount / $totalItems));

        // Prefer balanced group sizes
        $groupSizes = array_map(fn($g) => count($g['items'] ?? []), $grouping['groups'] ?? []);
        $avgSize = array_sum($groupSizes) / max(count($groupSizes), 1);
        $variance = 0;
        foreach ($groupSizes as $size) {
            $variance += pow($size - $avgSize, 2);
        }
        $variance = $variance / max(count($groupSizes), 1);
        $balanceScore = max(0, 1 - ($variance / ($avgSize * $avgSize)));

        return ($consolidationScore * 60) + ($balanceScore * 40);
    }
}
