<?php
/**
 * SigdocPDF — Clase base FPDF con diseño corporativo para SIGDOC-ML
 * Extiende FPDF v1.86
 */
require_once __DIR__ . '/fpdf.php';

class SigdocPDF extends FPDF
{
    // ── Paleta corporativa (RGB) ──────────────────────────
    const C_AZUL    = [26,  58,  92];   // var(--primary)
    const C_NARANJA = [232, 160,  32];  // var(--secondary)
    const C_GRIS_BG = [244, 246, 251];  // fondo tabla
    const C_GRIS_TX = [108, 117, 125];  // texto muted
    const C_VERDE   = [25,  135,  84];  // score alto
    const C_AMBAR   = [240, 165,   0];  // score medio
    const C_ROJO    = [220,  53,  69];  // score bajo
    const C_BLANCO  = [255, 255, 255];

    public string $subtitulo     = '';
    public string $nombreArchivo = 'sigdoc_reporte';

    // ── Cabecera ──────────────────────────────────────────
    public function Header(): void
    {
        // Banda azul superior
        $this->SetFillColor(...self::C_AZUL);
        $this->Rect(0, 0, $this->GetPageWidth(), 18, 'F');

        // Logo / nombre sistema
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(...self::C_BLANCO);
        $this->SetXY(10, 4);
        $this->Cell(80, 6, 'SIGDOC-ML', 0, 0, 'L');

        // Subtítulo en naranja
        if ($this->subtitulo) {
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor(...self::C_NARANJA);
            $this->SetXY(10, 11);
            $this->Cell(120, 5, $this->subtitulo, 0, 0, 'L');
        }

        // Número de página
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...self::C_BLANCO);
        $this->SetXY(-45, 6);
        $this->Cell(35, 5, 'Pag. ' . $this->PageNo(), 0, 0, 'R');

