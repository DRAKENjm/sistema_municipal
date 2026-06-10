<?php
/**
 * SimpleExcel - Generador simple de archivos Excel (.xlsx)
 * Sin dependencias externas - Solo PHP nativo
 */

class SimpleExcel {
    private $data = [];
    private $headers = [];
    private $filename = 'export.xlsx';

    public function __construct($filename = 'export.xlsx') {
        $this->filename = $filename;
    }

    public function setHeaders($headers) {
        $this->headers = $headers;
    }

    public function addRow($row) {
        $this->data[] = $row;
    }

    public function download() {
        // Crear archivo Excel usando formato XML compatible
        $excelXML = $this->generateExcelXML();
        
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->filename . '"');
        header('Cache-Control: max-age=0');
        
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo $excelXML;
        exit;
    }

    private function generateExcelXML() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        
        // Estilos
        $xml .= '<Styles>' . "\n";
        
        // Estilo para encabezados
        $xml .= '<Style ss:ID="Header">' . "\n";
        $xml .= '<Font ss:Bold="1" ss:Color="#FFFFFF"/>' . "\n";
        $xml .= '<Interior ss:Color="#4472C4" ss:Pattern="Solid"/>' . "\n";
        $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
        $xml .= '<Borders>' . "\n";
        $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
        $xml .= '</Borders>' . "\n";
        $xml .= '</Style>' . "\n";
        
        // Estilo para datos
        $xml .= '<Style ss:ID="Data">' . "\n";
        $xml .= '<Alignment ss:Vertical="Center"/>' . "\n";
        $xml .= '<Borders>' . "\n";
        $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>' . "\n";
        $xml .= '</Borders>' . "\n";
        $xml .= '</Style>' . "\n";
        
        $xml .= '</Styles>' . "\n";
        
        // Hoja de trabajo
        $xml .= '<Worksheet ss:Name="Datos">' . "\n";
        $xml .= '<Table>' . "\n";
        
        // Auto-ajustar columnas
        foreach ($this->headers as $i => $header) {
            $width = max(strlen($header) * 8, 80);
            $xml .= '<Column ss:Index="' . ($i + 1) . '" ss:AutoFitWidth="0" ss:Width="' . $width . '"/>' . "\n";
        }
        
        // Encabezados
        if (!empty($this->headers)) {
            $xml .= '<Row ss:StyleID="Header">' . "\n";
            foreach ($this->headers as $header) {
                $xml .= '<Cell><Data ss:Type="String">' . $this->xmlEscape($header) . '</Data></Cell>' . "\n";
            }
            $xml .= '</Row>' . "\n";
        }
        
        // Datos
        foreach ($this->data as $row) {
            $xml .= '<Row ss:StyleID="Data">' . "\n";
            foreach ($row as $cell) {
                $type = is_numeric($cell) ? 'Number' : 'String';
                $value = $this->xmlEscape($cell);
                $xml .= '<Cell><Data ss:Type="' . $type . '">' . $value . '</Data></Cell>' . "\n";
            }
            $xml .= '</Row>' . "\n";
        }
        
        $xml .= '</Table>' . "\n";
        $xml .= '</Worksheet>' . "\n";
        $xml .= '</Workbook>';
        
        return $xml;
    }

    private function xmlEscape($string) {
        return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
