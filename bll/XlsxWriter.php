<?php

require_once __DIR__ . '/ZipWriter.php';

/**
 * XlsxWriter — builds a real Excel workbook (.xlsx) with no libraries.
 * ------------------------------------------------------------
 * An .xlsx is a ZIP (see ZipWriter) holding a handful of XML files:
 *   [Content_Types].xml          what each file inside is
 *   _rels/.rels                  "the workbook is xl/workbook.xml"
 *   xl/workbook.xml              the list of sheets
 *   xl/_rels/workbook.xml.rels   where each sheet's XML lives
 *   xl/styles.xml                bold header, date format
 *   xl/worksheets/sheetN.xml     the cells
 *
 * Security rules, enforced here so no caller can forget them:
 *   - Text is ALWAYS written as an inline string (t="inlineStr"). Excel never
 *     evaluates those, so a session called "=HYPERLINK(...)" stays plain text
 *     (no formula injection — the classic CSV-export hole).
 *   - Text is XML-escaped, invalid UTF-8 is repaired, and characters XML 1.0
 *     forbids (control chars) are removed, so user text can't break the file.
 *
 * Column types: 'text' | 'number' | 'datetime' (a DateTimeInterface, shown as
 * yyyy-mm-dd hh:mm in ITS OWN time zone — convert before passing it in).
 */
final class XlsxWriter
{
    public const MAX_CELL_CHARS = 32767;   // Excel's limit per cell
    public const MAX_ROWS       = 1048576; // Excel's limit per sheet (header included)

    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_REL  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_PKG  = 'http://schemas.openxmlformats.org/package/2006/relationships';

    // Indexes into <cellXfs> in styles.xml
    private const STYLE_DEFAULT  = 0;
    private const STYLE_HEADER   = 1;
    private const STYLE_DATETIME = 2;

    private const TYPES = ['text', 'number', 'datetime'];

    /** @var list<array{name: string, columns: list<array{title: string, type: string, width: float}>, rows: list<list<mixed>>}> */
    private array $sheets = [];

    /**
     * @param list<array{0: string, 1: string, 2?: float}> $columns [title, type, width in characters]
     * @param list<list<mixed>> $rows one list of values per row, in column order (null = empty cell)
     */
    public function addSheet(string $name, array $columns, array $rows): void
    {
        if ($name === '' || mb_strlen($name) > 31 || preg_match('#[\[\]:*?/\\\\]#', $name)) {
            throw new InvalidArgumentException("Invalid sheet name: {$name}");
        }
        foreach ($this->sheets as $s) {
            if (mb_strtolower($s['name']) === mb_strtolower($name)) {
                throw new InvalidArgumentException("Duplicate sheet name: {$name}");
            }
        }
        if ($columns === []) {
            throw new InvalidArgumentException('A sheet needs at least one column.');
        }
        if (count($rows) + 1 > self::MAX_ROWS) {
            throw new LengthException("Too many rows for one sheet: {$name}");
        }

        $cols = [];
        foreach ($columns as $c) {
            if (!in_array($c[1], self::TYPES, true)) {
                throw new InvalidArgumentException("Unknown column type: {$c[1]}");
            }
            $cols[] = ['title' => $c[0], 'type' => $c[1], 'width' => (float) ($c[2] ?? 12)];
        }

        $this->sheets[] = ['name' => $name, 'columns' => $cols, 'rows' => $rows];
    }

