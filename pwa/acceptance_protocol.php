<?php
/**
 * Acceptance Protocol PDF Generator (v4.5.1)
 * Generates acceptance protocol for service interventions
 * Two-column layout: Inbetriebnahme | Abnahme
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
    $res = include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = include "../../../../main.inc.php";
}
if (!$res) {
    die("Dolibarr environment not found");
}

require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once dol_buildpath('/equipmentmanager/class/equipment.class.php', 0);
require_once dol_buildpath('/equipmentmanager/class/interventiondetail.class.php', 0);

// Check authentication
if (!$user->id) {
    http_response_code(401);
    die('Not authenticated');
}

// Get intervention ID
$id = GETPOST('id', 'int');
if (!$id) {
    http_response_code(400);
    die('Missing intervention ID');
}

// Load intervention
$fichinter = new Fichinter($db);
$result = $fichinter->fetch($id);
if ($result <= 0) {
    http_response_code(404);
    die('Intervention not found');
}

// Check permission
if (!$user->hasRight('ficheinter', 'lire')) {
    http_response_code(403);
    die('Access denied');
}

// Fetch related data
$fichinter->fetch_thirdparty();

// Get object address contact
$objectAddress = null;
$contacts = $fichinter->liste_contact(-1, 'external');
if (is_array($contacts)) {
    foreach ($contacts as $contact) {
        if ($contact['code'] == 'OBJ') {
            $contactObj = new Contact($db);
            $contactObj->fetch($contact['id']);
            $objectAddress = $contactObj;
            break;
        }
    }
}

// Get ALL service equipment (not filtered by commissioning/acceptance)
$sql = "SELECT e.rowid, e.equipment_number, e.label, e.equipment_type, e.serial_number,";
$sql .= " e.location_note, e.manufacturer,";
$sql .= " l.link_type,";
$sql .= " d.commissioning_done, d.commissioning_date, d.commissioning_note,";
$sql .= " d.acceptance_done, d.acceptance_date, d.acceptance_defect_free, d.acceptance_note,";
$sql .= " d.instruction_done, d.testbook_handed";
$sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link l";
$sql .= " JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e ON e.rowid = l.fk_equipment";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail d";
$sql .= "   ON d.fk_intervention = l.fk_intervention AND d.fk_equipment = l.fk_equipment";
$sql .= " WHERE l.fk_intervention = ".(int)$id;
$sql .= " AND l.link_type = 'service'";
$sql .= " ORDER BY e.equipment_number";

$resql = $db->query($sql);
$equipmentList = [];
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $equipmentList[] = $obj;
    }
}

if (empty($equipmentList)) {
    http_response_code(400);
    die('Keine Service-Anlagen gefunden');
}

// Get equipment type labels
$typeLabels = Equipment::getEquipmentTypesTranslated($db, $langs);

// Setup output language
$outputlangs = $langs;
$outputlangs->loadLangs(array("main", "interventions", "companies", "equipmentmanager@equipmentmanager"));

// Create PDF
$pdf = pdf_getInstance();
$pdf->SetCreator("Dolibarr ".DOL_VERSION);
$pdf->SetAuthor($mysoc->name);
$pdf->SetTitle("Abnahmeprotokoll ".$fichinter->ref);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

$default_font_size = 9;
$pageWidth = $pdf->getPageWidth();
$contentWidth = $pageWidth - 30; // margins
$leftMargin = 15;

// ========== HEADER ==========
$pdf->SetFont('', 'B', 16);
$pdf->Cell(0, 10, "INBETRIEBNAHME- UND ABNAHMEPROTOKOLL", 0, 1, 'C');
$pdf->SetFont('', '', $default_font_size);
$pdf->Cell(0, 5, "Serviceauftrag: ".$fichinter->ref, 0, 1, 'C');
$pdf->Ln(6);

// ========== CUSTOMER / OBJECT ADDRESS (Two columns) ==========
$colWidth = ($contentWidth / 2) - 5;
$startY = $pdf->GetY();

// Headers
$pdf->SetFont('', 'B', $default_font_size);
$pdf->Cell($colWidth, 5, "Auftraggeber:", 0, 0);
$pdf->SetX($leftMargin + $colWidth + 10);
$pdf->Cell($colWidth, 5, "Objektadresse:", 0, 1);

$pdf->SetFont('', '', $default_font_size - 1);
$infoY = $pdf->GetY();

// Customer details (left)
$pdf->SetXY($leftMargin, $infoY);
if ($fichinter->thirdparty) {
    $pdf->MultiCell($colWidth, 4, $fichinter->thirdparty->name, 0, 'L');
    if ($fichinter->thirdparty->address) {
        $pdf->SetX($leftMargin);
        $pdf->MultiCell($colWidth, 4, $fichinter->thirdparty->address, 0, 'L');
    }
    if ($fichinter->thirdparty->zip || $fichinter->thirdparty->town) {
        $pdf->SetX($leftMargin);
        $pdf->Cell($colWidth, 4, trim($fichinter->thirdparty->zip.' '.$fichinter->thirdparty->town), 0, 1);
    }
}
$customerEndY = $pdf->GetY();

// Object address (right)
$pdf->SetXY($leftMargin + $colWidth + 10, $infoY);
if ($objectAddress) {
    if ($objectAddress->address) {
        $pdf->MultiCell($colWidth, 4, $objectAddress->address, 0, 'L');
    }
    if ($objectAddress->zip || $objectAddress->town) {
        $pdf->SetX($leftMargin + $colWidth + 10);
        $pdf->Cell($colWidth, 4, trim($objectAddress->zip.' '.$objectAddress->town), 0, 1);
    }
} else {
    $pdf->Cell($colWidth, 4, "-", 0, 1);
}
$objectEndY = $pdf->GetY();

$pdf->SetY(max($customerEndY, $objectEndY) + 6);

// ========== EQUIPMENT LIST ==========
foreach ($equipmentList as $eq) {
    // Check if we need a new page (need ~60mm for one equipment block)
    if ($pdf->GetY() > 200) {
        $pdf->AddPage();
    }

    $typeLabel = isset($typeLabels[$eq->equipment_type]) ? $typeLabels[$eq->equipment_type] : $eq->equipment_type;

    // Equipment header with gray background
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('', 'B', $default_font_size);
    $pdf->Cell(0, 6, $eq->equipment_number." - ".$eq->label, 1, 1, 'L', true);

    // Equipment details line
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetFont('', '', $default_font_size - 1);
    $details = "Typ: ".$typeLabel;
    if (!empty($eq->serial_number)) $details .= "  |  S/N: ".$eq->serial_number;
    if (!empty($eq->manufacturer)) $details .= "  |  Hersteller: ".$eq->manufacturer;
    if (!empty($eq->location_note)) $details .= "  |  Standort: ".$eq->location_note;
    $pdf->Cell(0, 5, $details, 'LRB', 1, 'L', true);

    $pdf->Ln(2);

    // ===== TWO COLUMN LAYOUT: IBN | ABNAHME =====
    $halfWidth = ($contentWidth / 2) - 3;
    $boxStartY = $pdf->GetY();

    // ----- LEFT COLUMN: INBETRIEBNAHME -----
    $pdf->SetXY($leftMargin, $boxStartY);
    $pdf->SetFillColor(240, 248, 255); // Light blue
    $pdf->SetFont('', 'B', $default_font_size);
    $pdf->Cell($halfWidth, 6, "INBETRIEBNAHME", 1, 1, 'C', true);

    $pdf->SetFont('', '', $default_font_size - 1);
    $pdf->SetX($leftMargin);

    // Erfolgt row
    $pdf->SetFillColor(255, 255, 255);
    $erfolgtIBN = $eq->commissioning_done ? "Ja" : "Nein";
    $pdf->Cell($halfWidth, 5, "Erfolgt: ".$erfolgtIBN, 'LR', 1, 'L');

    $pdf->SetX($leftMargin);
    if ($eq->commissioning_done) {
        // Show date
        $dateStr = $eq->commissioning_date ? dol_print_date($db->jdate($eq->commissioning_date), 'day') : '-';
        $pdf->Cell($halfWidth, 5, "Datum: ".$dateStr, 'LR', 1, 'L');
        $pdf->SetX($leftMargin);
        $pdf->Cell($halfWidth, 5, "", 'LRB', 1, 'L'); // Empty row for balance
    } else {
        // Show note
        $note = $eq->commissioning_note ?: '-';
        $pdf->Cell($halfWidth, 5, "Bemerkung:", 'LR', 1, 'L');
        $pdf->SetX($leftMargin);
        $pdf->MultiCell($halfWidth, 5, $note, 'LRB', 'L');
    }

    $leftEndY = $pdf->GetY();

    // ----- RIGHT COLUMN: ABNAHME -----
    $pdf->SetXY($leftMargin + $halfWidth + 6, $boxStartY);
    $pdf->SetFillColor(255, 250, 240); // Light orange
    $pdf->SetFont('', 'B', $default_font_size);
    $pdf->Cell($halfWidth, 6, "ABNAHME", 1, 1, 'C', true);

    $pdf->SetFont('', '', $default_font_size - 1);
    $pdf->SetX($leftMargin + $halfWidth + 6);

    // Erfolgt row
    $pdf->SetFillColor(255, 255, 255);
    $erfolgtAbn = $eq->acceptance_done ? "Ja" : "Nein";
    $pdf->Cell($halfWidth, 5, "Erfolgt: ".$erfolgtAbn, 'LR', 1, 'L');

    $pdf->SetX($leftMargin + $halfWidth + 6);
    if ($eq->acceptance_done) {
        // Show date
        $dateStr = $eq->acceptance_date ? dol_print_date($db->jdate($eq->acceptance_date), 'day') : '-';
        $pdf->Cell($halfWidth, 5, "Datum: ".$dateStr, 'LR', 1, 'L');

        // Mängelfrei
        $pdf->SetX($leftMargin + $halfWidth + 6);
        $maengelfrei = $eq->acceptance_defect_free ? "Ja" : "Nein";
        $pdf->Cell($halfWidth, 5, "Mängelfrei: ".$maengelfrei, 'LR', 1, 'L');

        // Note/Mängel
        $pdf->SetX($leftMargin + $halfWidth + 6);
        if (!$eq->acceptance_defect_free) {
            $pdf->SetFont('', 'I', $default_font_size - 1);
            $pdf->Cell($halfWidth, 5, "Mängel:", 'LR', 1, 'L');
            $pdf->SetX($leftMargin + $halfWidth + 6);
            $pdf->MultiCell($halfWidth, 5, $eq->acceptance_note ?: '-', 'LRB', 'L');
            $pdf->SetFont('', '', $default_font_size - 1);
        } else {
            if (!empty($eq->acceptance_note)) {
                $pdf->Cell($halfWidth, 5, "Bemerkung: ".$eq->acceptance_note, 'LRB', 1, 'L');
            } else {
                $pdf->Cell($halfWidth, 5, "", 'LRB', 1, 'L');
            }
        }
    } else {
        // Not done - empty rows
        $pdf->Cell($halfWidth, 5, "", 'LR', 1, 'L');
        $pdf->SetX($leftMargin + $halfWidth + 6);
        $pdf->Cell($halfWidth, 5, "", 'LRB', 1, 'L');
    }

    $rightEndY = $pdf->GetY();

    // Ensure both columns end at the same height
    $maxY = max($leftEndY, $rightEndY);
    $pdf->SetY($maxY + 2);

    // ===== ADDITIONAL OPTIONS ROW =====
    $pdf->SetFillColor(250, 250, 250);
    $pdf->SetFont('', '', $default_font_size - 1);
    $checkYes = "[X]";
    $checkNo = "[  ]";

    $instruction = $eq->instruction_done ? $checkYes : $checkNo;
    $testbook = $eq->testbook_handed ? $checkYes : $checkNo;

    $pdf->Cell($halfWidth, 5, $instruction." Einweisung erfolgt", 1, 0, 'L', true);
    $pdf->Cell(6, 5, "", 0, 0); // Spacer
    $pdf->Cell($halfWidth, 5, $testbook." Prüfbuch übergeben", 1, 1, 'L', true);

    $pdf->Ln(6);
}

// ========== SIGNATURE SECTION ==========
// Check if we need a new page for signatures
if ($pdf->GetY() > 220) {
    $pdf->AddPage();
}

$pdf->Ln(3);
$pdf->SetFont('', 'B', $default_font_size);
$pdf->Cell(0, 6, "UNTERSCHRIFTEN", 0, 1, 'L');
$pdf->Ln(2);

// Find customer signature from intervention
$signatureFile = null;
$signatureDir = $conf->ficheinter->dir_output.'/'.$fichinter->ref.'/signatures';
if (is_dir($signatureDir)) {
    $files = scandir($signatureDir);
    foreach ($files as $file) {
        if (preg_match('/^customer_signature.*\.png$/', $file)) {
            $signatureFile = $signatureDir.'/'.$file;
            break;
        }
    }
}

// Find technician signature from EquipmentManager
$techSignatureFile = null;
$techUserId = $fichinter->fk_user_valid ?: $fichinter->fk_user_author;
if ($techUserId) {
    $techFile = DOL_DATA_ROOT.'/equipmentmanager/signatures/user_'.$techUserId.'.png';
    if (file_exists($techFile)) {
        $techSignatureFile = $techFile;
    }
}

// Two-column signature boxes
$boxWidth = ($contentWidth / 2) - 5;
$boxHeight = 25;
$curY = $pdf->GetY();

// Technician signature (left)
$pdf->SetFont('', '', $default_font_size - 1);
$pdf->SetXY($leftMargin, $curY);
$pdf->Cell($boxWidth, 5, "Techniker:", 0, 1);
$pdf->Rect($leftMargin, $curY + 5, $boxWidth, $boxHeight);

if ($techSignatureFile) {
    $pdf->Image($techSignatureFile, $leftMargin + 2, $curY + 7, $boxWidth - 4, $boxHeight - 6, '', '', '', false, 300, '', false, false, 0, 'CM');
}

// Customer signature (right)
$pdf->SetXY($leftMargin + $boxWidth + 10, $curY);
$pdf->Cell($boxWidth, 5, "Auftraggeber:", 0, 1);
$pdf->Rect($leftMargin + $boxWidth + 10, $curY + 5, $boxWidth, $boxHeight);

if ($signatureFile && file_exists($signatureFile)) {
    $pdf->Image($signatureFile, $leftMargin + $boxWidth + 12, $curY + 7, $boxWidth - 4, $boxHeight - 6, '', '', '', false, 300, '', false, false, 0, 'CM');
}

// Date and place line
$pdf->SetY($curY + $boxHeight + 10);
$pdf->SetFont('', '', $default_font_size - 1);
$pdf->Cell(0, 5, "Ort, Datum: ________________________________________     ".dol_print_date(dol_now(), 'day'), 0, 1);

// Output PDF
$pdf->Output("Abnahmeprotokoll_".$fichinter->ref.".pdf", 'I');
