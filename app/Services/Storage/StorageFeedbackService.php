<?php

namespace App\Services\Storage;

class StorageFeedbackService
{
    /**
     * Generate user-friendly feedback messages.
     *
     * @return array
     */
    public function generateFeedback(array $suggestions): array
    {
        $recommendations = [];
        $warnings = [];

        if (empty($suggestions['placement_options'])) {
            return [
                'recommendations' => ['No storage space available for this product'],
                'warnings' => ['Consider removing items or expanding room capacity'],
            ];
        }

        $stackOptions = array_filter($suggestions['placement_options'], function ($opt) {
            return $opt['stack_on_existing'] ?? false;
        });

        $newStackOptions = array_filter($suggestions['placement_options'], function ($opt) {
            return ! ($opt['stack_on_existing'] ?? false);
        });

        if (! empty($stackOptions)) {
            $bestStack = $stackOptions[0];
            $recommendations[] = sprintf(
                'Recommended: Stack %d items on existing stack at position (%.0f, %.0f) starting from Z=%.0f',
                $bestStack['can_fit_quantity'],
                $bestStack['x_position'],
                $bestStack['y_position'],
                $bestStack['z_position']
            );
        }

        if (! empty($newStackOptions)) {
            $bestNew = $newStackOptions[0];
            $recommendations[] = sprintf(
                'Remaining items can start new stack at position (%.0f, %.0f)',
                $bestNew['x_position'],
                $bestNew['y_position']
            );
        }

        if (! empty($suggestions['placement_options'])) {
            $utilization = $suggestions['placement_options'][0]['utilization_after'] ?? 0;
            $recommendations[] = sprintf(
                'Total space utilization will be %.1f%% after placement',
                $utilization
            );
        }

        return [
            'recommendations' => $recommendations,
            'warnings' => $warnings,
        ];
    }
}
