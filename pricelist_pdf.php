<?php
/* Copyright (C) 2024-2026 Equipment Manager
 * Price List PDF Generator - v5.3.0
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/equipmentmanager/class/pricelistitem.class.php');

if (!$user->rights->equipmentmanager->equipment->read) accessforbidden();

$langs->loadLangs(array("equipmentmanager@equipmentmanager", "companies", "main"));

$list_type = GETPOST('list_type', 'aZ09') ?: 'rate';
$fk_soc    = (int) GETPOST('fk_soc', 'int');
$discount  = max(0, min(99, (float) GETPOST('discount', 'alpha')));

// ─── Load items ─────────────────────────────────────────────────────────────

$proto = new PriceListItem($db);
$items = $proto->fetchAllByType($list_type);

// ─── Load customer if specified ─────────────────────────────────────────────

$customer = null;
if ($fk_soc > 0) {
    $customer = new Societe($db);
    if ($customer->fetch($fk_soc) <= 0) $customer = null;
}

// ─── List title ─────────────────────────────────────────────────────────────

$list_titles = array(
    'rate'        => $langs->trans('PriceListRate'),
    'maintenance' => $langs->trans('PriceListMaintenance'),
);
$list_title = $list_titles[$list_type] ?? $langs->trans('PriceList');

// ─── PDF setup ──────────────────────────────────────────────────────────────

$formatarray = pdf_getFormat();
$page_w      = $formatarray['width'];
$page_h      = $formatarray['height'];
$format      = array($page_w, $page_h);

$mg_left   = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT',   10);
$mg_right  = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT',  10);
$mg_top    = getDolGlobalInt('MAIN_PDF_MARGIN_TOP',    10);
$mg_bottom = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

$outputlangs = $langs;
$outputlangs->loadLangs(array("main", "dict", "companies", "equipmentmanager@equipmentmanager"));

$pdf = pdf_getInstance($format);
if (class_exists('TCPDF')) {
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
}
$pdf->SetFont(pdf_getPDFFont($outputlangs));
$pdf->SetCreator('Dolibarr Equipment Manager');
$pdf->SetTitle($list_title);
$pdf->SetAutoPageBreak(true, $mg_bottom + 10);

$pdf->AddPage();

$fsz = pdf_getPDFFontSize($outputlangs);

// ─── Helper: convert string ──────────────────────────────────────────────────

function pdfStr($str, $outputlangs)
{
    if (empty($str)) return '';
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    return $outputlangs->convToOutputCharset($str);
}

function fmtPrice($price, $discount = 0)
{
    if ($price === null || $price === '') return '–';
    $p = (float)$price;
    if ($discount > 0) $p = $p * (1 - $discount / 100);
    return number_format($p, 2, ',', '.') . ' €';
}

// ─── Header ─────────────────────────────────────────────────────────────────

$posy = $mg_top;
$content_w = $page_w - $mg_left - $mg_right;

// Logo left
$logo_h = 0;
$logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
if (!empty($mysoc->logo) && is_readable($logo)) {
    $logo_h = pdf_getHeightForLogo($logo);
    $pdf->Image($logo, $mg_left, $posy, 0, $logo_h * 1.3);
    $logo_h = $logo_h * 1.3;
} else {
    $pdf->SetFont('', 'B', $fsz + 2);
    $pdf->SetXY($mg_left, $posy);
    $pdf->Cell(80, 6, pdfStr($mysoc->name, $outputlangs), 0, 0, 'L');
    $logo_h = 6;
}

// Company address right
$addr_x = $page_w - $mg_right - 70;
$pdf->SetFont('', 'B', $fsz + 1);
$pdf->SetTextColor(0, 0, 60);
$pdf->SetXY($addr_x, $posy);
$pdf->Cell(70, 5, pdfStr($mysoc->name, $outputlangs), 0, 1, 'R');

$pdf->SetFont('', '', $fsz - 1);
$pdf->SetTextColor(80, 80, 80);
if ($mysoc->address) {
    $pdf->SetX($addr_x);
    $pdf->Cell(70, 4, pdfStr($mysoc->address, $outputlangs), 0, 1, 'R');
}
if ($mysoc->zip || $mysoc->town) {
    $pdf->SetX($addr_x);
    $pdf->Cell(70, 4, trim($mysoc->zip.' '.$mysoc->town), 0, 1, 'R');
}
if ($mysoc->phone) {
    $pdf->SetX($addr_x);
    $pdf->Cell(70, 4, 'Tel: '.pdfStr($mysoc->phone, $outputlangs), 0, 1, 'R');
}
if ($mysoc->email) {
    $pdf->SetX($addr_x);
    $pdf->Cell(70, 4, pdfStr($mysoc->email, $outputlangs), 0, 1, 'R');
}

$posy = max($posy + $logo_h, $pdf->GetY()) + 6;

// ─── Title bar ──────────────────────────────────────────────────────────────

$pdf->SetFont('', 'B', $fsz + 4);
$pdf->SetTextColor(0, 0, 60);
$pdf->SetFillColor(230, 230, 230);
$pdf->SetXY($mg_left, $posy);
$pdf->Cell($content_w, 11, pdfStr($list_title, $outputlangs), 1, 1, 'C', true);
$posy += 13;

// ─── Date / Stand ───────────────────────────────────────────────────────────

$pdf->SetFont('', '', $fsz);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetXY($mg_left, $posy);
$pdf->Cell($content_w, 5, 'Stand: '.date('d.m.Y'), 0, 1, 'R');
$posy += 7;

// ─── Customer box (if specified) ────────────────────────────────────────────

if ($customer) {
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetFillColor(248, 248, 248);

    $box_x = $page_w - $mg_right - 90;
    $box_y = $posy;

    // Build customer address lines
    $lines = array();
    $lines[] = $customer->name;
    if ($customer->address) {
        foreach (explode("\n", $customer->address) as $l) {
            $lines[] = trim($l);
        }
    }
    if ($customer->zip || $customer->town) $lines[] = trim($customer->zip.' '.$customer->town);

    $box_h = count($lines) * 4.5 + 8;
    if ($discount > 0) $box_h += 7;

    $pdf->Rect($box_x, $box_y, 90, $box_h, 'DF');

    $pdf->SetFont('', 'B', $fsz - 1);
    $pdf->SetTextColor(0, 0, 60);
    $pdf->SetXY($box_x + 3, $box_y + 3);
    $pdf->Cell(84, 4, 'Für:', 0, 1, 'L');

    $pdf->SetFont('', '', $fsz - 1);
    $pdf->SetTextColor(40, 40, 40);
    foreach ($lines as $line) {
        $pdf->SetX($box_x + 3);
        $pdf->Cell(84, 4.5, pdfStr($line, $outputlangs), 0, 1, 'L');
    }

    if ($discount > 0) {
        $pdf->SetFont('', 'B', $fsz - 1);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->SetX($box_x + 3);
        $pdf->Cell(84, 6, 'Sonderkonditionen: '.number_format($discount, 1, ',', '.').' % Rabatt', 0, 1, 'L');
    }

    $posy = max($posy, $pdf->GetY()) + 4;
}

// ─── Table header ────────────────────────────────────────────────────────────

$col_pos  = 12;
$col_lbl  = $content_w - $col_pos - 80 - 28 - 32; // dynamic label width
$col_desc = 0; // merged into label
$col_unit = 28;
$col_net  = 32;
$col_vat  = 20;

$pdf->SetFont('', 'B', $fsz - 1);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFillColor(60, 90, 130);
$pdf->SetXY($mg_left, $posy);

$pdf->Cell($col_pos,  7, 'Pos.',      1, 0, 'C', true);
$pdf->Cell($col_lbl,  7, 'Leistung',  1, 0, 'L', true);
$pdf->Cell($col_unit, 7, 'Einheit',   1, 0, 'C', true);
$pdf->Cell($col_net,  7, 'Preis netto', 1, 0, 'R', true);
$pdf->Cell($col_vat,  7, 'MwSt.',     1, 1, 'C', true);
$posy += 8;

// ─── Table rows ──────────────────────────────────────────────────────────────

$pdf->SetTextColor(30, 30, 30);
$row_fill = false;

foreach ($items as $idx => $item) {
    $price_str = fmtPrice($item->product_price, $discount);
    $vat_str   = $item->product_tva_tx !== null ? number_format((float)$item->product_tva_tx, 0).' %' : '–';
    $has_desc  = !empty($item->description);

    // Calculate row height (description wraps)
    $row_h = 6;
    $desc_lines = 0;
    if ($has_desc) {
        // Estimate lines: ~85 chars per line at current col width
        $desc_text = strip_tags($item->description);
        $desc_lines = max(1, (int)ceil(strlen($desc_text) / 80));
        $row_h += $desc_lines * 4;
    }

    // Page break check
    if ($pdf->GetY() + $row_h > $page_h - $mg_bottom - 15) {
        $pdf->AddPage();
        $posy = $mg_top;
        // Re-draw table header
        $pdf->SetFont('', 'B', $fsz - 1);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor(60, 90, 130);
        $pdf->SetXY($mg_left, $posy);
        $pdf->Cell($col_pos,  7, 'Pos.',        1, 0, 'C', true);
        $pdf->Cell($col_lbl,  7, 'Leistung',    1, 0, 'L', true);
        $pdf->Cell($col_unit, 7, 'Einheit',     1, 0, 'C', true);
        $pdf->Cell($col_net,  7, 'Preis netto', 1, 0, 'R', true);
        $pdf->Cell($col_vat,  7, 'MwSt.',       1, 1, 'C', true);
        $posy += 8;
        $pdf->SetTextColor(30, 30, 30);
    }

    $bg = $row_fill ? array(245, 248, 252) : array(255, 255, 255);
    $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);
    $row_fill = !$row_fill;

    $cur_y = $pdf->GetY();
    $pdf->SetFont('', '', $fsz - 1);

    // Position number
    $pdf->SetXY($mg_left, $cur_y);
    $pdf->Cell($col_pos, $row_h, ($idx + 1).'.', 1, 0, 'C', true);

    // Label + description (MultiCell for wrapping)
    $label_text = pdfStr($item->label, $outputlangs);
    if ($has_desc) {
        // Label bold first line, then description in smaller/normal
        $pdf->SetFont('', 'B', $fsz - 1);
        $pdf->SetXY($mg_left + $col_pos, $cur_y);
        $pdf->MultiCell($col_lbl, 6, $label_text, 'LRT', 'L', true);
        $pdf->SetFont('', '', $fsz - 2);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY($mg_left + $col_pos, $cur_y + 6);
        $desc_text = pdfStr(strip_tags($item->description), $outputlangs);
        $pdf->MultiCell($col_lbl, 4, $desc_text, 'LRB', 'L', true);
        $pdf->SetTextColor(30, 30, 30);
    } else {
        $pdf->SetFont('', 'B', $fsz - 1);
        $pdf->SetXY($mg_left + $col_pos, $cur_y);
        $pdf->Cell($col_lbl, $row_h, $label_text, 1, 0, 'L', true);
    }

    // Unit, price, VAT – aligned to right side
    $pdf->SetFont('', '', $fsz - 1);
    $pdf->SetXY($mg_left + $col_pos + $col_lbl, $cur_y);
    $pdf->Cell($col_unit, $row_h, pdfStr($item->unit, $outputlangs), 1, 0, 'C', true);
    $pdf->Cell($col_net,  $row_h, $price_str, 1, 0, 'R', true);
    $pdf->Cell($col_vat,  $row_h, $vat_str,   1, 1, 'C', true);
}

// ─── Footer note ─────────────────────────────────────────────────────────────

$posy = $pdf->GetY() + 8;
$pdf->SetFont('', 'I', $fsz - 2);
$pdf->SetTextColor(120, 120, 120);
$pdf->SetXY($mg_left, $posy);

$footer_text = 'Alle Preise netto zzgl. gesetzlicher MwSt.';
if ($discount > 0) {
    $footer_text .= '   |   Kundenpreise inkl. '.number_format($discount, 1, ',', '.').' % Rabatt';
}
$pdf->Cell($content_w, 5, $footer_text, 0, 1, 'C');

$pdf->SetXY($mg_left, $posy + 5);
$pdf->Cell($content_w, 4, 'Stand: '.date('d.m.Y'), 0, 1, 'C');

// ─── Page numbers ─────────────────────────────────────────────────────────────

$nb_pages = $pdf->getNumPages();
for ($p = 1; $p <= $nb_pages; $p++) {
    $pdf->setPage($p);
    $pdf->SetFont('', '', $fsz - 2);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetXY($mg_left, $page_h - $mg_bottom - 5);
    $pdf->Cell($content_w, 5, 'Seite '.$p.' / '.$nb_pages, 0, 0, 'C');
}

// ─── Output ──────────────────────────────────────────────────────────────────

$filename = 'Preisliste_'.($list_type === 'maintenance' ? 'Wartung' : 'Verrechnungssatz');
if ($customer) $filename .= '_'.dol_sanitizeFileName($customer->name);
$filename .= '_'.date('Y-m-d').'.pdf';

if (ob_get_length()) ob_end_clean();
$pdf->Output($filename, 'I');