        $this->SetY(22);
    }

    // ── Pie de página ─────────────────────────────────────
    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetDrawColor(...self::C_GRIS_TX);
        $this->SetLineWidth(0.2);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());

        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(...self::C_GRIS_TX);
        $this->SetX(10);
        $this->Cell(0, 5,
            'Sistema de Gestion Documental y ML - Municipalidad   |   Generado: ' .
            date('d/m/Y H:i'),
            0, 0, 'L');
    }

    // ── Sección con título ────────────────────────────────
    public function seccionTitulo(string $titulo): void
    {
        $this->Ln(3);
        $this->SetFillColor(...self::C_AZUL);
        $this->SetTextColor(...self::C_BLANCO);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetX(10);
        $this->Cell($this->GetPageWidth() - 20, 7, '  ' . mb_strtoupper($titulo), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }

    // ── Fila de tabla ─────────────────────────────────────
    /**
     * @param array $datos   Textos de cada celda
     * @param array $anchos  Ancho de cada celda en mm
     * @param bool  $cabecera Si true, fondo azul + texto blanco bold
     * @param array $fillRGB Color de fondo opcional [r,g,b] o [] para blanco
     */
    public function filaTabla(
        array $datos,
        array $anchos,
        bool $cabecera = false,
        array $fillRGB = []
    ): void {
        if ($cabecera) {
            $this->SetFillColor(...self::C_AZUL);
            $this->SetTextColor(...self::C_BLANCO);
            $this->SetFont('Helvetica', 'B', 8);
            $fill = true;
        } elseif (!empty($fillRGB)) {
            $this->SetFillColor(...$fillRGB);
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Helvetica', '', 8);
            $fill = true;
        } else {
            $this->SetFillColor(...self::C_BLANCO);
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Helvetica', '', 8);
            $fill = true;
        }

        $this->SetDrawColor(220, 220, 220);
        $this->SetLineWidth(0.1);
        $this->SetX(10);

        foreach ($datos as $i => $dato) {
            $ancho = $anchos[$i] ?? 30;
            $this->Cell($ancho, 6, $dato, 'B', 0, 'L', $fill);
        }
        $this->Ln();

        // Reset
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(...self::C_BLANCO);
    }

    // ── Barra de score coloreada ──────────────────────────
    public function barraScore(
        float $pct,
        float $x,
        float $y,
        float $w = 55,
        float $h = 3.5
    ): void {
        // Fondo gris
        $this->SetFillColor(220, 220, 220);
        $this->Rect($x, $y, $w, $h, 'F');

        // Relleno coloreado
        if ($pct >= 70) {
            $this->SetFillColor(...self::C_VERDE);
        } elseif ($pct >= 50) {
            $this->SetFillColor(...self::C_AMBAR);
        } else {
            $this->SetFillColor(...self::C_ROJO);
        }
        $fill_w = ($pct / 100) * $w;
        if ($fill_w > 0) {
            $this->Rect($x, $y, $fill_w, $h, 'F');
        }

        // Porcentaje al lado
        $this->SetFont('Helvetica', 'B', 7);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($x + $w + 1, $y - 0.5);
        $this->Cell(12, $h + 1, number_format($pct, 1) . '%', 0, 0, 'L');
    }

    // ── Módulo de análisis con barra ──────────────────────
    public function moduloScore(
        string $etiqueta,
        float  $pct,
        string $detalle = ''
    ): void {
        $x = $this->GetX();
        $y = $this->GetY();

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...self::C_GRIS_TX);
        $this->SetX(12);
        $this->Cell(55, 5, $etiqueta, 0, 0, 'L');

        $this->barraScore($pct, 68, $y + 0.8, 80, 3.5);

        if ($detalle) {
            $this->SetFont('Helvetica', 'I', 7);
            $this->SetTextColor(...self::C_GRIS_TX);
            $this->SetXY(162, $y);
            $this->Cell(40, 5, $detalle, 0, 0, 'L');
        }

        $this->SetTextColor(0, 0, 0);
        $this->Ln(5);
    }

    // ── Badge de categoría ────────────────────────────────
    public function badgeCategoria(string $categoria, float $x, float $y): void
    {
        $colores = [
            'EXCELENTE'       => self::C_VERDE,
            'APROBADO'        => self::C_AZUL,
            'A CONSIDERAR'    => self::C_AMBAR,
            'NO RECOMENDADO'  => self::C_ROJO,
        ];
        $rgb = $colores[$categoria] ?? self::C_GRIS_TX;

        $this->SetFillColor(...$rgb);
        $this->SetTextColor(...self::C_BLANCO);
        $this->SetFont('Helvetica', 'B', 6.5);
        $this->SetXY($x, $y);
        $this->Cell(28, 4, $categoria, 0, 0, 'C', true);

        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(...self::C_BLANCO);
    }

    // ── Caja de texto resaltada ───────────────────────────
    public function cajaTexto(string $texto, array $bgRGB, int $maxChars = 180): void
    {
        $this->Ln(2);
        $this->SetFillColor(...$bgRGB);
        $this->SetX(10);
        $this->SetFont('Helvetica', '', 8.5);
        $this->MultiCell($this->GetPageWidth() - 20, 5.5, $texto, 0, 'L', true);
        $this->SetFillColor(...self::C_BLANCO);
        $this->Ln(1);
    }

    // ── Sello "REVISADO" ──────────────────────────────────
    public function selloRevisado(): void
    {
        $cx = $this->GetPageWidth() - 45;
        $cy = 30;
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(...self::C_VERDE);
        $this->SetDrawColor(...self::C_VERDE);
        $this->SetLineWidth(0.8);
        $this->Rect($cx - 2, $cy - 2, 34, 10, 'D');
        $this->SetXY($cx - 2, $cy + 0.5);
        $this->Cell(34, 6, 'REVISADO RRHH', 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
    }

    // ── Helper: texto seguro para FPDF (Latin1) ──────────
    public static function txt(string $s): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s) ?: $s;
    }
}
