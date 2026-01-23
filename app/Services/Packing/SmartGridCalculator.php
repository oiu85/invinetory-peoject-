<?php

namespace App\Services\Packing;

class SmartGridCalculator
{
    /**
     * Calculate optimal grid based on product dimensions, quantities, and room dimensions.
     *
     * @param array $items Array of items with: product_id, width, depth, height, quantity
     * @param float $roomWidth Room width in cm
     * @param float $roomDepth Room depth in cm
     * @param float $roomHeight Room height in cm
     * @param array $options Grid configuration options
     * @return array{columns: int, rows: int, cell_width: float, cell_depth: float, compartments: array, strategy: string}
     */
    public function calculateOptimalGrid(
        array $items,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight,
        array $options = []
    ): array {
        // If explicit grid is requested, use it
        if (isset($options['grid']['columns']) && isset($options['grid']['rows'])) {
            return $this->calculateExplicitGrid(
                $options['grid']['columns'],
                $options['grid']['rows'],
                $roomWidth,
                $roomDepth
            );
        }

        // Group products by size categories
        $productGroups = $this->groupProductsBySize($items);
        
        // Calculate grid using best strategy
        $strategies = [
            'size_based' => fn() => $this->calculateSizeBasedGrid($productGroups, $roomWidth, $roomDepth, $roomHeight),
            'aspect_ratio' => fn() => $this->calculateAspectRatioGrid($productGroups, $roomWidth, $roomDepth),
            'density_optimized' => fn() => $this->calculateDensityOptimizedGrid($productGroups, $roomWidth, $roomDepth, $roomHeight),
        ];

        $bestGrid = null;
        $bestScore = -1;

        foreach ($strategies as $strategyName => $strategyFn) {
            try {
                $grid = $strategyFn();
                $score = $this->scoreGrid($grid, $productGroups, $roomWidth, $roomDepth, $roomHeight);
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestGrid = $grid;
                    $bestGrid['strategy'] = $strategyName;
                }
            } catch (\Exception $e) {
                // Strategy failed, try next one
                continue;
            }
        }

        // Fallback to simple grid if all strategies fail
        if (!$bestGrid) {
            $numProducts = count($productGroups);
            $bestGrid = $this->calculateSimpleGrid($numProducts, $roomWidth, $roomDepth);
            $bestGrid['strategy'] = 'simple_fallback';
        }

        // Validate and adjust grid
        $bestGrid = $this->validateAndAdjustGrid($bestGrid, $productGroups, $roomWidth, $roomDepth, $roomHeight);

