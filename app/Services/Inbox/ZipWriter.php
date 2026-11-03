<?php

declare(strict_types=1);

namespace App\Services\Inbox;

/**
 * Einfaches ZIP (unkomprimiert, „stored“) ohne ext-zip. Fotos/PDFs lassen sich ohnehin kaum packen.
 */
final class ZipWriter
{
    /** @var list<array{name:string, crc:int, size:int, offset:int}> */
    private array $entries = [];
    private string $data = '';

    public function add(string $name, string $content): void
    {
        $name = ltrim(str_replace('\\', '/', $name), '/');
        $crc = crc32($content);
        $size = strlen($content);
        [$time, $date] = self::dosTime();
        $this->entries[] = ['name' => $name, 'crc' => $crc, 'size' => $size, 'offset' => strlen($this->data), 'time' => $time, 'date' => $date];
        // Bit 11: Dateiname in UTF-8
        $this->data .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $date, $crc, $size, $size, strlen($name), 0)
            . $name . $content;
    }

    public function bytes(): string
    {
        $central = '';
        foreach ($this->entries as $e) {
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $e['time'], $e['date'], $e['crc'],
                $e['size'], $e['size'], strlen($e['name']), 0, 0, 0, 0, 0, $e['offset']) . $e['name'];
        }
        $count = count($this->entries);

        return $this->data . $central
            . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($this->data), 0);
    }

    /** @return array{0:int, 1:int} */
    private static function dosTime(): array
    {
        $t = getdate();

        return [
            ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2),
            (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'],
        ];
    }
}
