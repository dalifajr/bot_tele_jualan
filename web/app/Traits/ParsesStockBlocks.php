<?php

namespace App\Traits;

trait ParsesStockBlocks
{
    /**
     * Split raw bulk stock text into individual clean account blocks.
     * Supports:
     * 1. Line dividers (===..., ___..., ---..., ###...)
     * 2. Double-newlines (\n\s*\n)
     * 3. Single-line pipe / line fallback
     */
    public function splitStockBlocks(string $rawText): array
    {
        if (empty(trim($rawText))) {
            return [];
        }

        // Standardize newline characters
        $normalized = str_replace(["\r\n", "\r"], "\n", $rawText);

        // Check if raw text contains divider lines (e.g. 3 or more '=', '_', '-', or '#')
        if (preg_match('/^\s*[=_#-]{3,}\s*$/m', $normalized)) {
            // Split by divider lines
            $rawBlocks = preg_split('/(?:\n|^)\s*[=_#-]{3,}\s*(?:\n|$)/m', $normalized);
        } else {
            // Split by double-newlines
            $rawBlocks = preg_split('/\n\s*\n/', $normalized);
        }

        $cleanBlocks = [];
        foreach ($rawBlocks as $block) {
            $trimmed = trim($block);
            // Remove any leading/trailing solitary divider lines from the block if any remain
            $trimmed = preg_replace('/^\s*[=_#-]{3,}\s*\n?|\n?\s*[=_#-]{3,}\s*$/m', '', $trimmed);
            $trimmed = trim($trimmed);

            if (!empty($trimmed)) {
                $cleanBlocks[] = $trimmed;
            }
        }

        return $cleanBlocks;
    }
}
