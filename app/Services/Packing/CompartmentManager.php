<?php

namespace App\Services\Packing;

class CompartmentManager
{
    /**
     * Calculate optimal grid dimensions based on room size and number of products.
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
