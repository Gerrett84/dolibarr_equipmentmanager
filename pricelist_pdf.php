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

// ─── Load items ──────────────────────────────────────────────────────────────

$proto = new PriceListItem($db);
$items = $proto->fetchAllByType($list_type);

// ─── Load customer ────────────────────────────────────────────────────────────

$customer = null;
if ($fk_soc > 0) {
    $customer = new Societe($db);
    if ($customer->fetch($fk_soc) <= 0) $customer = null;
}

// ─── Titles ───────────────────────────────────────────────────────────────────

$list_titles = array(
    'rate'        => $langs->trans('PriceListRate'),
    'maintenance' => $langs->trans('PriceListMaintenance'),
);
$list_title = $list_titles[$list_type] ?? $langs->trans('PriceList');

// ─── PDF setup ────────────────────────────────────────────────────────────────

$formatarray = pdf_getFormat();
$page_w      = $formatarray['width'];
$page_h      = $formatarray['height'];

$mg_left   = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT',   10);
$mg_right  = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT',  10);
$mg_top    = getDolGlobalInt('MAIN_PDF_MARGIN_TOP',    10);
$mg_bottom = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

$content_w = $page_w - $mg_left - $mg_right;

$outputlangs = $langs;
$outputlangs->loadLangs(array("main", "dict", "companies", "equipmentmanager@equipmentmanager"));

$pdf = pdf_getInstance(array($page_w, $page_h));
if (class_exists('TCPDF')) {
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
}
$pdf->SetFont(pdf_getPDFFont($outputlangs));
$pdf->SetCreator('Dolibarr Equipment Manager');
$pdf->SetTitle($list_title);
$pdf->SetAutoPageBreak(false); // We handle page breaks manually
$pdf->AddPage();

$fsz      = pdf_getPDFFontSize($outputlangs);
$font     = pdf_getPDFFont($outputlangs);
$line_h   = 5.5;  // normal row line height
$desc_h   = 4.0;  // description line height
$min_row  = 7.0;  // minimum row height

// ─── Helpers ──────────────────────────────────────────────────────────────────

function ps($str, $ol) {
    if (empty($str)) return '';
    return $ol->convToOutputCharset(html_entity_decode($str, ENT_QUOTES, 'UTF-8'));
}

function fmtPrc($price, $disc = 0) {
    if ($price === null || $price === '') return '–';
    $p = (float)$price * (1 - $disc / 100);
    if ($p <= 0) return '–';
    return number_format($p, 2, ',', '.') . ' €';
}

// ─── Header (called per page) ─────────────────────────────────────────────────

