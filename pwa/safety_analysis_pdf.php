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

// ── Diagram helpers ───────────────────────────────────────────────────────
function _dPanel($pdf)  { $pdf->SetFillColor(70, 120, 190); $pdf->SetDrawColor(45, 95, 165); }
function _dStruct($pdf) { $pdf->SetFillColor(140, 145, 150); $pdf->SetDrawColor(100, 105, 110); }
function _dArrowR($pdf, $x1, $y, $x2) {
    $pdf->SetDrawColor(35, 35, 35); $pdf->SetLineWidth(0.65);
    $pdf->Line($x1, $y, $x2, $y);
    $pdf->Line($x2, $y, $x2 - 3, $y - 2); $pdf->Line($x2, $y, $x2 - 3, $y + 2);
}
function _dArrowL($pdf, $x1, $y, $x2) { // x2 < x1 (Pfeil nach links)
    $pdf->SetDrawColor(35, 35, 35); $pdf->SetLineWidth(0.65);
    $pdf->Line($x1, $y, $x2, $y);
    $pdf->Line($x2, $y, $x2 + 3, $y - 2); $pdf->Line($x2, $y, $x2 + 3, $y + 2);
}
function _dMH($pdf, $x1, $x2, $my, $label) {
    $pdf->SetLineWidth(0.3); $pdf->SetDrawColor(60, 60, 60);
    $pdf->Line($x1, $my, $x2, $my);
    $pdf->Line($x1, $my - 1.5, $x1, $my + 1.5); $pdf->Line($x2, $my - 1.5, $x2, $my + 1.5);
    $pdf->SetFont('helvetica', '', 5.5); $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($x1, $my + 0.5); $pdf->Cell($x2 - $x1, 3, $label, 0, 0, 'C');
    $pdf->SetLineWidth(0.3); $pdf->SetDrawColor(80, 80, 80);
}
function _dMV($pdf, $mx, $y1, $y2, $label) {
    $pdf->SetLineWidth(0.3); $pdf->SetDrawColor(60, 60, 60);
    $pdf->Line($mx, $y1, $mx, $y2);
    $pdf->Line($mx - 1.5, $y1, $mx + 1.5, $y1); $pdf->Line($mx - 1.5, $y2, $mx + 1.5, $y2);
    $pdf->SetFont('helvetica', '', 5.5); $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($mx + 1, ($y1 + $y2) / 2 - 1.5); $pdf->Cell(8, 3, $label, 0, 0, 'L');
    $pdf->SetLineWidth(0.3); $pdf->SetDrawColor(80, 80, 80);
}

// ── Diagram: HSK – Schließfahrt ───────────────────────────────────────────
function drawDiagramHSK($pdf, $x, $y, $w, $h) {
    $pdf->SetLineWidth(0.3);
    $mid = $x + $w / 2; $cy = $y + $h / 2;
    $pw = 14; $rH = 2.5; $panH = $h - 10; $py = $cy - $panH / 2;

    // Linkes Türblatt mit Schienen
    _dStruct($pdf);
    $pdf->Rect($x + 2, $py, $pw, $rH, 'DF');
    $pdf->Rect($x + 2, $py + $panH - $rH, $pw, $rH, 'DF');
    _dPanel($pdf);
    $pdf->Rect($x + 2, $py + $rH, $pw, $panH - 2 * $rH, 'DF');

    // Rechtes Türblatt mit Schienen
    $rx = $x + $w - 2 - $pw;
    _dStruct($pdf);
    $pdf->Rect($rx, $py, $pw, $rH, 'DF');
    $pdf->Rect($rx, $py + $panH - $rH, $pw, $rH, 'DF');
    _dPanel($pdf);
    $pdf->Rect($rx, $py + $rH, $pw, $panH - 2 * $rH, 'DF');

    // Pfeile aufeinander zu
    _dArrowR($pdf, $x + 2 + $pw + 1, $cy, $mid - 4);
    _dArrowL($pdf, $rx - 1,           $cy, $mid + 4);

    // Warndreieck
    $ts = 5.5;
    $pdf->SetFillColor(200, 30, 30); $pdf->SetDrawColor(150, 15, 15); $pdf->SetLineWidth(0.4);
    $pdf->Polygon([$mid - $ts, $cy + $ts, $mid + $ts, $cy + $ts, $mid, $cy - $ts], 'DF');
    $pdf->SetFont('helvetica', 'B', 7); $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY($mid - 2, $cy - 1.5); $pdf->Cell(4, 5, '!', 0, 0, 'C');
    $pdf->SetTextColor(0, 0, 0); $pdf->SetDrawColor(80, 80, 80); $pdf->SetLineWidth(0.3);
}

