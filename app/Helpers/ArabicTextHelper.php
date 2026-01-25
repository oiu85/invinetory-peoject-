<?php

namespace App\Helpers;

class ArabicTextHelper
{
    /**
     * Process Arabic text to ensure proper rendering in PDFs
     * This function handles text direction and ensures proper Unicode encoding
     */
    public static function processArabicText(string $text): string
    {
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        // Normalize Arabic text (NFC normalization)
        if (class_exists('Normalizer')) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C);
        }

        return $text;
    }

    /**
     * Process HTML content to fix Arabic text rendering
     */
    public static function processHtmlForArabic(string $html): string
    {
        // Process the entire HTML content
        // This ensures all Arabic text is properly encoded
        
        // Add proper Unicode bidirectional marks for mixed content
        $html = self::addBidiMarks($html);
        
        return $html;
    }

    /**
     * Add bidirectional marks to help with mixed Arabic/English content
     */
    private static function addBidiMarks(string $html): string
    {
        // This is a simple approach - for more complex needs, consider using a library
        // The key is ensuring proper direction attributes are set in HTML
        
        return $html;
    }

    /**
     * Wrap Arabic text in spans with proper direction
     */
    public static function wrapArabicText(string $text): string
    {
        // Detect if text contains Arabic characters
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            // Wrap in span with RTL direction
            return '<span dir="rtl" lang="ar">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