    /** The finished workbook as a binary string, ready to send. */
    public function toBinary(?int $timestamp = null): string
    {
        if ($this->sheets === []) {
            throw new LogicException('A workbook needs at least one sheet.');
        }

        $zip = new ZipWriter($timestamp);
        $zip->addFile('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFile('_rels/.rels', $this->rootRelsXml());
        $zip->addFile('xl/workbook.xml', $this->workbookXml());
        $zip->addFile('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFile('xl/styles.xml', $this->stylesXml());
        foreach ($this->sheets as $i => $sheet) {
            $zip->addFile('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->sheetXml($sheet));
        }
        return $zip->toString();
    }

    /* ---------------- text & value helpers (public: unit-tested directly) ---------------- */

    /** User text → safe XML text: valid UTF-8, no forbidden characters, ≤ 32767 chars, escaped. */
    public static function xmlText(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');                                   // invalid bytes → "?"
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text); // XML 1.0 forbids these
        $text = (string) preg_replace('/[\x{FFFE}\x{FFFF}]/u', '', $text);
        $text = mb_substr($text, 0, self::MAX_CELL_CHARS);
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Excel stores a date-time as a number: days since 1899-12-30, the fraction being the time.
     * We use the WALL-CLOCK time of $dt (its own time zone), because Excel has no time zones:
     * 2026-11-04 18:30 in Lisbon must show as 18:30, whatever the server's zone is.
     */
    public static function excelSerial(DateTimeInterface $dt): float
    {
        $wallClockAsUtc = new DateTimeImmutable($dt->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
        return $wallClockAsUtc->getTimestamp() / 86400 + 25569; // 25569 = days from 1899-12-30 to 1970-01-01
    }

    /** 0 → A, 25 → Z, 26 → AA … */
    public static function columnLetter(int $index): string
    {
        $letters = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + ($n - 1) % 26) . $letters;
        }
        return $letters;
    }

    /* ---------------- XML parts ---------------- */

    private static function xmlHeader(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    }

    private function contentTypesXml(): string
    {
        $sheetMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml';
        $overrides = '';
        foreach (array_keys($this->sheets) as $i) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="' . $sheetMime . '"/>';
        }
        return self::xmlHeader()
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return self::xmlHeader()
            . '<Relationships xmlns="' . self::NS_PKG . '">'
            . '<Relationship Id="rId1" Type="' . self::NS_REL . '/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        $sheets = '';
        $filters = '';
        foreach ($this->sheets as $i => $s) {
            $sheets .= '<sheet name="' . self::xmlText($s['name']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
            // Excel keeps each sheet's filter range in this hidden name; without it some versions "repair" the file
            $range = '$A$1:$' . self::columnLetter(count($s['columns']) - 1) . '$' . (count($s['rows']) + 1);
            $quoted = "'" . str_replace("'", "''", $s['name']) . "'";
            $filters .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">'
                . self::xmlText($quoted . '!' . $range) . '</definedName>';
        }
        return self::xmlHeader()
            . '<workbook xmlns="' . self::NS_MAIN . '" xmlns:r="' . self::NS_REL . '">'
            . '<sheets>' . $sheets . '</sheets>'
            . '<definedNames>' . $filters . '</definedNames>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        $rels = '';
        foreach (array_keys($this->sheets) as $i) {
            $rels .= '<Relationship Id="rId' . ($i + 1) . '" Type="' . self::NS_REL . '/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $rels .= '<Relationship Id="rId' . (count($this->sheets) + 1) . '" Type="' . self::NS_REL . '/styles" Target="styles.xml"/>';
        return self::xmlHeader() . '<Relationships xmlns="' . self::NS_PKG . '">' . $rels . '</Relationships>';
    }

    private function stylesXml(): string
    {
        // fills 0 and 1 (none, gray125) are mandatory placeholders in every styles.xml
        return self::xmlHeader()
            . '<styleSheet xmlns="' . self::NS_MAIN . '">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="yyyy-mm-dd hh:mm"/></numFmts>'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                            // 0 default
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'              // 1 header
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'    // 2 date-time
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** @param array{name: string, columns: list<array{title: string, type: string, width: float}>, rows: list<list<mixed>>} $sheet */
    private function sheetXml(array $sheet): string
    {
        $columns = $sheet['columns'];
        $lastCol = self::columnLetter(count($columns) - 1);
        $lastRow = count($sheet['rows']) + 1;

        $cols = '';
        foreach ($columns as $i => $c) {
            $n = $i + 1;
            $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . $c['width'] . '" customWidth="1"/>';
        }

        // Row 1: bold header
        $xml = '<row r="1">';
        foreach ($columns as $i => $c) {
            $xml .= self::textCell(self::columnLetter($i) . '1', $c['title'], self::STYLE_HEADER);
        }
        $xml .= '</row>';

        foreach ($sheet['rows'] as $r => $values) {
            $rowNum = $r + 2;
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($columns as $i => $c) {
                $xml .= self::cell(self::columnLetter($i) . $rowNum, $c['type'], $values[$i] ?? null);
            }
            $xml .= '</row>';
        }

        return self::xmlHeader()
            . '<worksheet xmlns="' . self::NS_MAIN . '" xmlns:r="' . self::NS_REL . '">'
            . '<dimension ref="A1:' . $lastCol . $lastRow . '"/>'
            // Freeze the header row: it stays visible while scrolling
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . $xml . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastCol . $lastRow . '"/>'   // filter arrows on the header
            . '</worksheet>';
    }

    private static function cell(string $ref, string $type, mixed $value): string
    {
        if ($value === null || $value === '') {
            return ''; // empty cells are simply omitted
        }
        if ($type === 'datetime') {
            if (!$value instanceof DateTimeInterface) {
                throw new InvalidArgumentException("Cell {$ref}: expected a DateTimeInterface");
            }
            return '<c r="' . $ref . '" s="' . self::STYLE_DATETIME . '"><v>' . self::number(self::excelSerial($value)) . '</v></c>';
        }
        if ($type === 'number') {
            if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
                throw new InvalidArgumentException("Cell {$ref}: expected a number");
            }
            return '<c r="' . $ref . '"><v>' . self::number(+$value) . '</v></c>';
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException("Cell {$ref}: expected text");
        }
        return self::textCell($ref, (string) $value, self::STYLE_DEFAULT);
    }

    private static function textCell(string $ref, string $text, int $style): string
    {
        $s = $style !== self::STYLE_DEFAULT ? ' s="' . $style . '"' : '';
        return '<c r="' . $ref . '" t="inlineStr"' . $s . '><is><t xml:space="preserve">' . self::xmlText($text) . '</t></is></c>';
    }

    private static function number(int|float $n): string
    {
        if (is_float($n) && !is_finite($n)) {
            throw new InvalidArgumentException('Cannot write NaN or infinity to a cell.');
        }
        // Fixed 10 decimals, trailing zeros trimmed: "62.5", never "62,5" (locale) or "1.0E-5" (scientific)
        return is_int($n) ? (string) $n : rtrim(rtrim(sprintf('%.10F', $n), '0'), '.');
    }
}
