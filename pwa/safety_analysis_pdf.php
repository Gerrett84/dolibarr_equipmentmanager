<?php
/**
 * Sicherheitsanalyse PDF – Risikobeurteilung automatische Schiebetüren
 * Gemäß DIN EN 16005 / Maschinenrichtlinie 2006/42/EG (FTA-Format)
 */

define('NOLOGIN', '1');

$res = 0;
if (!$res && file_exists("../../../main.inc.php"))  $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) { http_response_code(503); exit('Environment not found'); }

// ── Auth ──────────────────────────────────────────────────────────────────
if (!$user->id) {
    $pwaToken = GETPOST('pwa_token', 'alpha') ?: ($_SERVER['HTTP_X_PWA_TOKEN'] ?? '');
    if (!empty($pwaToken)) {
        $hashed  = hash('sha256', $pwaToken);
        $sqlTok  = "SELECT fk_user FROM ".MAIN_DB_PREFIX."equipmentmanager_pwa_token"
                 . " WHERE token = '".$db->escape($hashed)."'"
                 . " AND valid_until > '".$db->idate(dol_now())."'";
        $resTok  = $db->query($sqlTok);
        if ($resTok && $db->num_rows($resTok)) {
            $tokObj = $db->fetch_object($resTok);
            require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
            $user = new User($db);
            $user->fetch((int)$tokObj->fk_user);
            $user->getrights();
        }
    }
}
if (!$user->id) { http_response_code(401); exit('Not authenticated'); }

// ── Load analysis ─────────────────────────────────────────────────────────
$analysis_id = (int)GETPOST('id', 'int');
if (!$analysis_id) { http_response_code(400); exit('Missing id'); }

$sqlA = "SELECT sa.*, e.equipment_number, e.serial_number, e.label as eq_label,"
      . " e.manufacturer, e.door_wings,"
      . " s.nom as soc_name, s.address as soc_addr, s.zip as soc_zip, s.town as soc_town,"
      . " s.phone as soc_phone, s.email as soc_email,"
      . " f.ref as fichinter_ref"
      . " FROM ".MAIN_DB_PREFIX."equipmentmanager_safety_analysis sa"
      . " JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e ON e.rowid = sa.fk_equipment"
      . " JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = e.fk_soc"
      . " JOIN ".MAIN_DB_PREFIX."fichinter f ON f.rowid = sa.fk_fichinter"
      . " WHERE sa.rowid = ".$analysis_id;
$resA = $db->query($sqlA);
if (!$resA || !$db->num_rows($resA)) { http_response_code(404); exit('Analysis not found'); }
$a = $db->fetch_object($resA);

$form_data = $a->form_data ? json_decode($a->form_data, true) : [];
$fd = function($path, $default = false) use ($form_data) {
    $keys = explode('.', $path);
    $val  = $form_data;
    foreach ($keys as $k) { $val = $val[$k] ?? null; if ($val === null) return $default; }
    return $val;
};

// OBJ-Kontakt laden (Objektname, Adresse, Ansprechpartner, Telefon)
$obj_name    = '';
$obj_address = '';
$obj_contact = '';
$obj_phone   = '';
$sqlObj = "SELECT sp.lastname, sp.firstname, sp.address, sp.zip, sp.town, sp.phone"
        . " FROM ".MAIN_DB_PREFIX."element_contact ec"
        . " JOIN ".MAIN_DB_PREFIX."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact"
        . " JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = ec.fk_socpeople"
        . " WHERE ec.element_id = ".(int)$a->fk_fichinter
        . " AND tc.element = 'fichinter' AND tc.code = 'OBJ' LIMIT 1";
$resObj = $db->query($sqlObj);
if ($resObj && $db->num_rows($resObj)) {
    $obj         = $db->fetch_object($resObj);
    $obj_name    = trim($obj->lastname);
    $obj_contact = trim($obj->firstname);
    $obj_address = trim($obj->address.', '.$obj->zip.' '.$obj->town);
    $obj_phone   = $obj->phone ?? '';
}

// ── PDF setup ─────────────────────────────────────────────────────────────
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
$outputlangs = $langs;
$outputlangs->loadLangs(['main', 'companies']);