function drawHeader(&$pdf, $fsz, $font, $outputlangs, $mysoc, $mg_left, $mg_right, $mg_top, $content_w, $list_title, $customer, $discount, $conf)
{
    $posy = $mg_top;

    // Logo left
    $logo_h = 0;
    $logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
    if (!empty($mysoc->logo) && is_readable($logo)) {
        $lh = pdf_getHeightForLogo($logo);
        $pdf->Image($logo, $mg_left, $posy, 0, $lh * 1.2);
        $logo_h = $lh * 1.2;
    } else {
        $pdf->SetFont($font, 'B', $fsz + 1);
        $pdf->SetXY($mg_left, $posy);
        $pdf->Cell(70, 6, ps($mysoc->name, $outputlangs), 0, 0, 'L');
        $logo_h = 7;
    }

    // Company address right
    $addr_x = $mg_left + $content_w - 70;
    $pdf->SetFont($font, 'B', $fsz);
    $pdf->SetTextColor(0, 0, 60);
    $pdf->SetXY($addr_x, $posy);
    $pdf->Cell(70, 5, ps($mysoc->name, $outputlangs), 0, 1, 'R');
    $pdf->SetFont($font, '', $fsz - 1);
    $pdf->SetTextColor(80, 80, 80);
    foreach (array($mysoc->address, ($mysoc->zip ? trim($mysoc->zip.' '.$mysoc->town) : ''), ($mysoc->phone ? 'Tel: '.$mysoc->phone : ''), $mysoc->email) as $line) {
        if (!empty($line)) {
            $pdf->SetX($addr_x);
            $pdf->Cell(70, 4, ps($line, $outputlangs), 0, 1, 'R');
        }
    }

    $posy = max($posy + $logo_h, $pdf->GetY()) + 5;

    // Title bar — matching blue of table header
    $de_months = array(1=>'Januar',2=>'Februar',3=>'März',4=>'April',5=>'Mai',6=>'Juni',7=>'Juli',8=>'August',9=>'September',10=>'Oktober',11=>'November',12=>'Dezember');
    $title_full = $list_title.' '.$de_months[(int)date('n')].' '.date('Y');
    $pdf->SetFont($font, 'B', $fsz + 3);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(50, 80, 120);
    $pdf->SetXY($mg_left, $posy);
    $pdf->Cell($content_w, 10, ps($title_full, $outputlangs), 1, 1, 'C', true);
    $pdf->SetTextColor(30, 30, 30);
    $posy += 12;

    // Date right
    $pdf->SetFont($font, '', $fsz - 1);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY($mg_left, $posy);
    $pdf->Cell($content_w, 5, 'Stand: '.date('d.m.Y'), 0, 1, 'R');
    $posy += 6;

    // Customer box
    if ($customer) {
        $lines = array($customer->name);
        foreach (explode("\n", $customer->address ?? '') as $l) { if (trim($l)) $lines[] = trim($l); }
        if ($customer->zip || $customer->town) $lines[] = trim($customer->zip.' '.$customer->town);
        $box_h = count($lines) * 4.5 + 8 + ($discount > 0 ? 6 : 0);
        $box_x = $mg_left + $content_w - 90;
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetFillColor(248, 248, 250);
        $pdf->Rect($box_x, $posy, 90, $box_h, 'DF');
        $pdf->SetFont($font, 'B', $fsz - 1);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->SetXY($box_x + 2, $posy + 2);
        $pdf->Cell(86, 4.5, 'Für:', 0, 1, 'L');
        $pdf->SetFont($font, '', $fsz - 1);
        $pdf->SetTextColor(40, 40, 40);
        foreach ($lines as $l) { $pdf->SetX($box_x + 2); $pdf->Cell(86, 4.5, ps($l, $outputlangs), 0, 1, 'L'); }
        if ($discount > 0) {
            $pdf->SetFont($font, 'B', $fsz - 1);
            $pdf->SetTextColor(160, 20, 20);
            $pdf->SetX($box_x + 2);
            $pdf->Cell(86, 5, 'Sonderkonditionen: '.number_format($discount, 1, ',', '.').' % Rabatt', 0, 1, 'L');
        }
        $posy = max($posy + $box_h, $pdf->GetY()) + 4;
    }

    $pdf->SetTextColor(30, 30, 30);
    $pdf->SetDrawColor(0, 0, 0);
    return $posy;
}

// ─── Table header ─────────────────────────────────────────────────────────────

function drawTableHeader(&$pdf, $fsz, $font, $mg_left, $content_w, $col_pos, $col_lbl, $col_unit, $col_net, $col_vat, $posy)
{
    $pdf->SetFont($font, 'B', $fsz - 1);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(80, 125, 175);
    $pdf->SetXY($mg_left, $posy);
    $pdf->Cell($col_pos,  7, 'Pos.',        'B', 0, 'C', true);
    $pdf->Cell($col_lbl,  7, 'Leistung',    'B', 0, 'L', true);
    $pdf->Cell($col_unit, 7, 'Einheit',     'B', 0, 'C', true);
    $pdf->Cell($col_net,  7, 'Preis netto', 'B', 0, 'R', true);
    $pdf->Cell($col_vat,  7, 'MwSt.',       'B', 1, 'C', true);
    $pdf->SetTextColor(30, 30, 30);
    return $posy + 8;
}

// ─── Column widths ────────────────────────────────────────────────────────────

$col_pos  = 10;
$col_unit = 24;
$col_net  = 32;
$col_vat  = 20;
$col_lbl  = $content_w - $col_pos - $col_unit - $col_net - $col_vat; // ~104mm

// ─── Render first page header ─────────────────────────────────────────────────

$posy = drawHeader($pdf, $fsz, $font, $outputlangs, $mysoc, $mg_left, $mg_right, $mg_top, $content_w, $list_title, $customer, $discount, $conf);
$posy = drawTableHeader($pdf, $fsz, $font, $mg_left, $content_w, $col_pos, $col_lbl, $col_unit, $col_net, $col_vat, $posy);

$page_num   = 1;
$row_fill   = false;
$footer_h   = 14; // space reserved at bottom for footer note

// ─── Rows ─────────────────────────────────────────────────────────────────────

