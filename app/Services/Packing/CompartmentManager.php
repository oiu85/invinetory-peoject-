<?php

namespace App\Services\Packing;

use App\Services\Packing\SmartGridCalculator;

class CompartmentManager
{
    public function __construct(
        private SmartGridCalculator $smartGridCalculator
    ) {
    }

    /**
     * Calculate optimal grid dimensions based on room size and number of products.
     * Uses SmartGridCalculator for intelligent grid calculation.
     *
     * @param float $roomWidth Room width in cm
     * @param float $roomDepth Room depth in cm
     * @param int $numProducts Number of unique products
     * @param array $options Grid configuration options
     * @return array{columns: int, rows: int, cell_width: float, cell_depth: float}
     */
    public function calculateGrid(
        float $roomWidth,
        float $roomDepth,
        int $numProducts,
        array $options = []
    ): array {
        $requestedColumns = $options['columns'] ?? null;
        $requestedRows = $options['rows'] ?? null;

        if ($requestedColumns && $requestedRows) {
            return [
                'columns' => $requestedColumns,
                'rows' => $requestedRows,
                'cell_width' => $roomWidth / $requestedColumns,
                'cell_depth' => $roomDepth / $requestedRows,
            ];
        }

        // Fallback to simple calculation if no items provided
        if (empty($options['items'] ?? [])) {
            return $this->calculateSimpleGrid($roomWidth, $roomDepth, $numProducts, $options);
        }

        // Use SmartGridCalculator for optimal grid calculation
        try {
            $roomHeight = $options['room_height'] ?? 300; // Default height if not provided
            $grid = $this->smartGridCalculator->calculateOptimalGrid(
                $options['items'],
                $roomWidth,
                $roomDepth,
                $roomHeight,
                $options
            );

            return [
                'columns' => $grid['columns'],
                'rows' => $grid['rows'],
                'cell_width' => $grid['cell_width'],
                'cell_depth' => $grid['cell_depth'],
            ];
        } catch (\Exception $e) {
            // Fallback to simple calculation on error
            return $this->calculateSimpleGrid($roomWidth, $roomDepth, $numProducts, $options);
        }
    }

    /**
     * Calculate optimal grid using SmartGridCalculator with product dimensions.
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
        return $this->smartGridCalculator->calculateOptimalGrid(
            $items,
            $roomWidth,
            $roomDepth,
            $roomHeight,
            $options
        );
    }

    /**
     * Validate grid against room dimensions and product requirements.
     *
     * @param array $grid Grid configuration
     * @param array $items Array of items with dimensions
     * @param float $roomWidth Room width
     * @param float $roomDepth Room depth
     * @param float $roomHeight Room height
     * @return array{valid: bool, errors: array, adjusted_grid: array|null}
     */
    public function validateGridAgainstRoom(
        array $grid,
        array $items,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): array {
        $errors = [];

        // Validate grid dimensions
        if ($grid['columns'] <= 0 || $grid['rows'] <= 0) {
            $errors[] = 'Grid must have at least 1 column and 1 row';
        }

        if ($grid['cell_width'] <= 0 || $grid['cell_depth'] <= 0) {
            $errors[] = 'Grid cells must have positive dimensions';
        }

        // Check if grid fits in room
        $totalGridWidth = $grid['columns'] * $grid['cell_width'];
        $totalGridDepth = $grid['rows'] * $grid['cell_depth'];

        if ($totalGridWidth > $roomWidth * 1.01) { // 1% tolerance
            $errors[] = "Grid width ({$totalGridWidth} cm) exceeds room width ({$roomWidth} cm)";
        }

        if ($totalGridDepth > $roomDepth * 1.01) {
            $errors[] = "Grid depth ({$totalGridDepth} cm) exceeds room depth ({$roomDepth} cm)";
        }

        // Check if largest product fits in cells
        $maxProductWidth = 0;
        $maxProductDepth = 0;
        $maxProductHeight = 0;

        foreach ($items as $item) {
            $maxProductWidth = max($maxProductWidth, (float)($item['width'] ?? 0));
            $maxProductDepth = max($maxProductDepth, (float)($item['depth'] ?? 0));
            $maxProductHeight = max($maxProductHeight, (float)($item['height'] ?? 0));
        }

        if ($maxProductWidth > $grid['cell_width']) {
            $errors[] = "Largest product width ({$maxProductWidth} cm) exceeds cell width ({$grid['cell_width']} cm)";
        }

        if ($maxProductDepth > $grid['cell_depth']) {
            $errors[] = "Largest product depth ({$maxProductDepth} cm) exceeds cell depth ({$grid['cell_depth']} cm)";
        }

        if ($maxProductHeight > $roomHeight) {
            $errors[] = "Largest product height ({$maxProductHeight} cm) exceeds room height ({$roomHeight} cm)";
        }

        // Adjust grid if needed
        $adjustedGrid = null;
        if (!empty($errors)) {
            $adjustedGrid = $this->adjustGridForProducts(
                $grid,
                $items,
                $roomWidth,
                $roomDepth,
                $roomHeight
            );
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'adjusted_grid' => $adjustedGrid,
        ];
    }

