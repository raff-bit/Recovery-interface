<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../bll/XlsxWriter.php';
require_once __DIR__ . '/../Support/ZipReader.php';

/**
 * XlsxWriter — the workbook XML, and the security rules for user text.
 */
final class XlsxWriterTest extends TestCase
{
    /** @return array<string, string> */
    private static function buildBook(array $rows): array
    {
        $book = new XlsxWriter();
        $book->addSheet('Data', [['When', 'datetime', 17], ['Text', 'text'], ['Number', 'number']], $rows);
        return ZipReader::read($book->toBinary());
    }

    // ---------------- package structure ----------------

    public function testWorkbookHasEveryRequiredPartAndAllXmlIsWellFormed(): void
    {
        $files = self::buildBook([[new DateTimeImmutable('2026-11-04 18:30'), 'Bench', 60]]);

        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'] as $part) {
            $this->assertArrayHasKey($part, $files, "missing {$part}");
            $dom = new DOMDocument();
            $this->assertTrue(@$dom->loadXML($files[$part]), "{$part} is not well-formed XML");
        }
    }

    public function testEachSheetIsListedInWorkbookAndContentTypes(): void
    {
        $book = new XlsxWriter();
        $book->addSheet('Sessions', [['A', 'text']], []);
        $book->addSheet('Sets', [['A', 'text']], []);
        $files = ZipReader::read($book->toBinary());

        $this->assertStringContainsString('<sheet name="Sessions" sheetId="1" r:id="rId1"/>', $files['xl/workbook.xml']);
        $this->assertStringContainsString('<sheet name="Sets" sheetId="2" r:id="rId2"/>', $files['xl/workbook.xml']);
        $this->assertStringContainsString('/xl/worksheets/sheet2.xml', $files['[Content_Types].xml']);
        $this->assertArrayHasKey('xl/worksheets/sheet2.xml', $files);
    }

    public function testHeaderIsBoldFrozenAndFiltered(): void
    {
        $sheet = self::buildBook([[null, 'x', 1]])['xl/worksheets/sheet1.xml'];
        $this->assertStringContainsString('<c r="A1" t="inlineStr" s="1">', $sheet);
        $this->assertStringContainsString('state="frozen"', $sheet);
        $this->assertStringContainsString('<autoFilter ref="A1:C2"/>', $sheet);
    }

    // ---------------- security: user text ----------------

    public function testFormulaLookingTextStaysTextNeverAFormula(): void
    {
        $sheet = self::buildBook([[null, '=1+1', null], [null, '=HYPERLINK("http://evil","x")', null]])['xl/worksheets/sheet1.xml'];

        $this->assertStringNotContainsString('<f>', $sheet, 'no formula element may ever be written');
        $this->assertStringContainsString('<c r="B2" t="inlineStr"><is><t xml:space="preserve">=1+1</t></is></c>', $sheet);
    }

    public function testMarkupIsEscaped(): void
    {
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &apos;', XlsxWriter::xmlText('<script>alert("x")</script> & \''));
    }

    public function testCharactersForbiddenByXmlAreRemovedButTabsAndNewlinesKept(): void
    {
        $this->assertSame("ab\tc\nd", XlsxWriter::xmlText("a\x00b\tc\nd\x1F\x0B"));
    }

    public function testInvalidUtf8DoesNotBreakTheFile(): void
    {
        $files = self::buildBook([[null, "ok \xC3\x28 broken", null]]);
        $this->assertTrue((new DOMDocument())->loadXML($files['xl/worksheets/sheet1.xml']));
    }

    public function testTextIsCappedAtExcelsCellLimit(): void
    {
        $this->assertSame(XlsxWriter::MAX_CELL_CHARS, mb_strlen(XlsxWriter::xmlText(str_repeat('é', 40000))));
    }

    // ---------------- values ----------------

    public function testExcelSerialUsesTheWallClockOfTheGivenZone(): void
    {
        $this->assertSame(25569.0, XlsxWriter::excelSerial(new DateTimeImmutable('1970-01-01 00:00', new DateTimeZone('UTC'))));

        // 18:30 Lisbon summer time (UTC+1) must still be 18:30 in the cell: fraction 18.5 / 24
        $summer = new DateTimeImmutable('2026-07-01 18:30', new DateTimeZone('Europe/Lisbon'));
        $serial = XlsxWriter::excelSerial($summer);
        $this->assertEqualsWithDelta(18.5 / 24, $serial - floor($serial), 1e-9);
        $this->assertSame(46204.0, floor($serial)); // Excel shows 46204 as 2026-07-01
    }

    public function testNumbersAndDatesAreWrittenAsNumericCells(): void
    {
        $sheet = self::buildBook([[new DateTimeImmutable('1970-01-02 12:00', new DateTimeZone('UTC')), null, 62.5], [null, null, 0.1 + 0.2]])['xl/worksheets/sheet1.xml'];
        $this->assertStringContainsString('<c r="A2" s="2"><v>25570.5</v></c>', $sheet);
        $this->assertStringContainsString('<c r="C2"><v>62.5</v></c>', $sheet);
        $this->assertStringContainsString('<c r="C3"><v>0.3</v></c>', $sheet);
    }

    public function testEmptyValuesAreOmitted(): void
    {
        $sheet = self::buildBook([[null, '', null]])['xl/worksheets/sheet1.xml'];
        $this->assertStringContainsString('<row r="2"></row>', $sheet);
    }

    public function testColumnLetters(): void
    {
        $this->assertSame(['A', 'Z', 'AA', 'AZ', 'ZZ', 'AAA'], array_map([XlsxWriter::class, 'columnLetter'], [0, 25, 26, 51, 701, 702]));
    }

    // ---------------- rejected input ----------------

    public function testInvalidDefinitionsAreRejected(): void
    {
        $cases = [
            'name with /'     => fn() => (new XlsxWriter())->addSheet('a/b', [['A', 'text']], []),
            'name too long'   => fn() => (new XlsxWriter())->addSheet(str_repeat('x', 32), [['A', 'text']], []),
            'unknown type'    => fn() => (new XlsxWriter())->addSheet('S', [['A', 'formula']], []),
            'no columns'      => fn() => (new XlsxWriter())->addSheet('S', [], []),
            'not a date'      => function () {
                $b = new XlsxWriter();
                $b->addSheet('S', [['A', 'datetime']], [['2026-11-04']]);
                $b->toBinary();
            },
            'not a number'    => function () {
                $b = new XlsxWriter();
                $b->addSheet('S', [['A', 'number']], [['12 kg']]);
                $b->toBinary();
            },
            'empty workbook'  => fn() => (new XlsxWriter())->toBinary(),
        ];
        foreach ($cases as $why => $fn) {
            try {
                $fn();
                $this->fail("Accepted: {$why}");
            } catch (InvalidArgumentException | LogicException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