$pdf = pdf_getInstance('A4');
if (class_exists('TCPDF')) {
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
}
$pdf->SetCreator($mysoc->name);
$pdf->SetAuthor($mysoc->name);
$pdf->SetTitle('Sicherheitsanalyse '.$a->fichinter_ref);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetFont('helvetica', '', 9);

// ── Colors ───────────────────────────────────────────────────────────────
$GRAY_BG  = [240, 240, 240];
$GRAY_BD  = [180, 180, 180];
$DARK     = [40, 40, 40];
$RED      = [200, 50, 50];
$WHITE    = [255, 255, 255];

// ── Helper: filled section header ─────────────────────────────────────────
function sectionHeader($pdf, $x, $y, $w, $text) {
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY($x, $y);
    $pdf->Cell($w, 6, $text, 1, 1, 'L', true);
    $pdf->SetFont('helvetica', '', 9);
    return $y + 7;
}

// ── Helper: labeled field (underline style) ────────────────────────────────
function labelField($pdf, $x, $y, $labelW, $fieldW, $label, $value, $h = 5) {
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetXY($x, $y);
    $pdf->Cell($labelW, $h, $label, 0, 0, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($fieldW, $h, $value, 'B', 0, 'L');
}

// ── Helper: checkbox row ───────────────────────────────────────────────────
function checkRow($pdf, $x, $y, $w, $checked, $label, $indent = 0) {
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->SetTextColor(0, 0, 0);
    $cx = $x + $indent;
    $pdf->SetXY($cx, $y + 0.5);
    // Checkbox box
    $pdf->SetDrawColor(80, 80, 80);
    if ($checked) {
        $pdf->SetFillColor(40, 40, 40);
        $pdf->Rect($cx, $y + 1, 3.5, 3.5, 'DF');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY($cx + 0.3, $y + 0.8);
        $pdf->Cell(3.5, 3.5, 'X', 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    } else {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($cx, $y + 1, 3.5, 3.5, 'DF');
    }
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->SetXY($cx + 5, $y);
    $pdf->Cell($w - $indent - 5, 5, $label, 0, 0, 'L');
    return $y + 5;
}

// ── Helper: draw simple door diagram ──────────────────────────────────────
function drawDiagramHSK($pdf, $x, $y, $w, $h) {
    // Schließfahrt: zwei Panels bewegen sich aufeinander zu
    $pdf->SetDrawColor(80, 80, 80);
    $pdf->SetFillColor(210, 230, 255);
    $pw = $w * 0.28; $ph = $h * 0.55; $mid = $x + $w / 2;
    // Linkes Panel
    $pdf->Rect($x + 4, $y + 4, $pw, $ph, 'DF');
    // Rechtes Panel
    $pdf->Rect($x + $w - 4 - $pw, $y + 4, $pw, $ph, 'DF');
    // Pfeile aufeinander zu
    $ay = $y + 4 + $ph / 2;
    $pdf->SetDrawColor(40, 40, 40);
    $pdf->SetLineWidth(0.6);
    $pdf->Line($x + 4 + $pw, $ay, $mid - 5, $ay); // linker Pfeil
    $pdf->Line($mid - 5, $ay, $mid - 8, $ay - 2);
    $pdf->Line($mid - 5, $ay, $mid - 8, $ay + 2);
    $pdf->Line($x + $w - 4 - $pw, $ay, $mid + 5, $ay); // rechter Pfeil
    $pdf->Line($mid + 5, $ay, $mid + 8, $ay - 2);
    $pdf->Line($mid + 5, $ay, $mid + 8, $ay + 2);
    // Blitz-Symbol (Gefahrenzeichen vereinfacht)
    $pdf->SetLineWidth(0.4);
    $pdf->SetDrawColor(200, 100, 0);
    $bx = $mid - 3; $by = $ay - 4;
    $pdf->SetFillColor(255, 200, 0);
    // Dreieck
    $pdf->Polygon([$bx, $by + 7, $bx + 6, $by + 7, $bx + 3, $by], 'DF');
    $pdf->SetDrawColor(80, 80, 80);
    $pdf->SetLineWidth(0.3);
}

function drawDiagramQuetschen($pdf, $x, $y, $w, $h) {
    // Öffnungsfahrt gegen Quetschen: Panel bewegt sich, Y und x Abstände
    $pdf->SetDrawColor(80, 80, 80);
    $pdf->SetFillColor(210, 230, 255);
    $pw = $w * 0.35; $ph = $h * 0.65;
    // Wand rechts
    $pdf->SetFillColor(160, 160, 160);
    $pdf->Rect($x + $w - 6, $y + 2, 5, $ph + 4, 'DF');
    // Panel
    $pdf->SetFillColor(210, 230, 255);
    $gapX = 8;
    $pdf->Rect($x + 4, $y + 4, $pw, $ph, 'DF');
    // Pfeil nach rechts
    $ay = $y + 4 + $ph / 2;
    $pdf->SetDrawColor(40, 40, 40);
    $pdf->SetLineWidth(0.6);
    $ax2 = $x + $w - 6 - $gapX;
    $pdf->Line($x + 4 + $pw, $ay, $ax2, $ay);
    $pdf->Line($ax2, $ay, $ax2 - 3, $ay - 2);
    $pdf->Line($ax2, $ay, $ax2 - 3, $ay + 2);
    // Maßlinie Y (vertikal)
    $pdf->SetLineWidth(0.3);
    $mx = $x + 4 + $pw + 4;
    $pdf->Line($mx, $y + 4, $mx, $y + 4 + $ph);
    $pdf->Line($mx - 1.5, $y + 4, $mx + 1.5, $y + 4);
    $pdf->Line($mx - 1.5, $y + 4 + $ph, $mx + 1.5, $y + 4 + $ph);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($mx + 1, $y + 4 + $ph / 2 - 2);
    $pdf->Cell(8, 4, 'Y≥200', 0, 0, 'L');
    // Maßlinie x (horizontal, Spalt zur Wand)
    $gapStart = $x + 4 + $pw + 12;
    $my = $y + 4 + $ph + 3;
    $pdf->Line($gapStart, $my, $x + $w - 6, $my);
    $pdf->Line($gapStart, $my - 1.5, $gapStart, $my + 1.5);
    $pdf->Line($x + $w - 6, $my - 1.5, $x + $w - 6, $my + 1.5);
    $pdf->SetXY($gapStart, $my + 0.5);
    $pdf->Cell(10, 3, 'x≤100', 0, 0, 'C');
    $pdf->SetDrawColor(80, 80, 80);
    $pdf->SetLineWidth(0.3);
}

function drawDiagramAnstossen($pdf, $x, $y, $w, $h) {
    // gegen Anstoßen
    $pdf->SetDrawColor(80, 80, 80);
    $pdf->SetFillColor(160, 160, 160);
    $pdf->Rect($x + $w - 6, $y + 2, 5, $h - 4, 'DF');
    $pdf->SetFillColor(210, 230, 255);
    $pw = $w * 0.3; $ph = $h * 0.55;
    $pdf->Rect($x + 4, $y + ($h - $ph)/2, $pw, $ph, 'DF');
    $ay = $y + $h / 2;
    $pdf->SetDrawColor(40, 40, 40);
    $pdf->SetLineWidth(0.6);
    $ax2 = $x + $w - 6 - 6;
    $pdf->Line($x + 4 + $pw, $ay, $ax2, $ay);
    $pdf->Line($ax2, $ay, $ax2 - 3, $ay - 2);
    $pdf->Line($ax2, $ay, $ax2 - 3, $ay + 2);
    // x Maß
    $pdf->SetLineWidth(0.3);
    $my = $y + $h - 5;
    $pdf->Line($x + 4 + $pw, $my, $x + $w - 6, $my);
    $pdf->Line($x + 4 + $pw, $my - 1.5, $x + 4 + $pw, $my + 1.5);
    $pdf->Line($x + $w - 6, $my - 1.5, $x + $w - 6, $my + 1.5);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($x + 4 + $pw, $my + 0.5);
    $pdf->Cell($w - 4 - $pw - 6, 3, 'x≤100', 0, 0, 'C');
    $pdf->SetDrawColor(80, 80, 80);
    $pdf->SetLineWidth(0.3);
}

function drawDiagramScheren($pdf, $x, $y, $w, $h) {
    // gegen Scheren: zwei Panels mit Überlappungsbereich
    $pdf->SetDrawColor(80, 80, 80);
    $pw = $w * 0.28; $ph = $h * 0.35; $gap = 4;
    $y1 = $y + 4; $y2 = $y1 + $ph + $gap;
    // Oberes Panel (stationär)
    $pdf->SetFillColor(180, 200, 180);
    $pdf->Rect($x + 4, $y1, $pw, $ph, 'DF');
    // Unteres Panel (bewegt sich)
    $pdf->SetFillColor(210, 230, 255);
    $pdf->Rect($x + 4, $y2, $pw, $ph, 'DF');
    // Zweites Paar rechts
    $rx = $x + $w * 0.55;
    $pdf->SetFillColor(180, 200, 180);
    $pdf->Rect($rx, $y1, $pw, $ph, 'DF');
    $pdf->SetFillColor(210, 230, 255);
    $pdf->Rect($rx, $y2, $pw, $ph, 'DF');
    // Pfeil zwischen den Paaren
    $ay = $y2 + $ph / 2;
    $pdf->SetDrawColor(40, 40, 40);
    $pdf->SetLineWidth(0.6);
    $pdf->Line($x + 4 + $pw, $ay, $rx - 2, $ay);
    $pdf->Line($rx - 2, $ay, $rx - 5, $ay - 2);
    $pdf->Line($rx - 2, $ay, $rx - 5, $ay + 2);
    // S und t Maße
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(0, 0, 0);
    // S = Spalt vertikal
    $sx = $x + 4 + $pw + 1;
    $pdf->Line($sx, $y1 + $ph, $sx, $y2);
    $pdf->Line($sx - 1.5, $y1 + $ph, $sx + 1.5, $y1 + $ph);
    $pdf->Line($sx - 1.5, $y2, $sx + 1.5, $y2);
    $pdf->SetXY($sx + 1, $y1 + $ph + $gap/2 - 1.5);
    $pdf->Cell(5, 3, 'S', 0, 0, 'L');
    $pdf->SetDrawColor(80, 80, 80);
    $pdf->SetLineWidth(0.3);
}

function drawDiagramEinziehen($pdf, $x, $y, $w, $h) {
    // gegen Einziehen
    $pdf->SetDrawColor(80, 80, 80);
    // Wand links
    $pdf->SetFillColor(160, 160, 160);
    $pdf->Rect($x + 2, $y + 2, 5, $h - 4, 'DF');
    // Wand rechts
    $pdf->Rect($x + $w - 6, $y + 2, 5, $h - 4, 'DF');
    // Panel
    $pdf->SetFillColor(210, 230, 255);
    $pw = $w * 0.3; $ph = $h * 0.55;
    $panelX = $x + 12;
    $pdf->Rect($panelX, $y + ($h - $ph)/2, $pw, $ph, 'DF');
    // Pfeil nach rechts
    $ay = $y + $h / 2;
    $pdf->SetDrawColor(40, 40, 40);
    $pdf->SetLineWidth(0.6);
    $ax2 = $x + $w - 6 - 4;
    $pdf->Line($panelX + $pw, $ay, $ax2, $ay);
    $pdf->Line($ax2, $ay, $ax2 - 3, $ay - 2);
    $pdf->Line($ax2, $ay, $ax2 - 3, $ay + 2);
    // x Maß (Spalt rechte Wand)
    $pdf->SetLineWidth(0.3);
    $my = $y + $h - 5;
    $pdf->Line($panelX + $pw, $my, $x + $w - 6, $my);
    $pdf->Line($panelX + $pw, $my - 1.5, $panelX + $pw, $my + 1.5);
    $pdf->Line($x + $w - 6, $my - 1.5, $x + $w - 6, $my + 1.5);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($panelX + $pw, $my + 0.5);
    $pdf->Cell($x + $w - 6 - $panelX - $pw, 3, 'x≤8', 0, 0, 'C');
    $pdf->SetDrawColor(80, 80, 80);
    $pdf->SetLineWidth(0.3);
}

// page=0: beide Seiten (Standard für Download/E-Mail)
// page=1: nur Seite 1 (Deckblatt) – für in-App-Viewer
// page=2: nur Seite 2 (Schutzmaßnahmen) – für in-App-Viewer
$pageOnly = (int)GETPOST('page', 'int');
$lm = 15;
$cw = $pdf->getPageWidth() - 30;

// ────────────────────────────────────────────────────────────────────────────
// SEITE 1 – Deckblatt + Formular
// ────────────────────────────────────────────────────────────────────────────
if ($pageOnly !== 2) {
$pdf->AddPage();
$y  = 15;

// Logo
$logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
if (!empty($mysoc->logo) && file_exists($logo)) {
    $logoH = pdf_getHeightForLogo($logo);
    $pdf->Image($logo, $lm, $y, 0, $logoH);
    $y += $logoH + 4;
} else {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetXY($lm, $y);
    $pdf->Cell($cw, 7, $mysoc->name, 0, 1, 'L');
    $y += 10;
}

// Titel
$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetFillColor(40, 40, 40);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY($lm, $y);
$pdf->Cell($cw, 9, 'Risikobeurteilung – Automatische Schiebetüren', 0, 1, 'C', true);
$y += 10;
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetXY($lm, $y);
$pdf->Cell($cw, 5, 'Gemäß DIN EN 16005 / Maschinenrichtlinie 2006/42/EG', 0, 1, 'C');
$y += 7;

$pdf->SetTextColor(0, 0, 0);

// ── Türdaten ────────────────────────────────────────────────────────────────
$y = sectionHeader($pdf, $lm, $y, $cw, 'Türdaten / Einbauort');
$hw = ($cw / 2) - 2;

labelField($pdf, $lm,        $y, 32, $hw - 32, 'Einbauort:', $a->einbauort ?: '');
labelField($pdf, $lm + $hw + 4, $y, 32, $hw - 32, 'Antriebstyp:', $a->antriebstyp ?: '');
$y += 7;

labelField($pdf, $lm,        $y, 32, $hw - 32, 'Durchgangshöhe:', $a->durchgangshoehe ? $a->durchgangshoehe.' mm' : '');
labelField($pdf, $lm + $hw + 4, $y, 32, $hw - 32, 'Durchgangsbreite:', $a->durchgangsbreite ? $a->durchgangsbreite.' mm' : '');
$y += 7;

// Objektdaten
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetFillColor(248, 248, 248);
$pdf->Rect($lm, $y, $cw, 34, 'DF');
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($lm + 2, $y + 1);
$pdf->Cell($cw - 4, 4, 'Objektdaten:', 0, 1, 'L');
$y += 6;

// Zeile 1: Objektname | Auftrags-Nr.
$fdObjName = $fd('objektdaten.objektname');
labelField($pdf, $lm + 2,       $y, 25, $hw - 27, 'Objektname:', $fdObjName !== false ? $fdObjName : $obj_name);
labelField($pdf, $lm + $hw + 4, $y, 25, $hw - 25, 'Auftrags-Nr.:', $a->fichinter_ref);
$y += 6;

// Zeile 2: Objektadresse | Equipment-Nr.
$fdObjAddr = $fd('objektdaten.adresse');
labelField($pdf, $lm + 2,       $y, 25, $hw - 27, 'Anschrift:', $fdObjAddr !== false ? $fdObjAddr : $obj_address);
labelField($pdf, $lm + $hw + 4, $y, 25, $hw - 25, 'Equipment-Nr.:', $a->equipment_number.($a->serial_number ? ' / '.$a->serial_number : ''));
$y += 6;

// Zeile 3: Ansprechpartner | Hersteller
$fdAnsprech = $fd('objektdaten.ansprechpartner');
labelField($pdf, $lm + 2,       $y, 25, $hw - 27, 'Ansprechpartner:', $fdAnsprech !== false ? $fdAnsprech : $obj_contact);
labelField($pdf, $lm + $hw + 4, $y, 25, $hw - 25, 'Hersteller:', $a->manufacturer ?? '');
$y += 6;

// Zeile 4: Telefon OBJ
$fdPhone = $fd('objektdaten.telefon');
labelField($pdf, $lm + 2,       $y, 25, $hw - 27, 'Telefon:', $fdPhone !== false ? $fdPhone : $obj_phone);
$y += 8;

// Bauliche Gegebenheiten
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($lm, $y);
$pdf->Cell($cw, 4, 'Besondere bauliche Gegebenheiten:', 0, 1, 'L');
$y += 4;
$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($lm, $y);
$pdf->SetDrawColor(180, 180, 180);
$pdf->MultiCell($cw, 4, $a->bauliche_gegebenheiten ?: ' ', 'B', 'L');
$y += 10;

// ── Ersteller & Auftraggeber ───────────────────────────────────────────────
$y = sectionHeader($pdf, $lm, $y, $cw, 'Ersteller der Risikobeurteilung');

labelField($pdf, $lm,        $y, 20, $hw - 20, 'Firma:', $mysoc->name);
labelField($pdf, $lm + $hw + 4, $y, 20, $hw - 20, 'Telefon:', $mysoc->phone ?? '');
$y += 7;
labelField($pdf, $lm,        $y, 20, $hw - 20, 'Name:', $a->sig_ersteller_name ?: '');
labelField($pdf, $lm + $hw + 4, $y, 20, $hw - 20, 'E-Mail:', $mysoc->email ?? '');
$y += 7;
labelField($pdf, $lm,        $y, 20, $hw - 20, 'Ort:', $a->sig_ersteller_ort ?: '');
labelField($pdf, $lm + $hw + 4, $y, 20, $hw - 20, 'Datum:', $a->sig_ersteller_date ?: '');
$y += 10;

// ── Unterschrift Ersteller ─────────────────────────────────────────────────
$pdf->SetFont('helvetica', '', 8);
$pdf->SetDrawColor(160, 160, 160);
$sigBoxH = 22;
$sigW    = $cw / 2 - 3;
$rx2     = $lm + $sigW + 6;

// Volle Breite für Ersteller-Unterschrift
$pdf->SetFillColor(252, 252, 252);
$pdf->Rect($lm, $y, $cw, $sigBoxH, 'DF');
if (!empty($a->sig_ersteller) && strpos($a->sig_ersteller, 'data:image') === 0) {
    $pdf->Image('@'.base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $a->sig_ersteller)),
        $lm + 2, $y + 2, $cw - 4, $sigBoxH - 8);
}
$pdf->SetXY($lm, $y + $sigBoxH - 5);
$pdf->Cell($cw, 4, 'Unterschrift Ersteller', 0, 0, 'C');
$y += $sigBoxH + 4;

// Bestätigung
$pdf->SetFillColor(235, 245, 235);
$pdf->SetDrawColor(150, 200, 150);
$pdf->Rect($lm, $y, $cw, 7, 'DF');
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($lm, $y + 1);
$pdf->Cell($cw, 5, 'Die im Folgenden beschriebenen Schutzmaßnahmen sind einzuhalten.', 0, 1, 'C');
$y += 9;

// Monteur-Unterschrift
$pdf->SetFillColor(252, 252, 252);
$pdf->SetDrawColor(160, 160, 160);
$pdf->Rect($lm, $y, $sigW, $sigBoxH, 'DF');
if (!empty($a->sig_monteur) && strpos($a->sig_monteur, 'data:image') === 0) {
    $pdf->Image('@'.base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $a->sig_monteur)),
        $lm + 2, $y + 2, $sigW - 4, $sigBoxH - 8);
}
$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($lm, $y + $sigBoxH - 5);
$pdf->Cell($sigW, 4, 'Unterschrift Monteur', 0, 0, 'C');
$pdf->SetXY($rx2, $y + 2);
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(80, 80, 80);
$pdf->MultiCell($sigW, 4, 'Datum: '.($a->sig_monteur_date ?: '___________')."\nName: ".($a->sig_monteur_name ?: ''), 0, 'L');
$pdf->SetTextColor(0, 0, 0);
$y += $sigBoxH + 4;

} // end if ($pageOnly !== 2)

