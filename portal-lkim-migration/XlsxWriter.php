<?php
/**
 * A small SpreadsheetML writer — enough to produce a styled .xlsx without
 * pulling in a dependency. This machine has no Python and the project has no
 * Composer packages, so the deliverable is built from the OOXML up.
 *
 * Supports inline strings, a fixed style palette, merged cells, column widths,
 * row heights and a frozen pane. That is all the checklist needs.
 */
class XlsxWriter
{
    /** Style ids, in the order they are written into styles.xml cellXfs. */
    public const S_DEFAULT   = 0;
    public const S_TITLE     = 1;
    public const S_SUBTITLE  = 2;
    public const S_HEADER    = 3;
    public const S_SECTION   = 4;
    public const S_CELL      = 5;
    public const S_CELL_WRAP = 6;
    public const S_BOLD      = 7;
    public const S_OK        = 8;   // COMPLETED
    public const S_PROGRESS  = 9;   // IN PROGRESS
    public const S_NONE      = 10;  // NOT STARTED YET
    public const S_EMPTY     = 11;  // NO CONTENT
    public const S_NOTRANS   = 12;  // NO TRANSLATION
    public const S_MUTED     = 13;
    public const S_LEGEND    = 14;

    private string $sheetName;
    private array $rows    = [];
    private array $merges  = [];
    private array $widths  = [];
    private array $heights = [];
    private ?string $freeze = null;

    public function __construct(string $sheetName = 'Sheet1')
    {
        $this->sheetName = $sheetName;
    }

    /**
     * Append a row. Each cell is either a scalar or [value, styleId].
     */
    public function addRow(array $cells, int $defaultStyle = self::S_CELL): void
    {
        $this->rows[] = ['cells' => $cells, 'style' => $defaultStyle];
    }

    public function mergeCells(string $range): void
    {
        $this->merges[] = $range;
    }

    public function setWidths(array $widths): void
    {
        $this->widths = $widths;
    }

    public function setRowHeight(int $rowIndex, float $height): void
    {
        $this->heights[$rowIndex] = $height;
    }

    public function freezePane(string $cell): void
    {
        $this->freeze = $cell;
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    /** 1 => A, 27 => AA */
    public static function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $rem   = ($index - 1) % 26;
            $name  = chr(65 + $rem) . $name;
            $index = (int) (($index - $rem - 1) / 26);
        }

        return $name;
    }

    public function save(string $path): void
    {
        @unlink($path);

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Cannot create ' . $path);
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet());
        $zip->close();
    }

    /* ── Parts ───────────────────────────────────────────────────────────── */

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . htmlspecialchars($this->sheetName, ENT_XML1) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function styles(): string
    {
        // Fonts: 0 body, 1 title, 2 white bold, 3 bold, 4 muted italic, 5 subtitle
        $fonts = '<fonts count="6">'
            . '<font><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><color rgb="FF002365"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FF002365"/><name val="Calibri"/></font>'
            . '<font><i/><sz val="9"/><color rgb="FF6B7280"/><name val="Calibri"/></font>'
            . '<font><sz val="11"/><color rgb="FF4D5A6B"/><name val="Calibri"/></font>'
            . '</fonts>';

        // Fills: 0/1 reserved, 2 navy, 3 gold, 4 green, 5 amber, 6 red, 7 grey, 8 blue
        $fills = '<fills count="9">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF002365"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3E3A8"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9F2E4"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFDF0D0"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFBDDDD"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFECEFF3"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE2ECF8"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>';

        $thin    = '<left style="thin"><color rgb="FFCFD6DE"/></left><right style="thin"><color rgb="FFCFD6DE"/></right>'
            . '<top style="thin"><color rgb="FFCFD6DE"/></top><bottom style="thin"><color rgb="FFCFD6DE"/></bottom>';
        $borders = '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>' . $thin . '<diagonal/></border></borders>';

        $left   = '<alignment vertical="center" horizontal="left"/>';
        $wrap   = '<alignment vertical="top" horizontal="left" wrapText="1"/>';
        $centre = '<alignment vertical="center" horizontal="center" wrapText="1"/>';

        // Order must match the S_* constants.
        $xfs = [
            '<xf fontId="0" fillId="0" borderId="0" xfId="0"/>',                                                        // default
            '<xf fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">' . $left . '</xf>',       // title
            '<xf fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">' . $left . '</xf>',       // subtitle
            '<xf fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $centre . '</xf>',
            '<xf fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $left . '</xf>',
            '<xf fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">' . $left . '</xf>',
            '<xf fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">' . $wrap . '</xf>',
            '<xf fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">' . $left . '</xf>',
            '<xf fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1">' . $centre . '</xf>',
            '<xf fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1">' . $centre . '</xf>',
            '<xf fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1">' . $centre . '</xf>',
            '<xf fontId="0" fillId="7" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1">' . $centre . '</xf>',
            '<xf fontId="0" fillId="8" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1">' . $centre . '</xf>',
            '<xf fontId="4" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">' . $wrap . '</xf>',
            '<xf fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">' . $left . '</xf>',
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $fonts . $fills . $borders
            . '<cellStyleXfs count="1"><xf fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($xfs) . '">' . implode('', $xfs) . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function sheet(): string
    {
        $cols = '';

        if ($this->widths) {
            $cols = '<cols>';
            foreach ($this->widths as $i => $w) {
                $n     = $i + 1;
                $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }

        $pane = '';

        if ($this->freeze !== null) {
            preg_match('/([A-Z]+)(\d+)/', $this->freeze, $m);
            $xSplit = 0;
            $col    = $m[1];

            for ($i = 0, $len = strlen($col); $i < $len; $i++) {
                $xSplit = $xSplit * 26 + (ord($col[$i]) - 64);
            }

            $xSplit--;
            $ySplit = (int) $m[2] - 1;

            $pane = '<pane xSplit="' . $xSplit . '" ySplit="' . $ySplit . '" topLeftCell="' . $this->freeze
                . '" activePane="bottomRight" state="frozen"/>';
        }

        $body = '';

        foreach ($this->rows as $r => $row) {
            $rowNum = $r + 1;
            $attrs  = ' r="' . $rowNum . '"';

            if (isset($this->heights[$rowNum])) {
                $attrs .= ' ht="' . $this->heights[$rowNum] . '" customHeight="1"';
            }

            $cellsXml = '';

            foreach ($row['cells'] as $c => $cell) {
                [$value, $style] = is_array($cell) ? $cell : [$cell, $row['style']];

                if ($value === null || $value === '') {
                    // Still emit the cell so borders and fills draw.
                    $cellsXml .= '<c r="' . self::columnName($c + 1) . $rowNum . '" s="' . $style . '"/>';
                    continue;
                }

                $ref = self::columnName($c + 1) . $rowNum;

                if (is_int($value) || is_float($value)) {
                    $cellsXml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
                    continue;
                }

                $cellsXml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
                    . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    . '</t></is></c>';
            }

            $body .= '<row' . $attrs . '>' . $cellsXml . '</row>';
        }

        $merges = '';

        if ($this->merges) {
            $merges = '<mergeCells count="' . count($this->merges) . '">';
            foreach ($this->merges as $range) {
                $merges .= '<mergeCell ref="' . $range . '"/>';
            }
            $merges .= '</mergeCells>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0" showGridLines="0">' . $pane . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . $cols
            . '<sheetData>' . $body . '</sheetData>'
            . $merges
            . '</worksheet>';
    }
}