// ── Diagram: Quetschen ────────────────────────────────────────────────────
// DRAUFSICHT: Γ-Form (Querarm oben + Längsarm rechts), Blatt darunter
// Y = Abstand Blatt-Oberkante ↔ Querarm-Unterkante, x = Abstand Blattkante ↔ Längsarm
function drawDiagramQuetschen($pdf, $x, $y, $w, $h) {
    $pdf->SetLineWidth(0.3);
    $panH  = 3;   // Türblattdicke
    $armH  = 6;   // Γ Querarm-Höhe (oben)
    $armW  = 3;   // Γ Längsarm-Breite (rechts)
    $gY    = 4;   // Y-Abstand (Querarm-Unterkante → Blatt-Oberkante)
    $gX    = 5;   // x-Abstand (Blatt-Kante → Längsarm)
    $armL  = $x + round($w * 0.44); // Querarm beginnt hier

    // Γ-Form: Querarm oben (horizontal, läuft nach rechts bis zur Kante)
    _dStruct($pdf);
    $pdf->Rect($armL, $y + 4, $x + $w - 2 - $armL, $armH, 'DF');
    // Γ-Form: Längsarm rechts (vertikal, läuft von Querarm-Unterkante nach unten)
    $vArmX = $x + $w - 2 - $armW;
    $vArmT = $y + 4 + $armH;
    $pdf->Rect($vArmX, $vArmT, $armW, $h - 4 - $armH - 4, 'DF');

    // Türblatt (thin, blau)
    $panY  = $vArmT + $gY;
    $panX2 = $vArmX - $gX;
    _dPanel($pdf);
    $pdf->Rect($x + 2, $panY, $panX2 - $x - 2, $panH, 'DF');

    // Pfeil →
    _dArrowR($pdf, $x + 5, $panY + $panH / 2, $panX2 - 1);

    // Y-Maß (Querarm-Unterkante → Blatt-Oberkante), im Γ-Bereich
    _dMV($pdf, $armL + 3, $vArmT, $panY, 'Y');

    // x-Maß (Blattkante → Längsarm)
    _dMH($pdf, $panX2, $vArmX, $panY + $panH + 2.5, 'x');

    $pdf->SetDrawColor(80, 80, 80); $pdf->SetLineWidth(0.3);
}

// ── Diagram: Anstoßen ────────────────────────────────────────────────────
// DRAUFSICHT: ⌐-Form (Längsarm rechts oben + Querarm rechts unten), Blatt links
// x = Abstand Blattkante ↔ Längsarm
function drawDiagramAnstossen($pdf, $x, $y, $w, $h) {
    $pdf->SetLineWidth(0.3);
    $panH  = 3;
    $armH  = 6;   // ⌐ Querarm-Höhe (unten)
    $armW  = 3;   // ⌐ Längsarm-Breite (rechts)
    $gX    = 5;   // x-Abstand
    $armL  = $x + round($w * 0.44);
    $vArmX = $x + $w - 2 - $armW;

    // ⌐-Form: Längsarm rechts (vertikal, von oben bis Querarm)
    _dStruct($pdf);
    $vArmB = $y + $h - 4 - $armH;
    $pdf->Rect($vArmX, $y + 4, $armW, $vArmB - $y - 4, 'DF');
    // ⌐-Form: Querarm unten rechts (horizontal)
    $pdf->Rect($armL, $vArmB, $x + $w - 2 - $armL, $armH, 'DF');

    // Türblatt (thin, blau), vertikal zentriert (oberhalb des Querarms)
    $panY  = $y + ($vArmB - $y - $panH) / 2;
    $panX2 = $vArmX - $gX;
    _dPanel($pdf);
    $pdf->Rect($x + 2, $panY, $panX2 - $x - 2, $panH, 'DF');

    // Pfeil →
    _dArrowR($pdf, $x + 5, $panY + $panH / 2, $panX2 - 1);

    // x-Maß (Blattkante → Längsarm)
    _dMH($pdf, $panX2, $vArmX, $panY + $panH + 2.5, 'x');

    $pdf->SetDrawColor(80, 80, 80); $pdf->SetLineWidth(0.3);
}

