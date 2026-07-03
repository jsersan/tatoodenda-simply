<?php
// =============================================================================
//  ALBARAN  ·  Genera el albarán en PDF en el servidor con FPDF.
//
//  FPDF es un único fichero, sin Composer (perfecto para subir por FTP).
//  Descárgalo de https://www.fpdf.org (v1.86) y colócalo en:
//        backend-php/lib/fpdf.php
//
//  Si el fichero no está, las funciones devuelven null y el resto del backend
//  sigue funcionando (el email se manda sin adjunto o con el PDF que envíe el
//  frontend en base64).
// =============================================================================

/** ¿Está disponible FPDF? */
function fpdf_disponible()
{
    return is_file(__DIR__ . '/lib/fpdf.php');
}

/**
 * Genera el PDF del albarán y devuelve sus bytes (string), o null si no hay FPDF.
 * $pedido  = ['id','fecha','total','lineas'=>[['nombre','color','cant','precio'],...]]
 * $usuario = ['nombre','email','direccion','ciudad','cp'] (puede ser null)
 */
function albaran_pdf_bytes($pedido, $usuario)
{
    if (!fpdf_disponible()) return null;
    require_once __DIR__ . '/lib/fpdf.php';

    // Utilidad: FPDF core usa latin1; convertimos desde UTF-8.
    $L = function ($t) {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', (string) $t);
    };

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();

    // Cabecera
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(0, 10, $L('TatooDenda'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 6, $L('Albarán de pedido'), 0, 1, 'L');
    $pdf->Ln(2);

    // Datos del pedido y del cliente
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(95, 6, $L('Pedido nº: ' . ($pedido['id'] ?? '')), 0, 0, 'L');
    $pdf->Cell(0, 6, $L('Fecha: ' . ($pedido['fecha'] ?? '')), 0, 1, 'R');

    if ($usuario) {
        $pdf->Ln(1);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, $L('Cliente'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, $L($usuario['nombre'] ?? ''), 0, 1, 'L');
        $dir = trim(($usuario['direccion'] ?? '') . '  ' . ($usuario['cp'] ?? '') . ' ' . ($usuario['ciudad'] ?? ''));
        if ($dir !== '') $pdf->Cell(0, 5, $L($dir), 0, 1, 'L');
        if (!empty($usuario['email'])) $pdf->Cell(0, 5, $L($usuario['email']), 0, 1, 'L');
    }
    $pdf->Ln(3);

    // Cabecera de la tabla
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(85, 8, $L('Producto'), 1, 0, 'L', true);
    $pdf->Cell(35, 8, $L('Color'),    1, 0, 'L', true);
    $pdf->Cell(15, 8, $L('Cant.'),    1, 0, 'C', true);
    $pdf->Cell(25, 8, $L('Precio'),   1, 0, 'R', true);
    $pdf->Cell(0,  8, $L('Subtotal'), 1, 1, 'R', true);

    // Filas
    $pdf->SetFont('Arial', '', 10);
    $total = 0;
    $lineas = isset($pedido['lineas']) && is_array($pedido['lineas']) ? $pedido['lineas'] : array();
    foreach ($lineas as $l) {
        $cant   = (int) ($l['cant'] ?? ($l['cantidad'] ?? 0));
        $precio = (float) ($l['precio'] ?? 0);
        $sub    = $cant * $precio;
        $total += $sub;

        $pdf->Cell(85, 7, $L($l['nombre'] ?? ''),             1, 0, 'L');
        $pdf->Cell(35, 7, $L($l['color'] ?? ''),              1, 0, 'L');
        $pdf->Cell(15, 7, (string) $cant,                     1, 0, 'C');
        $pdf->Cell(25, 7, number_format($precio, 2) . ' EUR', 1, 0, 'R');
        $pdf->Cell(0,  7, number_format($sub, 2) . ' EUR',    1, 1, 'R');
    }

    // Total (usa el del pedido si viene; si no, el calculado)
    $totalFinal = isset($pedido['total']) ? (float) $pedido['total'] : $total;
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(160, 9, $L('TOTAL'), 1, 0, 'R');
    $pdf->Cell(0,   9, number_format($totalFinal, 2) . ' EUR', 1, 1, 'R');

    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 5, $L('Gracias por tu compra en TatooDenda.'), 0, 1, 'C');

    return $pdf->Output('S');   // 'S' = devuelve el PDF como string
}