// ────────────────────────────────────────────────────────────────────────────
// SEITE 2 – Schutzmaßnahmen mit Diagrammen
// ────────────────────────────────────────────────────────────────────────────
if ($pageOnly !== 1) {
$pdf->AddPage();
$y = 15;

// Titel
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(40, 40, 40);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY($lm, $y);
$pdf->Cell($cw, 8, 'Schutzmaßnahmen – Risikobeurteilung', 0, 1, 'C', true);
$y += 10;
$pdf->SetTextColor(0, 0, 0);

// Info-Zeile
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetXY($lm, $y);
$pdf->Cell($cw, 4, 'Gemäß DIN EN 16005 / Maschinenrichtlinie 2006/42/EG – je Abschnitt mindestens eine Maßnahme erforderlich', 0, 1, 'C');
$y += 6;
$pdf->SetTextColor(0, 0, 0);

$diagW = 48; // Breite Diagramm-Spalte
$textX = $lm + $diagW + 4;
$textW = $cw - $diagW - 4;

// ── I. Schließfahrt ──────────────────────────────────────────────────────
$secY = $y;
$pdf->SetFillColor(50, 80, 140);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 8.5);
$pdf->SetXY($lm, $y);
$pdf->Cell($cw, 6, 'I.  Betriebszustand – kraftbetätigte Schließfahrt – Absicherung Hauptschließkante (HSK)', 0, 1, 'L', true);
$y += 7;
$pdf->SetTextColor(0, 0, 0);

