<?php
/**
 * Acceptance Protocol PDF Generator (v4.5)
 * Generates acceptance protocol for service interventions with commissioning/acceptance data
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

// Get equipment with acceptance data
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
$sql .= " AND l.link_type = 'service'"; // Only service entries
$sql .= " AND (d.commissioning_done = 1 OR d.acceptance_done = 1)"; // Only with commissioning or acceptance
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
    die('Keine Anlagen mit Inbetriebnahme/Abnahme gefunden');
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
$pdf->SetAutoPageBreak(true, 30);
$pdf->AddPage();

$default_font_size = 10;
$pageWidth = $pdf->getPageWidth();
$contentWidth = $pageWidth - 30; // margins

// Header
$pdf->SetFont('', 'B', 18);
$pdf->Cell(0, 12, "ABNAHMEPROTOKOLL", 0, 1, 'C');
$pdf->SetFont('', '', $default_font_size);
$pdf->Cell(0, 5, "Serviceauftrag: ".$fichinter->ref, 0, 1, 'C');
$pdf->Ln(8);

// Two column layout for customer and object address
$colWidth = ($contentWidth / 2) - 5;
$startY = $pdf->GetY();

// Customer info (left)
$pdf->SetFont('', 'B', $default_font_size);
$pdf->Cell($colWidth, 6, "Auftraggeber:", 0, 0);
$pdf->SetX(15 + $colWidth + 10);
$pdf->Cell($colWidth, 6, "Objektadresse:", 0, 1);

$pdf->SetFont('', '', $default_font_size - 1);
$customerY = $pdf->GetY();

// Customer details
$pdf->SetXY(15, $customerY);
if ($fichinter->thirdparty) {
    $pdf->MultiCell($colWidth, 4, $fichinter->thirdparty->name, 0, 'L');
    if ($fichinter->thirdparty->address) {
        $pdf->SetX(15);
        $pdf->MultiCell($colWidth, 4, $fichinter->thirdparty->address, 0, 'L');
    }
    if ($fichinter->thirdparty->zip || $fichinter->thirdparty->town) {
        $pdf->SetX(15);
        $pdf->Cell($colWidth, 4, trim($fichinter->thirdparty->zip.' '.$fichinter->thirdparty->town), 0, 1);
    }
}
$customerEndY = $pdf->GetY();

// Object address (right)
$pdf->SetXY(15 + $colWidth + 10, $customerY);
if ($objectAddress) {
    $pdf->MultiCell($colWidth, 4, $objectAddress->address ?: '', 0, 'L');
    if ($objectAddress->zip || $objectAddress->town) {
        $pdf->SetX(15 + $colWidth + 10);
        $pdf->Cell($colWidth, 4, trim($objectAddress->zip.' '.$objectAddress->town), 0, 1);
    }
}
$objectEndY = $pdf->GetY();

$pdf->SetY(max($customerEndY, $objectEndY) + 5);

// Equipment list
$pdf->SetFont('', 'B', $default_font_size + 1);
$pdf->Cell(0, 7, "Anlagen", 0, 1);
$pdf->Ln(2);

foreach ($equipmentList as $eq) {
    // Check if we need a new page
    if ($pdf->GetY() > 220) {
        $pdf->AddPage();
    }

    // Equipment header box
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetFont('', 'B', $default_font_size);
    $pdf->Cell(0, 7, $eq->equipment_number." - ".$eq->label, 1, 1, 'L', true);

    // Equipment details line
    $pdf->SetFont('', '', $default_font_size - 1);
    $typeLabel = isset($typeLabels[$eq->equipment_type]) ? $typeLabels[$eq->equipment_type] : $eq->equipment_type;

    $details = [];
    $details[] = "Typ: ".$typeLabel;
    if (!empty($eq->serial_number)) $details[] = "S/N: ".$eq->serial_number;
    if (!empty($eq->manufacturer)) $details[] = "Hersteller: ".$eq->manufacturer;
    if (!empty($eq->location_note)) $details[] = "Standort: ".$eq->location_note;

    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(0, 5, implode("  |  ", $details), 'LR', 1, 'L', true);

    // Commissioning section
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('', 'B', $default_font_size - 1);
    $pdf->Cell(45, 6, "Inbetriebnahme:", 'L', 0);
    $pdf->SetFont('', '', $default_font_size - 1);

    if ($eq->commissioning_done) {
        $pdf->Cell(20, 6, "Erfolgt: Ja", 0, 0);
        $pdf->Cell(0, 6, "am: ".dol_print_date($db->jdate($eq->commissioning_date), 'day'), 'R', 1);
    } else {
        $pdf->Cell(20, 6, "Erfolgt: Nein", 0, 0);
        $note = $eq->commissioning_note ?: '-';
        $pdf->Cell(0, 6, "Bemerkung: ".$note, 'R', 1);
    }

    // Acceptance section
    $pdf->SetFont('', 'B', $default_font_size - 1);
    $pdf->Cell(45, 6, "Abnahme:", 'L', 0);
    $pdf->SetFont('', '', $default_font_size - 1);

    if ($eq->acceptance_done) {
        $pdf->Cell(20, 6, "Erfolgt: Ja", 0, 0);
        $pdf->Cell(40, 6, "am: ".dol_print_date($db->jdate($eq->acceptance_date), 'day'), 0, 0);

        if ($eq->acceptance_defect_free) {
            $pdf->Cell(0, 6, "Mängelfrei: Ja", 'R', 1);
            if (!empty($eq->acceptance_note)) {
                $pdf->Cell(45, 5, "", 'L', 0);
                $pdf->Cell(0, 5, "Bemerkung: ".$eq->acceptance_note, 'R', 1);
            }
        } else {
            $pdf->Cell(0, 6, "Mängelfrei: Nein", 'R', 1);
            $pdf->SetFont('', 'I', $default_font_size - 1);
            $pdf->Cell(45, 5, "", 'L', 0);
            $pdf->MultiCell(0, 5, "Wesentliche Mängel: ".($eq->acceptance_note ?: '-'), 'R', 'L');
            $pdf->SetFont('', '', $default_font_size - 1);
        }
    } else {
        $pdf->Cell(0, 6, "Erfolgt: Nein", 'R', 1);
    }

    // Instruction & Testbook
    $pdf->Cell(45, 6, "", 'LB', 0);
    $instruction = $eq->instruction_done ? "Ja" : "Nein";
    $testbook = $eq->testbook_handed ? "Ja" : "Nein";
    $pdf->Cell(0, 6, "Einweisung: ".$instruction."  |  Prüfbuch übergeben: ".$testbook, 'RB', 1);

    $pdf->Ln(4);
}

// Signature section
$pdf->Ln(5);

// Check if we need a new page for signatures
if ($pdf->GetY() > 230) {
    $pdf->AddPage();
}

$pdf->SetFont('', 'B', $default_font_size);
$pdf->Cell(0, 7, "Unterschriften", 0, 1);
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
// Try user who validated, then author
$techUserId = $fichinter->fk_user_valid ?: $fichinter->fk_user_author;
if ($techUserId) {
    $techFile = DOL_DATA_ROOT.'/equipmentmanager/signatures/user_'.$techUserId.'.png';
    if (file_exists($techFile)) {
        $techSignatureFile = $techFile;
    }
}

// Two-column signature boxes
$boxWidth = ($contentWidth / 2) - 5;
$boxHeight = 30;
$curY = $pdf->GetY();

// Technician signature (left)
$pdf->SetFont('', '', $default_font_size - 1);
$pdf->SetXY(15, $curY);
$pdf->Cell($boxWidth, 5, "Techniker:", 0, 1);
$pdf->Rect(15, $curY + 5, $boxWidth, $boxHeight);

if ($techSignatureFile) {
    $pdf->Image($techSignatureFile, 17, $curY + 7, $boxWidth - 4, $boxHeight - 8, '', '', '', false, 300, '', false, false, 0, 'CM');
}

// Customer signature (right)
$pdf->SetXY(15 + $boxWidth + 10, $curY);
$pdf->Cell($boxWidth, 5, "Auftraggeber:", 0, 1);
$pdf->Rect(15 + $boxWidth + 10, $curY + 5, $boxWidth, $boxHeight);

if ($signatureFile && file_exists($signatureFile)) {
    $pdf->Image($signatureFile, 17 + $boxWidth + 10, $curY + 7, $boxWidth - 4, $boxHeight - 8, '', '', '', false, 300, '', false, false, 0, 'CM');
}

// Date and place line
$pdf->SetY($curY + $boxHeight + 10);
$pdf->SetFont('', '', $default_font_size - 1);
$pdf->Cell(0, 5, "Ort, Datum: ________________________________________     ".dol_print_date(dol_now(), 'day'), 0, 1);

// Output PDF
$pdf->Output("Abnahmeprotokoll_".$fichinter->ref.".pdf", 'I');
