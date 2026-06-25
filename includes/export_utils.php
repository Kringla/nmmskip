<?php
// includes/export_utils.php
// Felles hjelpere for CSV/PDF-eksport. PDF brukes ikke når vi kjører null-avhengighet,
// men beholder et minimalt grensesnitt i tilfelle TCPDF legges til senere.

// Uses global h() from includes/functions.php

function sw_send_csv(string $filename, array $headers, array $rows): void
{
    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);
    }
    echo "\xEF\xBB\xBF"; // BOM for Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ',');
    foreach ($rows as $r) {
        $flat = [];
        foreach ($r as $v) $flat[] = is_scalar($v) ? (string)$v : '';
        fputcsv($out, $flat, ',');
    }
    fclose($out);
    exit;
}

function sw_load_tcpdf(): bool
{
    if (class_exists('TCPDF')) return true;
    $candidates = [
        __DIR__ . '/../tcpdf/tcpdf.php',
        __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../vendor/autoload.php',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) { require_once $file; if (class_exists('TCPDF')) return true; }
    }
    return class_exists('TCPDF');
}

function sw_send_pdf(string $title, array $headers, array $rows, string $orientation = 'P'): void
{
    $orientation = strtoupper($orientation) === 'L' ? 'L' : 'P';
    if (!sw_load_tcpdf()) {
        if (!headers_sent()) header('Content-Type: text/plain; charset=UTF-8');
        echo "TCPDF ikke tilgjengelig (bruk utskriftsvisning).";
        exit;
    }
    $pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('SkipsWeb');
    $pdf->SetTitle($title);
    $pdf->SetMargins(12,14,12);
    $pdf->AddPage();
    $pdf->SetFont('helvetica','',10);
    $html  = '<h2 style="text-align:center;">' . h($title) . '</h2>';
    $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
    $html .= '<thead><tr style="background-color:#f0f3f7;">';
    foreach ($headers as $hcell) $html .= '<th><b>' . h($hcell) . '</b></th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $html .= '<tr>';
        foreach ($r as $v) $html .= '<td>' . h((string)$v) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($title . '.pdf', 'I');
    exit;
}