$pdf->SetFont('helvetica', 'I', 7.5);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetXY($lm + 2, $y);
$pdf->Cell($cw - 2, 4, 'gegen Anstoßen / Quetschen', 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);
$y += 5;

$rowStart = $y;
drawDiagramHSK($pdf, $lm, $y, $diagW, 28);
$y2 = checkRow($pdf, $textX, $y + 2, $textW, $fd('schliesfahrt.lichtvorhang'), 'Lichtvorhang beidseitig über die komplette Durchgangsbreite');
$y = max($rowStart + 30, $y2 + 2);
$y += 4;

// ── II. Öffnungsfahrt ─────────────────────────────────────────────────────
$pdf->SetFillColor(50, 80, 140);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 8.5);
$pdf->SetXY($lm, $y);
$pdf->Cell($cw, 6, 'II.  Betriebszustand – kraftbetätigte Öffnungsfahrt – Absicherung Nebenschließkante (NSK)', 0, 1, 'L', true);
$y += 7;
$pdf->SetTextColor(0, 0, 0);

// gegen Quetschen
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($lm + 2, $y);
$pdf->Cell($cw - 2, 5, 'gegen Quetschen:', 0, 1, 'L');
$y += 5;
$rowStart = $y;
drawDiagramQuetschen($pdf, $lm, $y, $diagW, 30);
$y2 = checkRow($pdf, $textX, $y + 1, $textW, $fd('oeffnungsfahrt.quetschen.schutzeinrichtung'), 'Trennende Schutzeinrichtung (z. B. Schutzflügel)');
$y2 = checkRow($pdf, $textX, $y2 + 1, $textW, $fd('oeffnungsfahrt.quetschen.sicherheitsabstaende'), 'Sicherheitsabstände eingehalten (Y ≥ 200 mm und x ≤ 100 mm)');
$y2 = checkRow($pdf, $textX, $y2 + 1, $textW, $fd('oeffnungsfahrt.quetschen.vertikale'), 'Vertikale berührungslos wirkende Schutzeinrichtungen');
$y = max($rowStart + 32, $y2 + 2);
$y += 3;

