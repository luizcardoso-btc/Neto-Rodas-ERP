<?php
declare(strict_types=1);

namespace App;

/**
 * Gerador de PDF minimo, sem dependencias (A4, fontes Helvetica embutidas no leitor).
 * Coordenadas em pontos, origem no canto SUPERIOR esquerdo.
 */
final class Pdf
{
    public const W = 595.28;
    public const H = 841.89;

    /** Larguras Helvetica (AFM) de 32 a 126, em 1/1000 de em. */
    private const REG = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    private const BOLD = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];

    /** @var array<string,array{name:string,w:int,h:int,data:string}> imagens JPEG por caminho */
    private array $images = [];
    /** @var list<string> */
    private array $pages = [];
    private string $cur = '';

    public function addPage(): void
    {
        if ($this->cur !== '') {
            $this->pages[] = $this->cur;
        }
        $this->cur = '';
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private static function color(array $rgb): string
    {
        return sprintf('%.3F %.3F %.3F', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    }

    private static function enc(string $s): string
    {
        $c = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        $c = $c === false ? preg_replace('/[^\x20-\x7e]/', '?', $s) : $c;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $c);
    }

    public function width(string $s, float $size, bool $bold = false): float
    {
        $t = $bold ? self::BOLD : self::REG;
        $w = 0;
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $o = mb_ord($ch, 'UTF-8');
            $w += ($o >= 32 && $o <= 126) ? $t[$o - 32] : ($o >= 192 && $o <= 222 ? 722 : 556);
        }
        return $w * $size / 1000;
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, array $rgb = [15, 23, 42], string $align = 'L'): void
    {
        $w = $this->width($s, $size, $bold);
        if ($align === 'R') {
            $x -= $w;
        } elseif ($align === 'C') {
            $x -= $w / 2;
        }
        $this->cur .= sprintf("BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, self::color($rgb), $x, self::H - $y, self::enc($s));
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    public function rect(float $x, float $y, float $w, float $h, array $rgb, bool $fill = true): void
    {
        $c = self::color($rgb);
        $this->cur .= $fill
            ? sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", $c, $x, self::H - $y - $h, $w, $h)
            : sprintf("%s RG 0.6 w %.2F %.2F %.2F %.2F re S\n", $c, $x, self::H - $y - $h, $w, $h);
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb = [203, 213, 225], float $lw = 0.6): void
    {
        $this->cur .= sprintf("%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n", self::color($rgb), $lw, $x1, self::H - $y1, $x2, self::H - $y2);
    }

    /** Desenha um JPEG (RGB/cinza) em x,y com largura w (altura proporcional). Retorna a altura usada. */
    public function image(string $path, float $x, float $y, float $w): float
    {
        if (!isset($this->images[$path])) {
            $info = @getimagesize($path);
            $data = @file_get_contents($path);
            if (!$info || $info[2] !== IMAGETYPE_JPEG || $data === false) {
                return 0.0;
            }
            $this->images[$path] = ['name' => 'Im' . (count($this->images) + 1), 'w' => (int) $info[0], 'h' => (int) $info[1], 'data' => $data, 'ch' => (int) ($info['channels'] ?? 3)];
        }
        $im = $this->images[$path];
        $h = $w * $im['h'] / $im['w'];
        $this->cur .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n", $w, $h, $x, self::H - $y - $h, $im['name']);
        return $h;
    }

    /** @return list<string> linhas quebradas por largura */
    public function wrap(string $s, float $size, float $maxW, bool $bold = false): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $s) ?: [''] as $para) {
            $cur = '';
            foreach (preg_split('/\s+/u', $para, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $try = $cur === '' ? $word : $cur . ' ' . $word;
                if ($this->width($try, $size, $bold) <= $maxW) {
                    $cur = $try;
                    continue;
                }
                if ($cur !== '') {
                    $lines[] = $cur;
                }
                $cur = $word;
                while ($this->width($cur, $size, $bold) > $maxW && mb_strlen($cur) > 1) { // palavra maior que a coluna
                    $k = mb_strlen($cur);
                    while ($k > 1 && $this->width(mb_substr($cur, 0, $k), $size, $bold) > $maxW) {
                        $k--;
                    }
                    $lines[] = mb_substr($cur, 0, $k);
                    $cur = mb_substr($cur, $k);
                }
            }
            $lines[] = $cur;
        }
        return $lines ?: [''];
    }

    public function output(): string
    {
        $pages = $this->pages;
        if ($this->cur !== '' || !$pages) {
            $pages[] = $this->cur;
        }
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $imgBase = 5 + 2 * count($pages);
        $xo = '';
        $k = 0;
        foreach ($this->images as $im) {
            $xo .= sprintf('/%s %d 0 R ', $im['name'], $imgBase + $k++);
        }
        $n = 5; // 3,4 = fontes; paginas a partir de 5 (par = pagina, impar = conteudo)
        foreach ($pages as $content) {
            $kids[] = $n . ' 0 R';
            $objs[$n] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << %s>> >> /Contents %d 0 R >>', self::W, self::H, $xo, $n + 1);
            $objs[$n + 1] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
            $n += 2;
        }
        $k = 0;
        foreach ($this->images as $im) {
            $objs[$imgBase + $k++] = sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $im['w'], $im['h'], $im['ch'] === 1 ? 'DeviceGray' : 'DeviceRGB', strlen($im['data']), $im['data']);
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objs);
        $out = "%PDF-1.4\n";
        $off = [];
        foreach ($objs as $i => $body) {
            $off[$i] = strlen($out);
            $out .= "$i 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($out);
        $out .= 'xref' . "\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($off as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }
        $out .= 'trailer << /Size ' . (count($objs) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n$xref\n%%EOF";
        return $out;
    }
}
