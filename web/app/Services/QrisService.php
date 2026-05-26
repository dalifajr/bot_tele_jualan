<?php

namespace App\Services;

class QrisService
{
    /**
     * Builds a dynamic QRIS payload by inserting the amount into the static payload.
     * Replicates the logic from Python's qris_service.py.
     *
     * @param string $staticPayload
     * @param int $amount
     * @return string
     * @throws \Exception
     */
    public static function buildDynamicPayload(string $staticPayload, int $amount): string
    {
        if ($amount <= 0) {
            throw new \Exception("Nominal QRIS harus lebih dari 0.");
        }

        $normalized = self::normalizePayload($staticPayload);
        $fields = self::parseTlvWithSpaceFallback($normalized);
        
        $fieldsWithoutCrc = self::stripCrcField($fields);
        
        // Upsert tag 01 with value 12 (Point of Initiation Method = Dynamic)
        $fieldsWithoutCrc = self::upsertTag($fieldsWithoutCrc, '01', '12', ['00']);
        
        // Upsert tag 54 with the amount
        $fieldsWithoutCrc = self::upsertTag($fieldsWithoutCrc, '54', (string)$amount, ['53', '52', '01', '00']);

        $payloadWithoutCrc = self::encodeTlvFields($fieldsWithoutCrc);
        $crcBase = $payloadWithoutCrc . "6304";
        $crc = self::crc16CcittFalse($crcBase);
        
        return $crcBase . $crc;
    }

    private static function normalizePayload(string $payload): string
    {
        $normalized = trim(str_replace(["\r", "\n", "\t"], "", $payload));
        if (empty($normalized)) {
            throw new \Exception("Payload QRIS tidak boleh kosong.");
        }
        if (!mb_check_encoding($normalized, 'ASCII')) {
            throw new \Exception("Payload QRIS harus ASCII.");
        }
        return $normalized;
    }

    private static function parseTlvWithSpaceFallback(string $payload): array
    {
        try {
            return self::parseTlv($payload);
        } catch (\Exception $e) {
            if (!str_contains($payload, ' ')) {
                throw $e;
            }
            $compactPayload = str_replace(' ', '', $payload);
            return self::parseTlv($compactPayload);
        }
    }

    private static function parseTlv(string $payload): array
    {
        $fields = [];
        $index = 0;
        $len = strlen($payload);

        while ($index < $len) {
            if ($index + 4 > $len) {
                throw new \Exception("Payload QRIS tidak valid: header TLV terpotong.");
            }

            $tag = substr($payload, $index, 2);
            $lengthText = substr($payload, $index + 2, 2);
            
            if (!is_numeric($tag) || !is_numeric($lengthText)) {
                throw new \Exception("Payload QRIS tidak valid: format TLV salah.");
            }

            $valueLength = (int)$lengthText;
            $valueStart = $index + 4;
            $valueEnd = $valueStart + $valueLength;

            if ($valueEnd > $len) {
                throw new \Exception("Payload QRIS tidak valid: panjang field melebihi payload.");
            }

            $value = substr($payload, $valueStart, $valueLength);
            $fields[] = ['tag' => $tag, 'value' => $value];
            $index = $valueEnd;
        }

        return $fields;
    }

    private static function stripCrcField(array $fields): array
    {
        $crcIndexes = [];
        foreach ($fields as $idx => $field) {
            if ($field['tag'] === '63') {
                $crcIndexes[] = $idx;
            }
        }

        if (empty($crcIndexes)) {
            return $fields;
        }

        if (count($crcIndexes) > 1 || $crcIndexes[0] !== count($fields) - 1) {
            throw new \Exception("Payload QRIS tidak valid: field CRC harus berada di akhir.");
        }

        array_pop($fields); // Remove the last field (CRC)
        return $fields;
    }

    private static function upsertTag(array $fields, string $tag, string $value, array $preferredAfter): array
    {
        $cleaned = array_values(array_filter($fields, fn($f) => $f['tag'] !== $tag));
        $insertAt = count($cleaned);

        foreach ($preferredAfter as $prefTag) {
            foreach ($cleaned as $idx => $f) {
                if ($f['tag'] === $prefTag) {
                    $insertAt = $idx + 1;
                    break 2;
                }
            }
        }

        array_splice($cleaned, $insertAt, 0, [['tag' => $tag, 'value' => $value]]);
        return $cleaned;
    }

    private static function encodeTlvFields(array $fields): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $tag = $field['tag'];
            $value = $field['value'];
            
            if (strlen($tag) !== 2 || !is_numeric($tag)) {
                throw new \Exception("Payload QRIS tidak valid: tag harus 2 digit.");
            }
            if (strlen($value) > 99) {
                throw new \Exception("Payload QRIS tidak valid: panjang field {$tag} melebihi 99.");
            }
            
            $lenStr = str_pad((string)strlen($value), 2, '0', STR_PAD_LEFT);
            $parts[] = $tag . $lenStr . $value;
        }
        return implode('', $parts);
    }

    private static function crc16CcittFalse(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