// ── Diagram: Scheren ──────────────────────────────────────────────────────
// DRAUFSICHT: zwei dünne Flügel nahe beieinander, Wand am rechten Ende
// S = seitl. Spalt (sehr klein), t = Überlappungszone
function drawDiagramScheren($pdf, $x, $y, $w, $h) {
    $pdf->SetLineWidth(0.3);
    $panH  = 3;    // Türblattdicke
    $gapS  = 3;    // S sehr klein (nahe beieinander)
    $wallW = 4;    // Wand rechts
    $wallX = $x + $w - 2 - $wallW;
    $cy    = $y + $h / 2;

    // Wand am rechten Ende
    _dStruct($pdf);
    $pdf->Rect($wallX, $y + 3, $wallW, $h - 6, 'DF');

    // Flügel 1 (oben, kürzer – stationärer Referenzflügel)
    $p1Y   = $cy - $gapS / 2 - $panH;
    $p1W   = $w * 0.58;
    _dPanel($pdf);
    $pdf->Rect($x + 2, $p1Y, $p1W, $panH, 'DF');

    // Flügel 2 (unten, länger, fährt nach rechts zur Wand)
    $p2Y   = $cy + $gapS / 2;
    $p2X2  = $wallX - 2;          // Flügel 2 endet kurz vor der Wand
    $p2X   = $x + 2 + $w * 0.36;  // Flügel 2 beginnt versetzt
    $pdf->Rect($p2X, $p2Y, $p2X2 - $p2X, $panH, 'DF');

    // Pfeil: Flügel 2 bewegt sich → Wand
    _dArrowR($pdf, $x + 3, $p2Y + $panH / 2, $p2X - 1);

    // S-Maß (sehr kleiner Spalt zwischen Flügeln, in Überlappungszone)
    $ovX   = $p2X + ($p2X2 - $p2X) * 0.4;
    _dMV($pdf, $ovX, $p1Y + $panH, $p2Y, 'S');

    // t-Maß (Überlappungszone: wo beide Flügel nebeneinander)
    $tL = $p2X; $tR = $x + 2 + $p1W;
    if ($tR > $tL) _dMH($pdf, $tL, $tR, $p2Y + $panH + 2.5, 't');

    $pdf->SetDrawColor(80, 80, 80); $pdf->SetLineWidth(0.3);
}

// ── Diagram: Einziehen ────────────────────────────────────────────────────
// DRAUFSICHT: Schlitz in Wand mit Glas (blau), Blatt fährt durch den Schlitz
// x = seitlicher Spalt Blatt ↔ Glaswand (oben und unten)
function drawDiagramEinziehen($pdf, $x, $y, $w, $h) {
    $pdf->SetLineWidth(0.3);
    $panH  = 3;
    $gapX  = 2.5;
    $wallX = $x + round($w * 0.50);
    $wallR = $x + $w - 2;
    $cy    = $y + $h / 2;
    $panY  = $cy - $panH / 2;
    $slotT = $panY - $gapX;
    $slotB = $panY + $panH + $gapX;

    // Wandblock OBEN: grau, Glas (hellblau) als Füllung
    _dStruct($pdf);
    $pdf->Rect($wallX, $y + 2, $wallR - $wallX, $slotT - $y - 2, 'DF');
    $pdf->SetFillColor(90, 140, 200); $pdf->SetDrawColor(60, 110, 170);
    $pdf->Rect($wallX + 1, $y + 3, $wallR - $wallX - 2, $slotT - $y - 4, 'DF');

    // Wandblock UNTEN: grau, Glas (hellblau)
    _dStruct($pdf);
    $pdf->Rect($wallX, $slotB, $wallR - $wallX, $y + $h - 2 - $slotB, 'DF');
    $pdf->SetFillColor(90, 140, 200); $pdf->SetDrawColor(60, 110, 170);
    $pdf->Rect($wallX + 1, $slotB + 1, $wallR - $wallX - 2, $y + $h - 4 - $slotB, 'DF');

    // Türblatt (dunkleres Blau, dünn) – fährt von links durch den Schlitz
    _dPanel($pdf);
    $pdf->Rect($x + 2, $panY, $wallR - $x - 2, $panH, 'DF');

    // Pfeil → (links vom Schlitz)
    _dArrowR($pdf, $x + 5, $panY + $panH / 2, $wallX - 2);

    // x-Maß (Schlitzkante oben → Blatt-Oberkante)
    _dMV($pdf, $wallX - 3.5, $slotT, $panY, 'x');

    $pdf->SetDrawColor(80, 80, 80); $pdf->SetLineWidth(0.3);
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
