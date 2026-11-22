<?php

namespace App\Support;

class CsvSanitizer
{
    public static function cell(mixed $value): string
    {
        if ($value === null) return '';
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $value = (string) $value;
        // Prevent spreadsheet formula execution when a CSV is opened in Excel/LibreOffice.
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) $value = "'" . $value;
        return $value;
    }
}
