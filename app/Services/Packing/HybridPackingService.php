<?php

namespace App\Services\Packing;

use App\Services\Packing\PackingServiceInterface;

class HybridPackingService implements PackingServiceInterface
{
    public function __construct(
        private CompartmentPackingService $compartmentService,
        private LAFFPackingService $laffService,
        private ProductGroupingService $groupingService
    ) {
    }

    /**
     * Pack items using hybrid strategy (combines compartment and LAFF).
     *
     * @param array $items Array of items with: product_id, width, depth, height, quantity
     * @param float $roomWidth Room width
     * @param float $roomDepth Room depth
     * @param float $roomHeight Room height
     * @param array $options Options
     * @return array{placements: array, unplaced_items: array, utilization: float, strategy_used: string}
     */
    public function pack(
        array $items,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight,
        array $options = []
    ): array {
        // Analyze product mix to determine best strategy
        $strategy = $this->determineStrategy($items, $roomWidth, $roomDepth, $roomHeight);

        // Try multiple strategies and return the best result
        $results = [];

        // Strategy 1: Compartment-based (good for many different products)
        if ($strategy === 'compartment' || $strategy === 'hybrid') {
            try {
                $compartmentResult = $this->compartmentService->pack(
                    $items,
                    $roomWidth,
                    $roomDepth,
                    $roomHeight,
                    $options
                );
                $results['compartment'] = $compartmentResult;
            } catch (\Exception $e) {
                // Strategy failed
            }
        }

        // Strategy 2: LAFF-based (good for similar products or high quantity)
        if ($strategy === 'laff' || $strategy === 'hybrid') {
            try {
                $laffResult = $this->laffService->pack(
                    $items,
                    $roomWidth,
                    $roomDepth,
                    $roomHeight,
                    $options
                );
                $results['laff'] = $laffResult;
            } catch (\Exception $e) {
                // Strategy failed
            }
        }

        // Strategy 3: Hybrid (use compartment for grouping, LAFF for placement)
        if ($strategy === 'hybrid') {
            try {
                $hybridResult = $this->hybridStrategy($items, $roomWidth, $roomDepth, $roomHeight, $options);
                $results['hybrid'] = $hybridResult;
            } catch (\Exception $e) {
                // Strategy failed
            }
        }

        // Return best result
        if (empty($results)) {
            // Fallback to compartment
            return $this->compartmentService->pack($items, $roomWidth, $roomDepth, $roomHeight, $options);
        }

        $bestResult = null;
        $bestUtilization = -1;
        $bestStrategy = 'compartment';

        foreach ($results as $strategyName => $result) {
            $utilization = $result['utilization'] ?? 0;
            $placedCount = count($result['placements'] ?? []);
            
            // Score: utilization + placement count bonus
            $score = $utilization + ($placedCount * 0.1);

            if ($score > $bestUtilization) {
                $bestUtilization = $score;
                $bestResult = $result;
                $bestStrategy = $strategyName;
            }
        }

        return [
            ...$bestResult,
            'strategy_used' => $bestStrategy,
        ];
    }

    /**
     * Determine best strategy based on product mix.
     */
    private function determineStrategy(
        array $items,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): string {
        $numProducts = count(array_unique(array_column($items, 'product_id')));
        $totalQuantity = array_sum(array_column($items, 'quantity'));
        $avgQuantity = $totalQuantity / max($numProducts, 1);

        // Many different products with low quantity each -> compartment
        if ($numProducts > 10 && $avgQuantity < 5) {
            return 'compartment';
        }

        // Few products with high quantity -> LAFF
        if ($numProducts <= 3 && $avgQuantity > 10) {
            return 'laff';
        }

        // Mixed case -> hybrid
        return 'hybrid';
    }

    /**
     * Hybrid strategy: use compartment for grouping, LAFF for placement.
     */
    private function hybridStrategy(
        array $items,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight,
        array $options
    ): array {
        // Group products
        $grouping = $this->groupingService->groupForOptimalFit($items);
        $groups = $grouping['groups'] ?? [];

        // Allocate space for each group using compartment approach
        $numGroups = count($groups);
        $gridConfig = [
            'columns' => (int)ceil(sqrt($numGroups * ($roomWidth / $roomDepth))),
            'rows' => (int)ceil($numGroups / max(1, (int)ceil(sqrt($numGroups * ($roomWidth / $roomDepth))))),
        ];

        $cellWidth = $roomWidth / $gridConfig['columns'];
        $cellDepth = $roomDepth / $gridConfig['rows'];

        $allPlacements = [];
        $allUnplaced = [];

        // Use LAFF for placement within each group's compartment
        foreach ($groups as $groupIndex => $group) {
            $groupItems = $group['items'] ?? [];
            if (empty($groupItems)) {
                continue;
            }

            $column = $groupIndex % $gridConfig['columns'];
            $row = (int)floor($groupIndex / $gridConfig['columns']);

            $compartmentX = $column * $cellWidth;
            $compartmentY = $row * $cellDepth;
            $compartmentWidth = min($cellWidth, $roomWidth - $compartmentX);
            $compartmentDepth = min($cellDepth, $roomDepth - $compartmentY);

            // Pack items in this compartment using LAFF
            $compartmentOptions = array_merge($options, [
                'compartment_boundary' => [
                    'x' => $compartmentX,
                    'y' => $compartmentY,
                    'width' => $compartmentWidth,
                    'depth' => $compartmentDepth,
                ],
            ]);

            $result = $this->laffService->pack(
                $groupItems,
                $compartmentWidth,
                $compartmentDepth,
                $roomHeight,
                $compartmentOptions
            );

            // Adjust placements to compartment position
            foreach ($result['placements'] ?? [] as $placement) {
                $allPlacements[] = [
                    ...$placement,
                    'x' => $placement['x'] + $compartmentX,
                    'y' => $placement['y'] + $compartmentY,
                ];
            }

            $allUnplaced = array_merge($allUnplaced, $result['unplaced_items'] ?? []);
        }

        // Calculate utilization
        $totalVolume = $roomWidth * $roomDepth * $roomHeight;
        $usedVolume = 0;
        foreach ($allPlacements as $placement) {
            $usedVolume += ($placement['width'] ?? 0) * ($placement['depth'] ?? 0) * ($placement['height'] ?? 0);
        }
        $utilization = $totalVolume > 0 ? ($usedVolume / $totalVolume) * 100 : 0;

        return [
            'placements' => $allPlacements,
            'unplaced_items' => $allUnplaced,
            'utilization' => $utilization,
        ];
    }
}
