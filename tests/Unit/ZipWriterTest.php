<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../bll/ZipWriter.php';
require_once __DIR__ . '/../Support/ZipReader.php';

/**
 * ZipWriter — the archive format under every .xlsx export.
 */
final class ZipWriterTest extends TestCase
{
    /** @return array<string, string> */
    private static function sampleFiles(): array
    {
        return [
            'a.txt'               => 'hello',
            'empty.txt'           => '',
            'dir/nested.xml'      => str_repeat('<row>compressible</row>', 500),
            'binary.bin'          => random_bytes(2048),
            'ç-acentuação/ü.txt'  => 'UTF-8 names (flag bit 11)',
        ];
    }

    private static function build(array $files): string
    {
        $zip = new ZipWriter(mktime(12, 30, 10, 11, 4, 2026));
        foreach ($files as $name => $data) {
            $zip->addFile($name, $data);
        }
        return $zip->toString();
    }

    public function testRoundTripKeepsEveryFileByteForByte(): void
    {
        $files = self::sampleFiles();
        $this->assertSame($files, ZipReader::read(self::build($files)));
    }

    public function testStartsWithLocalHeaderAndEndsWithEndRecord(): void
    {
        $zip = self::build(['a.txt' => 'x']);
        $this->assertSame("PK\x03\x04", substr($zip, 0, 4));
        $this->assertSame("PK\x05\x06", substr($zip, -22, 4)); // no archive comment → end record is the last 22 bytes
    }

    public function testRepetitiveDataIsActuallyCompressed(): void
    {
        $xml = str_repeat('<row>compressible</row>', 500); // 11.5 KB
        $this->assertLessThan(strlen($xml) / 10, strlen(self::build(['x.xml' => $xml])));
    }

    public function testEmptyArchiveIsStillValid(): void
    {
        $this->assertSame([], ZipReader::read((new ZipWriter())->toString()));
    }

    public function testUnsafeNamesAreRefused(): void
    {
        foreach (['', '/etc/passwd', '../escape.txt', 'a/../../b', 'win\\path.txt'] as $name) {
            try {
                (new ZipWriter())->addFile($name, 'x');
                $this->fail("Accepted unsafe name: {$name}");
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** Second opinion from a reader we didn't write (only where ext-zip is enabled, e.g. CI). */
    public function testPhpZipArchiveReadsItToo(): void
    {
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ext-zip not enabled (normal on XAMPP)');
        }
        $files = self::sampleFiles();
        $path = tempnam(sys_get_temp_dir(), 'zw');
        file_put_contents($path, self::build($files));

        $archive = new ZipArchive();
        $this->assertTrue($archive->open($path, ZipArchive::CHECKCONS));
        foreach ($files as $name => $data) {
            $this->assertSame($data, $archive->getFromName($name), $name);
        }
        $archive->close();
        unlink($path);
    }
}
