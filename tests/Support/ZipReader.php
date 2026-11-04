<?php

/**
 * ZipReader — test helper: reads the archives ZipWriter produces, independently of it.
 * ------------------------------------------------------------
 * Walks the format the way a real reader does (end record → central directory →
 * each local header), inflates every file and checks its CRC-32.
 * Lets the tests open our .xlsx without ext-zip (off in XAMPP).
 */
final class ZipReader
{
    /**
     * @return array<string, string> file name → uncompressed contents
     * @throws RuntimeException if anything about the archive is wrong
     */
    public static function read(string $zip): array
    {
        $endPos = strrpos($zip, pack('V', 0x06054b50));
        if ($endPos === false) {
            throw new RuntimeException('No end-of-central-directory record');
        }
        $end = unpack('Vsig/vdisk/vcdDisk/vdiskEntries/ventries/VcdSize/VcdOffset/vcommentLen', substr($zip, $endPos, 22));
        if ($end === false || $end['cdOffset'] + $end['cdSize'] !== $endPos) {
            throw new RuntimeException('Central directory size/offset do not add up');
        }

        $files = [];
        $pos = $end['cdOffset'];
        for ($i = 0; $i < $end['entries']; $i++) {
            $c = unpack('Vsig/vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnameLen/vextraLen/vcommentLen/vdisk/vinternal/Vexternal/Voffset', substr($zip, $pos, 46));
            if ($c === false || $c['sig'] !== 0x02014b50) {
                throw new RuntimeException("Bad central directory entry #{$i}");
            }
            $name = substr($zip, $pos + 46, $c['nameLen']);
            $pos += 46 + $c['nameLen'] + $c['extraLen'] + $c['commentLen'];

            $l = unpack('Vsig/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnameLen/vextraLen', substr($zip, $c['offset'], 30));
            if ($l === false || $l['sig'] !== 0x04034b50 || $l['crc'] !== $c['crc'] || substr($zip, $c['offset'] + 30, $l['nameLen']) !== $name) {
                throw new RuntimeException("Local header of {$name} does not match the central directory");
            }
            $raw = substr($zip, $c['offset'] + 30 + $l['nameLen'] + $l['extraLen'], $c['csize']);
            $data = match ($c['method']) {
                0 => $raw,
                8 => gzinflate($raw),
                default => throw new RuntimeException("Unsupported method {$c['method']} for {$name}"),
            };
            if ($data === false || strlen($data) !== $c['usize'] || crc32($data) !== $c['crc']) {
                throw new RuntimeException("Corrupt data in {$name}");
            }
            $files[$name] = $data;
        }
        return $files;
    }
}