foreach ($items as $idx => $item) {
    $label    = ps($item->label, $outputlangs);
    $desc     = ps(strip_tags(str_replace(array('<br>','<br/>','</p>','</li>'), ' ', $item->description ?? '')), $outputlangs);
    $unit     = ps($item->unit, $outputlangs);
    $has_desc = ($desc !== '');

    $price_str = fmtPrc($item->product_price, $discount);
    $vat_str   = $item->product_tva_tx !== null ? number_format((float)$item->product_tva_tx, 0).' %' : '–';

    // Calculate row height: estimate description lines
    $pdf->SetFont($font, 'B', $fsz - 1);
    $lbl_h = $pdf->getStringHeight($col_lbl - 3, $label);
    $dsc_h = 0;
    if ($has_desc) {
        $pdf->SetFont($font, 'I', $fsz - 2);
        $dsc_h = $pdf->getStringHeight($col_lbl - 3, $desc);
    }
    $row_h = max($min_row, $lbl_h + $dsc_h + $pad * 3);

    // Page break?
    if ($posy + $row_h > $page_h - $mg_bottom - $footer_h) {
        // Footer on current page
        $pdf->SetFont($font, 'I', $fsz - 2);
        $pdf->SetTextColor(140, 140, 140);
        $pdf->SetXY($mg_left, $page_h - $mg_bottom - 5);
        $pdf->Cell($content_w, 4, 'Seite '.$page_num, 0, 0, 'R');
        // New page
        $pdf->AddPage();
        $page_num++;
        $posy = drawHeader($pdf, $fsz, $font, $outputlangs, $mysoc, $mg_left, $mg_right, $mg_top, $content_w, $list_title, $customer, $discount, $conf);
        $posy = drawTableHeader($pdf, $fsz, $font, $mg_left, $content_w, $col_pos, $col_lbl, $col_unit, $col_net, $col_vat, $posy);
        $row_fill = false;
    }

    // Background
    $bg = $row_fill ? array(243, 246, 251) : array(255, 255, 255);
    $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);
    $pdf->SetDrawColor(200, 205, 215);
    $row_fill = !$row_fill;

    // Draw background rect + column borders
    $pdf->Rect($mg_left, $posy, $content_w, $row_h, 'DF');

    // Label + description (text only, no borders — rect already drawn)
    $pad = 1.5;
    $pdf->SetFont($font, 'B', $fsz - 1);
    $pdf->SetTextColor(20, 20, 60);
    $pdf->SetXY($mg_left + $col_pos + $pad, $posy + $pad);
    $pdf->MultiCell($col_lbl - $pad * 2, $line_h, $label, 0, 'L', false);

    if ($has_desc) {
        $pdf->SetFont($font, 'I', $fsz - 2);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetX($mg_left + $col_pos + $pad);
        $pdf->MultiCell($col_lbl - $pad * 2, $desc_h, $desc, 0, 'L', false);
    }

    // Other cells: border + text, at fixed row position
    $pdf->SetFont($font, '', $fsz - 1);
    $pdf->SetTextColor(30, 30, 30);

    $pdf->SetXY($mg_left, $posy);
    $pdf->Cell($col_pos, $row_h, ($idx + 1).'.', 'LR', 0, 'C', false);

    $pdf->SetXY($mg_left + $col_pos, $posy);
    $pdf->Cell($col_lbl, $row_h, '', 'LR', 0, 'L', false); // border only

    $pdf->SetXY($mg_left + $col_pos + $col_lbl, $posy);
    $pdf->Cell($col_unit, $row_h, $unit, 'LR', 0, 'C', false);
    $pdf->Cell($col_net,  $row_h, $price_str, 'LR', 0, 'R', false);
    $pdf->Cell($col_vat,  $row_h, $vat_str,   'LR', 0, 'C', false);

    $posy += $row_h;
}

// Closing bottom border of table
$pdf->SetDrawColor(200, 205, 215);
$pdf->Line($mg_left, $posy, $mg_left + $content_w, $posy);

// ─── Footer note ──────────────────────────────────────────────────────────────

$posy += 6;
$pdf->SetFont($font, 'I', $fsz - 2);
$pdf->SetTextColor(130, 130, 130);
$pdf->SetXY($mg_left, $posy);
$footer_note = 'Alle Preise netto zzgl. gesetzlicher MwSt.';
if ($discount > 0) $footer_note .= '   |   Kundenpreise inkl. '.number_format($discount, 1, ',', '.').' % Rabatt';
$pdf->Cell($content_w, 4.5, $footer_note, 0, 1, 'C');

// Page number (bottom right)
$pdf->SetXY($mg_left, $page_h - $mg_bottom - 5);
$pdf->Cell($content_w, 4, 'Seite '.$page_num, 0, 0, 'R');

// ─── Output ───────────────────────────────────────────────────────────────────

$filename = 'Preisliste_'.($list_type === 'maintenance' ? 'Wartung' : 'Verrechnungssatz');
if ($customer) $filename .= '_'.dol_sanitizeFileName($customer->name);
$filename .= '_'.date('Y-m-d').'.pdf';

if (ob_get_length()) ob_end_clean();
$pdf->Output($filename, 'I');
