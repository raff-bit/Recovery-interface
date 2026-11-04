<?php

/**
 * ZipWriter — the smallest ZIP archive writer that Excel and LibreOffice accept.
 * ------------------------------------------------------------
 * Why not PHP's ZipArchive? ext-zip is OFF by default in XAMPP, and an app
 * that needs php.ini changes before it can export is a broken app.
 * An .xlsx file is just a ZIP of XML files, and the ZIP format is simple:
 *
 *   [local header 1][compressed data 1] … [local header N][data N]
 *   [central directory: one entry per file, pointing back at its local header]
 *   [end record: how many entries, where the central directory starts]
 *
 * Compression is raw DEFLATE (gzdeflate, part of zlib, always on in XAMPP);
 * integrity is a CRC-32 of the uncompressed bytes, checked by every reader.
 * Spec: PKWARE APPNOTE.TXT, sections 4.3.7 (local), 4.3.12 (central), 4.3.16 (end).
 */
final class ZipWriter
{
    private const SIG_LOCAL   = 0x04034b50;
    private const SIG_CENTRAL = 0x02014b50;
    private const SIG_END     = 0x06054b50;
    private const VERSION     = 20;      // "2.0": needed for DEFLATE
    private const FLAG_UTF8   = 0x0800;  // bit 11: file names are UTF-8
    private const STORE       = 0;
    private const DEFLATE     = 8;
    private const MAX_UINT32  = 0xFFFFFFFF; // no ZIP64: 4 GiB / 65535 files is plenty for an export

    /** @var list<array{name: string, crc: int, csize: int, usize: int, method: int, offset: int}> */
    private array $entries = [];
    private string $body = '';
    private int $dosTime;
    private int $dosDate;

    public function __construct(?int $timestamp = null)
    {
        [$this->dosTime, $this->dosDate] = self::dosDateTime($timestamp ?? time());
    }

    public function addFile(string $name, string $data): void
    {
        if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, '\\')) {
            throw new InvalidArgumentException("Unsafe file name in archive: {$name}");
        }
        if (count($this->entries) >= 0xFFFF) {
            throw new LengthException('Too many files for a non-ZIP64 archive.');
        }

        $deflated = function_exists('gzdeflate') ? gzdeflate($data, 6) : false;
        $method   = $deflated !== false ? self::DEFLATE : self::STORE;
        $payload  = $deflated !== false ? $deflated : $data;
        $crc      = crc32($data); // of the ORIGINAL bytes: the reader checks it after inflating
        $offset   = strlen($this->body);

        if (strlen($data) > self::MAX_UINT32 || $offset + strlen($payload) > self::MAX_UINT32) {
            throw new LengthException('Archive larger than 4 GiB needs ZIP64.');
        }

        // V = 32-bit little-endian, v = 16-bit little-endian (ZIP is little-endian throughout)
        $this->body .= pack(
            'VvvvvvVVVvv',
            self::SIG_LOCAL,
            self::VERSION,
            self::FLAG_UTF8,
            $method,
            $this->dosTime,
            $this->dosDate,
            $crc,
            strlen($payload),   // compressed size
            strlen($data),      // uncompressed size
            strlen($name),
            0                   // extra field length
        ) . $name . $payload;

        $this->entries[] = [
            'name'   => $name,
            'crc'    => $crc,
            'csize'  => strlen($payload),
            'usize'  => strlen($data),
            'method' => $method,
            'offset' => $offset,
        ];
    }

    /** The finished archive as a binary string. */
    public function toString(): string
    {
        $central = '';
        foreach ($this->entries as $e) {
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                self::SIG_CENTRAL,
                self::VERSION,      // version made by
                self::VERSION,      // version needed to extract
                self::FLAG_UTF8,
                $e['method'],
                $this->dosTime,
                $this->dosDate,
                $e['crc'],
                $e['csize'],
                $e['usize'],
                strlen($e['name']),
                0,                  // extra field length
                0,                  // comment length
                0,                  // disk number start
                0,                  // internal attributes
                0,                  // external attributes
                $e['offset']        // where this file's local header starts
            ) . $e['name'];
        }

        $end = pack(
            'VvvvvVVv',
            self::SIG_END,
            0,                       // this disk
            0,                       // disk where the central directory starts
            count($this->entries),   // entries on this disk
            count($this->entries),   // entries in total
            strlen($central),
            strlen($this->body),     // central directory offset = right after the last file
            0                        // comment length
        );

        return $this->body . $central . $end;
    }

    /**
     * MS-DOS date/time, the only timestamp format the basic ZIP header has.
     * time: hhhhh mmmmmm sssss (seconds / 2) · date: yyyyyyy mmmm ddddd (years since 1980)
     * @return array{0: int, 1: int}
     */
    private static function dosDateTime(int $timestamp): array
    {
        $d = getdate($timestamp);
        $year = max(1980, min(2107, $d['year']));
        $time = ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2);
        $date = (($year - 1980) << 9) | ($d['mon'] << 5) | $d['mday'];
        return [$time, $date];
    }
}
