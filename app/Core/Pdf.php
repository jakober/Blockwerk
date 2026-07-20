<?php
declare(strict_types=1);

namespace Core;

/**
 * Minimaler, abhängigkeitsfreier PDF-Generator (nur so viel, wie für eine
 * Rechnung nötig ist): Text (Helvetica/Helvetica-Bold, links/rechtsbündig),
 * Linien, gefüllte Rechtecke und eingebettete JPEG-Bilder. Koordinaten mit
 * Ursprung OBEN LINKS (in Punkten, 1 pt = 1/72"). Seitenformat A4.
 */
class Pdf
{
    public const PAGE_W = 595.28; // A4 Breite in pt
    public const PAGE_H = 841.89; // A4 Höhe in pt

    /** @var string[] Inhaltsströme je Seite */
    private array $pages = [''];
    private int $cur = 0;
    /** @var array<int, array{data:string,w:int,h:int}> */
    private array $images = [];

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->cur = count($this->pages) - 1;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function setPage(int $i): void
    {
        if (isset($this->pages[$i])) {
            $this->cur = $i;
        }
    }

    /** Y von oben nach PDF-Koordinate (unten) umrechnen. */
    private function y(float $y): float
    {
        return self::PAGE_H - $y;
    }

    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    /** Text linksbündig an (x,y) – y = Grundlinie von oben. */
    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, array $rgb = [0, 0, 0]): void
    {
        $font = $bold ? '/F2' : '/F1';
        $this->pages[$this->cur] .= sprintf(
            "%s %s %s rg BT %s %s Tf 1 0 0 1 %s %s Tm (%s) Tj ET\n",
            self::num($rgb[0] / 255), self::num($rgb[1] / 255), self::num($rgb[2] / 255),
            $font, self::num($size), self::num($x), self::num($this->y($y)), self::esc($s)
        );
    }

    /** Text rechtsbündig: das Ende der Zeichenkette liegt bei x. */
    public function textRight(float $x, float $y, string $s, float $size = 10, bool $bold = false, array $rgb = [0, 0, 0]): void
    {
        $this->text($x - $this->width($s, $size, $bold), $y, $s, $size, $bold, $rgb);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $w = 0.5, array $rgb = [0, 0, 0]): void
    {
        $this->pages[$this->cur] .= sprintf(
            "%s %s %s RG %s w %s %s m %s %s l S\n",
            self::num($rgb[0] / 255), self::num($rgb[1] / 255), self::num($rgb[2] / 255),
            self::num($w), self::num($x1), self::num($this->y($y1)), self::num($x2), self::num($this->y($y2))
        );
    }

    /** Gefülltes Rechteck (x,y = obere linke Ecke). */
    public function rect(float $x, float $y, float $w, float $h, array $rgb = [0, 0, 0]): void
    {
        $this->pages[$this->cur] .= sprintf(
            "%s %s %s rg %s %s %s %s re f\n",
            self::num($rgb[0] / 255), self::num($rgb[1] / 255), self::num($rgb[2] / 255),
            self::num($x), self::num($this->y($y + $h)), self::num($w), self::num($h)
        );
    }

    /** JPEG-Bild einbetten (x,y = obere linke Ecke, w/h Zielgröße in pt). */
    public function image(string $jpeg, float $x, float $y, float $w, float $h): void
    {
        $info = @getimagesizefromstring($jpeg);
        if ($info === false || ($info[2] ?? 0) !== IMAGETYPE_JPEG) {
            return;
        }
        $this->images[] = ['data' => $jpeg, 'w' => (int) $info[0], 'h' => (int) $info[1]];
        $n = count($this->images);
        $this->pages[$this->cur] .= sprintf(
            "q %s 0 0 %s %s %s cm /Im%d Do Q\n",
            self::num($w), self::num($h), self::num($x), self::num($this->y($y + $h)), $n
        );
    }

    private static function esc(string $s): string
    {
        $s = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        if ($s === false) {
            $s = '';
        }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $s);
    }

    /** Breite einer (UTF-8-)Zeichenkette in pt für Helvetica(-Bold). */
    public function width(string $s, float $size, bool $bold): float
    {
        $cp = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        if ($cp === false) {
            $cp = '';
        }
        $table = self::widths($bold);
        $sum = 0;
        $len = strlen($cp);
        for ($i = 0; $i < $len; $i++) {
            $sum += $table[ord($cp[$i])] ?? 556;
        }
        return $sum / 1000 * $size;
    }

    /** @return array<int,int> Zeichenbreiten (per 1000) für ASCII 32–126. */
    private static function widths(bool $bold): array
    {
        $regular = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
        $boldW = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];
        $src = $bold ? $boldW : $regular;
        $map = [];
        foreach ($src as $i => $w) {
            $map[32 + $i] = $w;
        }
        return $map;
    }

    public function output(): string
    {
        $objects = [];
        $add = static function (string $body) use (&$objects): int {
            $objects[] = $body;
            return count($objects);
        };

        // 1: Catalog, 2: Pages – Nummern reservieren wir fest.
        $catalog = 1;
        $pagesObj = 2;
        $fontF1 = 3;
        $fontF2 = 4;
        $objects[0] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[1] = ''; // Pages – später gefüllt
        $objects[2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        // Bild-XObjects.
        $imgRefs = '';
        $imgObjNums = [];
        foreach ($this->images as $idx => $img) {
            $objects[] = "<< /Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']}"
                . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length "
                . strlen($img['data']) . " >>\nstream\n" . $img['data'] . "\nendstream";
            $imgObjNums[$idx] = count($objects);
        }
        foreach ($imgObjNums as $idx => $num) {
            $imgRefs .= '/Im' . ($idx + 1) . ' ' . $num . ' 0 R ';
        }

        // Seiten + Inhaltsströme.
        $pageObjNums = [];
        $contentObjNums = [];
        foreach ($this->pages as $content) {
            $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $contentObjNums[] = count($objects);
            $objects[] = ''; // Platzhalter für Seiten-Objekt
            $pageObjNums[] = count($objects);
        }
        $resources = '<< /Font << /F1 3 0 R /F2 4 0 R >>'
            . ($imgRefs !== '' ? ' /XObject << ' . trim($imgRefs) . ' >>' : '') . ' >>';
        foreach ($this->pages as $i => $content) {
            $objects[$pageObjNums[$i] - 1] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources %s /Contents %d 0 R >>',
                self::num(self::PAGE_W), self::num(self::PAGE_H), $resources, $contentObjNums[$i]
            );
        }
        $kids = implode(' ', array_map(static fn ($n) => $n . ' 0 R', $pageObjNums));
        $objects[1] = '<< /Type /Pages /Count ' . count($this->pages) . ' /Kids [' . $kids . '] >>';

        // Datei zusammensetzen mit xref.
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[$i] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 " . $count . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= "trailer\n<< /Size " . $count . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";
        return $pdf;
    }
}