    /**
     * Adjust grid to accommodate products.
     */
    private function adjustGridForProducts(
        array $grid,
        array $items,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): array {
        // Find minimum cell size needed
        $minCellWidth = 0;
        $minCellDepth = 0;

        foreach ($items as $item) {
            $minCellWidth = max($minCellWidth, (float)($item['width'] ?? 0) * 1.1); // 10% margin
            $minCellDepth = max($minCellDepth, (float)($item['depth'] ?? 0) * 1.1);
        }

        // Recalculate grid with minimum cell sizes
        $maxColumns = max(1, (int)floor($roomWidth / $minCellWidth));
        $maxRows = max(1, (int)floor($roomDepth / $minCellDepth));

        $numProducts = count(array_unique(array_column($items, 'product_id')));
        $columns = min($grid['columns'], $maxColumns);
        $rows = min($grid['rows'], $maxRows);

        // Ensure enough cells
        while ($columns * $rows < $numProducts && ($columns < $maxColumns || $rows < $maxRows)) {
            if ($columns < $maxColumns) {
                $columns++;
            } elseif ($rows < $maxRows) {
                $rows++;
            } else {
                break;
            }
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'cell_width' => $roomWidth / $columns,
            'cell_depth' => $roomDepth / $rows,
        ];
    }

    /**
     * Simple grid calculation (fallback).
     */
    private function calculateSimpleGrid(
        float $roomWidth,
        float $roomDepth,
        int $numProducts,
        array $options
    ): array {
        $requestedColumns = $options['columns'] ?? null;
        $requestedRows = $options['rows'] ?? null;

        // Calculate optimal grid based on room aspect ratio and number of products
        $aspectRatio = $roomWidth / $roomDepth;
        $totalCells = max($numProducts, 1);

        // Calculate columns and rows to fit all products
        if ($requestedColumns) {
            $columns = $requestedColumns;
            $rows = (int) ceil($totalCells / $columns);
        } elseif ($requestedRows) {
            $rows = $requestedRows;
            $columns = (int) ceil($totalCells / $rows);
        } else {
            // Auto-calculate: aim for square-ish grid
            $columns = (int) ceil(sqrt($totalCells * $aspectRatio));
            $rows = (int) ceil($totalCells / $columns);
        }

        // Ensure minimum dimensions
        $columns = max(1, $columns);
        $rows = max(1, $rows);

        return [
            'columns' => $columns,
            'rows' => $rows,
            'cell_width' => $roomWidth / $columns,
            'cell_depth' => $roomDepth / $rows,
        ];
    }

    /**
     * Get compartment boundary for a product in the grid.
     *
     * @param int $productId Product ID
     * @param int $column Grid column (0-indexed)
     * @param int $row Grid row (0-indexed)
     * @param float $cellWidth Cell width
     * @param float $cellDepth Cell depth
     * @param float $roomWidth Room width
     * @param float $roomDepth Room depth
     * @return array{x: float, y: float, width: float, depth: float}
     */
    public function getCompartmentBoundary(
        int $productId,
        int $column,
        int $row,
        float $cellWidth,
        float $cellDepth,
        float $roomWidth,
        float $roomDepth
    ): array {
        $x = $column * $cellWidth;
        $y = $row * $cellDepth;

        // Ensure boundaries don't exceed room dimensions
        $width = min($cellWidth, $roomWidth - $x);
        $depth = min($cellDepth, $roomDepth - $y);

        return [
            'product_id' => $productId,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'depth' => $depth,
            'column' => $column,
            'row' => $row,
        ];
    }

    /**
     * Calculate next grid position (column, row) for compartment filling.
     *
     * @param int $currentColumn Current column
     * @param int $currentRow Current row
     * @param int $gridColumns Total columns
     * @param float $currentColumnHeight Current height in column
     * @param float $maxColumnHeight Maximum height for column
     * @return array{column: int, row: int, shouldMoveColumn: bool}
     */
    public function getNextGridPosition(
        int $currentColumn,
        int $currentRow,
        int $gridColumns,
        float $currentColumnHeight,
        float $maxColumnHeight
    ): array {
        $shouldMoveColumn = $currentColumnHeight >= $maxColumnHeight;

        if ($shouldMoveColumn) {
            $currentColumn++;
            if ($currentColumn >= $gridColumns) {
                $currentRow++;
                $currentColumn = 0;
            }
        }

        return [
            'column' => $currentColumn,
            'row' => $currentRow,
            'shouldMoveColumn' => $shouldMoveColumn,
        ];
    }
}