        return $bestGrid;
    }

    /**
     * Group products by size categories (small, medium, large).
     *
     * @param array $items
     * @return array{small: array, medium: array, large: array, groups: array}
     */
    private function groupProductsBySize(array $items): array
    {
        $groups = [];
        $allVolumes = [];
        $allBaseAreas = [];

        // Calculate volumes and base areas for all items
        foreach ($items as $item) {
            $volume = ($item['width'] ?? 0) * ($item['depth'] ?? 0) * ($item['height'] ?? 0);
            $baseArea = ($item['width'] ?? 0) * ($item['depth'] ?? 0);
            $allVolumes[] = $volume;
            $allBaseAreas[] = $baseArea;
        }

        if (empty($allVolumes)) {
            return ['small' => [], 'medium' => [], 'large' => [], 'groups' => []];
        }

        // Calculate percentiles for categorization
        sort($allVolumes);
        sort($allBaseAreas);
        $volume33 = $allVolumes[(int)(count($allVolumes) * 0.33)];
        $volume66 = $allVolumes[(int)(count($allVolumes) * 0.66)];
        $area33 = $allBaseAreas[(int)(count($allBaseAreas) * 0.33)];
        $area66 = $allBaseAreas[(int)(count($allBaseAreas) * 0.66)];

        $small = [];
        $medium = [];
        $large = [];
        $productGroups = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId) continue;

            $volume = ($item['width'] ?? 0) * ($item['depth'] ?? 0) * ($item['height'] ?? 0);
            $baseArea = ($item['width'] ?? 0) * ($item['depth'] ?? 0);

            if (!isset($productGroups[$productId])) {
                $productGroups[$productId] = [
                    'product_id' => $productId,
                    'width' => $item['width'] ?? 0,
                    'depth' => $item['depth'] ?? 0,
                    'height' => $item['height'] ?? 0,
                    'quantity' => 0,
                    'volume' => 0,
                    'base_area' => 0,
                ];
            }

            $productGroups[$productId]['quantity'] += ($item['quantity'] ?? 1);
            $productGroups[$productId]['volume'] = $volume;
            $productGroups[$productId]['base_area'] = $baseArea;

            // Categorize
            if ($volume <= $volume33 && $baseArea <= $area33) {
                $small[] = $productId;
            } elseif ($volume <= $volume66 && $baseArea <= $area66) {
                $medium[] = $productId;
            } else {
                $large[] = $productId;
            }
        }

        return [
            'small' => $small,
            'medium' => $medium,
            'large' => $large,
            'groups' => array_values($productGroups),
        ];
    }

    /**
     * Calculate grid based on size categories.
     */
    private function calculateSizeBasedGrid(
        array $productGroups,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): array {
        $numProducts = count($productGroups['groups']);
        if ($numProducts === 0) {
            return $this->calculateSimpleGrid(1, $roomWidth, $roomDepth);
        }

        // Calculate optimal grid based on largest products
        $maxWidth = 0;
        $maxDepth = 0;
        $maxHeight = 0;

        foreach ($productGroups['groups'] as $group) {
            $maxWidth = max($maxWidth, $group['width']);
            $maxDepth = max($maxDepth, $group['depth']);
            $maxHeight = max($maxHeight, $group['height']);
        }

        // Calculate minimum cell size needed
        $minCellWidth = $maxWidth * 1.1; // 10% margin
        $minCellDepth = $maxDepth * 1.1;

        // Calculate maximum possible grid
        $maxColumns = max(1, (int)floor($roomWidth / $minCellWidth));
        $maxRows = max(1, (int)floor($roomDepth / $minCellDepth));

        // Calculate optimal grid dimensions
        $aspectRatio = $roomWidth / $roomDepth;
        $targetColumns = (int)ceil(sqrt($numProducts * $aspectRatio));
        $targetRows = (int)ceil($numProducts / $targetColumns);

        // Ensure grid fits within room
        $columns = min($targetColumns, $maxColumns);
        $rows = min($targetRows, $maxRows);

        // Ensure we have enough cells
        while ($columns * $rows < $numProducts) {
            if ($columns < $maxColumns) {
                $columns++;
            } elseif ($rows < $maxRows) {
                $rows++;
            } else {
                break; // Can't fit all products
            }
        }

        $cellWidth = $roomWidth / $columns;
        $cellDepth = $roomDepth / $rows;

        return [
            'columns' => $columns,
            'rows' => $rows,
            'cell_width' => $cellWidth,
            'cell_depth' => $cellDepth,
            'compartments' => $this->generateCompartments($productGroups['groups'], $columns, $rows, $cellWidth, $cellDepth, $roomWidth, $roomDepth),
        ];
    }

    /**
     * Calculate grid based on aspect ratio matching.
     */
    private function calculateAspectRatioGrid(
        array $productGroups,
        float $roomWidth,
        float $roomDepth
    ): array {
        $numProducts = count($productGroups['groups']);
        if ($numProducts === 0) {
            return $this->calculateSimpleGrid(1, $roomWidth, $roomDepth);
        }

        // Calculate average aspect ratio of products
        $totalAspectRatio = 0;
        $count = 0;

        foreach ($productGroups['groups'] as $group) {
            if ($group['depth'] > 0) {
                $totalAspectRatio += $group['width'] / $group['depth'];
                $count++;
            }
        }

        $avgAspectRatio = $count > 0 ? $totalAspectRatio / $count : 1.0;
        $roomAspectRatio = $roomWidth / $roomDepth;

        // Calculate grid that matches product aspect ratio
        $targetAspectRatio = $avgAspectRatio;
        $numCells = $numProducts;

        // Try different grid configurations
        $bestGrid = null;
        $bestMatch = PHP_FLOAT_MAX;

        for ($cols = 1; $cols <= min($numCells, 20); $cols++) {
            $rows = (int)ceil($numCells / $cols);
            $gridAspectRatio = ($roomWidth / $cols) / ($roomDepth / $rows);
            $match = abs($gridAspectRatio - $targetAspectRatio);

            if ($match < $bestMatch) {
                $bestMatch = $match;
                $bestGrid = [
                    'columns' => $cols,
                    'rows' => $rows,
                    'cell_width' => $roomWidth / $cols,
                    'cell_depth' => $roomDepth / $rows,
                ];
            }
        }

        if (!$bestGrid) {
            return $this->calculateSimpleGrid($numProducts, $roomWidth, $roomDepth);
        }

        $bestGrid['compartments'] = $this->generateCompartments(
            $productGroups['groups'],
            $bestGrid['columns'],
            $bestGrid['rows'],
            $bestGrid['cell_width'],
            $bestGrid['cell_depth'],
            $roomWidth,
            $roomDepth
        );

        return $bestGrid;
    }

    /**
     * Calculate grid optimized for density (maximize space utilization).
     */
    private function calculateDensityOptimizedGrid(
        array $productGroups,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): array {
        $numProducts = count($productGroups['groups']);
        if ($numProducts === 0) {
            return $this->calculateSimpleGrid(1, $roomWidth, $roomDepth);
        }

        // Calculate total volume needed
        $totalVolumeNeeded = 0;
        foreach ($productGroups['groups'] as $group) {
            $totalVolumeNeeded += $group['volume'] * $group['quantity'];
        }

        $roomVolume = $roomWidth * $roomDepth * $roomHeight;
        $targetUtilization = min(0.85, $totalVolumeNeeded / $roomVolume);

        // Try different grid configurations and pick the one with best density
        $bestGrid = null;
        $bestDensity = 0;

        $maxCols = min(20, (int)floor($roomWidth / 10)); // Minimum 10cm per cell
        $maxRows = min(20, (int)floor($roomDepth / 10));

        for ($cols = 1; $cols <= $maxCols; $cols++) {
            for ($rows = 1; $rows <= $maxRows; $rows++) {
                if ($cols * $rows < $numProducts) continue;

                $cellWidth = $roomWidth / $cols;
                $cellDepth = $roomDepth / $rows;

                // Check if largest product fits
                $fits = true;
                foreach ($productGroups['groups'] as $group) {
                    if ($group['width'] > $cellWidth || $group['depth'] > $cellDepth) {
                        $fits = false;
                        break;
                    }
                }

                if (!$fits) continue;

                // Calculate density score
                $cellArea = $cellWidth * $cellDepth;
                $usedArea = 0;
                foreach ($productGroups['groups'] as $group) {
                    $usedArea += min($group['base_area'], $cellArea) * min($group['quantity'], 10); // Cap at 10 for calculation
                }

                $density = $usedArea / ($cellArea * $cols * $rows);

                if ($density > $bestDensity) {
                    $bestDensity = $density;
                    $bestGrid = [
                        'columns' => $cols,
                        'rows' => $rows,
                        'cell_width' => $cellWidth,
                        'cell_depth' => $cellDepth,
                    ];
                }
            }
        }

        if (!$bestGrid) {
            return $this->calculateSimpleGrid($numProducts, $roomWidth, $roomDepth);
        }

        $bestGrid['compartments'] = $this->generateCompartments(
            $productGroups['groups'],
            $bestGrid['columns'],
            $bestGrid['rows'],
            $bestGrid['cell_width'],
            $bestGrid['cell_depth'],
            $roomWidth,
            $roomDepth
        );

        return $bestGrid;
    }

    /**
     * Calculate simple grid (fallback).
     */
    private function calculateSimpleGrid(int $numProducts, float $roomWidth, float $roomDepth): array
    {
        $aspectRatio = $roomWidth / $roomDepth;
        $columns = max(1, (int)ceil(sqrt($numProducts * $aspectRatio)));
        $rows = max(1, (int)ceil($numProducts / $columns));

        return [
            'columns' => $columns,
            'rows' => $rows,
            'cell_width' => $roomWidth / $columns,
            'cell_depth' => $roomDepth / $rows,
            'compartments' => [],
        ];
    }

    /**
     * Calculate explicit grid from user input.
     */
    private function calculateExplicitGrid(int $columns, int $rows, float $roomWidth, float $roomDepth): array
    {
        return [
            'columns' => $columns,
            'rows' => $rows,
            'cell_width' => $roomWidth / $columns,
            'cell_depth' => $roomDepth / $rows,
            'compartments' => [],
        ];
    }

    /**
     * Generate compartment boundaries for products.
     */
    private function generateCompartments(
        array $productGroups,
        int $columns,
        int $rows,
        float $cellWidth,
        float $cellDepth,
        float $roomWidth,
        float $roomDepth
    ): array {
        $compartments = [];
        $productIndex = 0;

        foreach ($productGroups as $group) {
            if ($productIndex >= $columns * $rows) break;

            $column = $productIndex % $columns;
            $row = (int)floor($productIndex / $columns);

            $x = $column * $cellWidth;
            $y = $row * $cellDepth;
            $width = min($cellWidth, $roomWidth - $x);
            $depth = min($cellDepth, $roomDepth - $y);

            $compartments[] = [
                'product_id' => $group['product_id'],
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'depth' => $depth,
                'column' => $column,
                'row' => $row,
            ];

            $productIndex++;
        }

        return $compartments;
    }

    /**
     * Score a grid configuration.
     */
    private function scoreGrid(
        array $grid,
        array $productGroups,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): float {
        $score = 0;

        // Score based on cell size vs product size
        $cellArea = $grid['cell_width'] * $grid['cell_depth'];
        $wasteScore = 0;
        $fitScore = 0;

        foreach ($productGroups['groups'] as $group) {
            $productArea = $group['width'] * $group['depth'];
            if ($productArea <= $cellArea) {
                $fitScore += 1;
                $waste = $cellArea - $productArea;
                $wasteScore += max(0, 1 - ($waste / $cellArea)); // Less waste = higher score
            }
        }

        $score += ($fitScore / count($productGroups['groups'])) * 50;
        $score += ($wasteScore / count($productGroups['groups'])) * 30;

        // Score based on grid utilization
        $totalCells = $grid['columns'] * $grid['rows'];
        $usedCells = min(count($productGroups['groups']), $totalCells);
        $utilizationScore = $usedCells / $totalCells;
        $score += $utilizationScore * 20;

        return $score;
    }

    /**
     * Validate and adjust grid to ensure it fits room and products.
     */
    private function validateAndAdjustGrid(
        array $grid,
        array $productGroups,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): array {
        // Ensure minimum cell sizes
        $minCellWidth = 0;
        $minCellDepth = 0;

        foreach ($productGroups['groups'] as $group) {
            $minCellWidth = max($minCellWidth, $group['width'] * 1.05); // 5% margin
            $minCellDepth = max($minCellDepth, $group['depth'] * 1.05);
        }

        // Adjust grid if cells are too small
        if ($grid['cell_width'] < $minCellWidth) {
            $grid['columns'] = max(1, (int)floor($roomWidth / $minCellWidth));
            $grid['cell_width'] = $roomWidth / $grid['columns'];
        }

        if ($grid['cell_depth'] < $minCellDepth) {
            $grid['rows'] = max(1, (int)floor($roomDepth / $minCellDepth));
            $grid['cell_depth'] = $roomDepth / $grid['rows'];
        }

        // Ensure grid fits within room
        $grid['cell_width'] = min($grid['cell_width'], $roomWidth);
        $grid['cell_depth'] = min($grid['cell_depth'], $roomDepth);

        return $grid;
    }
}