// gegen Anstoßen
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($lm + 2, $y);
$pdf->Cell($cw - 2, 5, 'gegen Anstoßen:', 0, 1, 'L');
$y += 5;
$rowStart = $y;
drawDiagramAnstossen($pdf, $lm, $y, $diagW, 26);
$y2 = checkRow($pdf, $textX, $y + 1, $textW, $fd('oeffnungsfahrt.anstossen.schutzeinrichtung'), 'Trennende Schutzeinrichtung (z. B. Schutzflügel)');
$y2 = checkRow($pdf, $textX, $y2 + 1, $textW, $fd('oeffnungsfahrt.anstossen.sicherheitsabstaende'), 'Sicherheitsabstände eingehalten (x ≤ 100, oder 100 < x ≤ 150 mit Kraftbegrenzung)');
$y2 = checkRow($pdf, $textX, $y2 + 1, $textW, $fd('oeffnungsfahrt.anstossen.vertikale'), 'Vertikale berührungslos wirkende Schutzeinrichtungen');
$y = max($rowStart + 28, $y2 + 2);
$y += 3;

// gegen Scheren
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($lm + 2, $y);
$pdf->Cell($cw - 2, 5, 'gegen Scheren:', 0, 1, 'L');
$y += 5;
$rowStart = $y;
drawDiagramScheren($pdf, $lm, $y, $diagW, 26);
$y2 = checkRow($pdf, $textX, $y + 1, $textW, $fd('oeffnungsfahrt.scheren.schutzeinrichtung'), 'Trennende Schutzeinrichtung (z. B. Schutzflügel)');
$y2 = checkRow($pdf, $textX, $y2 + 1, $textW, $fd('oeffnungsfahrt.scheren.sicherheitsabstaende'), 'Sicherheitsabstände eingehalten (S ≤ 8 → t = 0 mm; S > 8 → t ≥ 25 mm)');
$y2 = checkRow($pdf, $textX, $y2 + 1, $textW, $fd('oeffnungsfahrt.scheren.vertikale'), 'Vertikale berührungslos wirkende Schutzeinrichtungen');
$y = max($rowStart + 28, $y2 + 2);
$y += 3;

