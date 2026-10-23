<?php

namespace PuffxHost\ClientDataExport;

use RuntimeException;

/** Serialize an export without persisting it on disk. */
final class FormatWriter
{
    public static function encodeJson($payload)
    {
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            throw new RuntimeException('The export could not be encoded as JSON.');
        }
        return $json . "\n";
    }

    /**
     * Produce a single long-form CSV, so all selected sections fit one file:
     * record_type,record_id,client_id,field,value.
     */
    public static function encodeCsv($payload)
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('The CSV export could not be prepared.');
        }

        // Excel-compatible UTF-8; data itself remains UTF-8.
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['record_type', 'record_id', 'client_id', 'field', 'value'], ',', '"', '\\');

        $clientId = (string) $payload['meta']['client_id'];
        foreach ($payload['data'] as $section => $records) {
            foreach ($records as $record) {
                $recordId = isset($record['id']) ? (string) $record['id'] : '';
                foreach ($record as $field => $value) {
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                    }
                    $row = [
                        self::safeCsvCell($section),
                        self::safeCsvCell($recordId),
                        self::safeCsvCell($clientId),
                        self::safeCsvCell($field),
                        self::safeCsvCell($value === null ? '' : (string) $value),
                    ];
                    fputcsv($stream, $row, ',', '"', '\\');
                }
            }
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        if ($csv === false) {
            throw new RuntimeException('The CSV export could not be read.');
        }
        return $csv;
    }

    /** Neutralize formula-leading cells while retaining RFC 4180 quoting. */
    public static function safeCsvCell($value)
    {
        $value = (string) $value;
        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}