// gegen Einziehen
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($lm + 2, $y);
$pdf->Cell($cw - 2, 5, 'gegen Einziehen:', 0, 1, 'L');
$y += 5;
$rowStart = $y;
drawDiagramEinziehen($pdf, $lm, $y, $diagW, 24);
$y2 = checkRow($pdf, $textX, $y + 1, $textW, $fd('oeffnungsfahrt.einziehen.schutzeinrichtung'), 'Trennende Schutzeinrichtung (z. B. Schutzflügel)');
$y2 = checkRow($pdf, $textX, $y2 + 1, $textW, $fd('oeffnungsfahrt.einziehen.sicherheitsabstaende'), 'Sicherheitsabstände eingehalten (x ≤ 8 mm)');
$y = max($rowStart + 26, $y2 + 2);
$y += 6;

// Hinweis
$pdf->SetFont('helvetica', 'I', 7.5);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetXY($lm, $y);
$pdf->Cell($cw, 4, '* Die Absicherung mit Schutzflügeln ist bei Teleskop-Schiebetüren nicht möglich.', 0, 1, 'L');
$y += 5;
$pdf->SetTextColor(0, 0, 0);

// Fußzeile Seite 2 – AutoPageBreak deaktivieren, damit TCPDF keinen Seitenumbruch
// bei y=285mm (> Trigger 277mm) einfügt und keine leere Seite 3 entsteht.
$pdf->SetAutoPageBreak(false);
$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(120, 120, 120);
$pdf->SetXY($lm, $pdf->getPageHeight() - 12);
$pdf->Cell($cw/2, 4, $mysoc->name.' – Sicherheitsanalyse '.$a->fichinter_ref, 0, 0, 'L');
$pdf->Cell($cw/2, 4, 'Stand: '.dol_print_date(dol_now(), 'day').'  |  Seite 2 von 2', 0, 0, 'R');
} // end if ($pageOnly !== 1)

// ── Stream ────────────────────────────────────────────────────────────────
$filename = 'Sicherheitsanalyse_'.$a->fichinter_ref.'_'.$a->equipment_number.'.pdf';
$pdf->Output($filename, 'I');
exit;
