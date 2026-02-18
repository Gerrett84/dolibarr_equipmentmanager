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
$sql .= " d.acceptance_done, d.acceptance_date, d.acceptance_note";
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
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

$default_font_size = 10;
$pageWidth = $pdf->getPageWidth();
$contentWidth = $pageWidth - 30; // margins

// Header
$pdf->SetFont('', 'B', 16);
$pdf->Cell(0, 10, "Abnahmeprotokoll", 0, 1, 'C');
$pdf->SetFont('', '', $default_font_size);
$pdf->Cell(0, 5, "Serviceauftrag: ".$fichinter->ref, 0, 1, 'C');
$pdf->Ln(5);

// Customer info
$pdf->SetFont('', 'B', $default_font_size + 1);
$pdf->Cell(0, 6, "Auftraggeber", 0, 1);
$pdf->SetFont('', '', $default_font_size);
if ($fichinter->thirdparty) {
    $pdf->MultiCell(0, 5, $fichinter->thirdparty->name, 0, 'L');
    if ($fichinter->thirdparty->address) {
        $pdf->MultiCell(0, 5, $fichinter->thirdparty->address, 0, 'L');
    }
    if ($fichinter->thirdparty->zip || $fichinter->thirdparty->town) {
        $pdf->Cell(0, 5, trim($fichinter->thirdparty->zip.' '.$fichinter->thirdparty->town), 0, 1);
    }
}
$pdf->Ln(3);

// Object address
if ($objectAddress) {
    $pdf->SetFont('', 'B', $default_font_size + 1);
    $pdf->Cell(0, 6, "Objektadresse", 0, 1);
    $pdf->SetFont('', '', $default_font_size);

    $addressParts = [];
    if (!empty($objectAddress->address)) $addressParts[] = $objectAddress->address;
    if (!empty($objectAddress->zip) || !empty($objectAddress->town)) {
        $addressParts[] = trim($objectAddress->zip.' '.$objectAddress->town);
    }
    $pdf->MultiCell(0, 5, implode("\n", $addressParts), 0, 'L');
    $pdf->Ln(3);
}

// Equipment list with commissioning/acceptance status
$pdf->SetFont('', 'B', $default_font_size + 1);
$pdf->Cell(0, 6, "Anlagen", 0, 1);
$pdf->Ln(2);

foreach ($equipmentList as $eq) {
    // Equipment header
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('', 'B', $default_font_size);
    $pdf->Cell(0, 7, $eq->equipment_number." - ".$eq->label, 1, 1, 'L', true);

    // Equipment details
    $pdf->SetFont('', '', $default_font_size - 1);
    $typeLabel = isset($typeLabels[$eq->equipment_type]) ? $typeLabels[$eq->equipment_type] : $eq->equipment_type;

    $details = [];
    $details[] = "Typ: ".$typeLabel;
    if (!empty($eq->serial_number)) $details[] = "S/N: ".$eq->serial_number;
    if (!empty($eq->manufacturer)) $details[] = "Hersteller: ".$eq->manufacturer;
    if (!empty($eq->location_note)) $details[] = "Standort: ".$eq->location_note;

    $pdf->Cell(0, 5, implode(" | ", $details), 0, 1);
    $pdf->Ln(2);

    // Commissioning status
    $pdf->SetFont('', 'B', $default_font_size - 1);
    $pdf->Cell(50, 5, "Inbetriebnahme:", 0, 0);
    $pdf->SetFont('', '', $default_font_size - 1);
    if ($eq->commissioning_done) {
        $pdf->Cell(0, 5, "Ja - ".dol_print_date($db->jdate($eq->commissioning_date), 'day'), 0, 1);
    } else {
        $note = $eq->commissioning_note ?: '-';
        $pdf->Cell(0, 5, "Nein - ".$note, 0, 1);
    }

    // Acceptance status
    $pdf->SetFont('', 'B', $default_font_size - 1);
    $pdf->Cell(50, 5, "Abnahme:", 0, 0);
    $pdf->SetFont('', '', $default_font_size - 1);
    if ($eq->acceptance_done) {
        $pdf->Cell(0, 5, "Ja - ".dol_print_date($db->jdate($eq->acceptance_date), 'day'), 0, 1);
    } else {
        $note = $eq->acceptance_note ?: '-';
        $pdf->Cell(0, 5, "Nein - ".$note, 0, 1);
    }

    $pdf->Ln(4);
}

// Signature section
$pdf->Ln(10);
$pdf->SetFont('', 'B', $default_font_size);
$pdf->Cell(0, 6, "Unterschriften", 0, 1);
$pdf->Ln(3);

// Check if intervention has customer signature
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

// Two-column signature boxes
$boxWidth = ($contentWidth / 2) - 5;
$boxHeight = 35;
$curY = $pdf->GetY();

// Technician signature (left)
$pdf->SetFont('', '', $default_font_size - 1);
$pdf->SetXY(15, $curY);
$pdf->Cell($boxWidth, 5, "Techniker:", 0, 1);
$pdf->SetXY(15, $curY + 5);
$pdf->Rect(15, $curY + 5, $boxWidth, $boxHeight);

// Add technician signature if exists
$techSignatureFile = DOL_DATA_ROOT.'/equipmentmanager/signatures/user_'.$fichinter->fk_user_valid.'.png';
if (!file_exists($techSignatureFile)) {
    $techSignatureFile = DOL_DATA_ROOT.'/equipmentmanager/signatures/user_'.$fichinter->fk_user_author.'.png';
}
if (file_exists($techSignatureFile)) {
    $pdf->Image($techSignatureFile, 17, $curY + 7, $boxWidth - 4, $boxHeight - 8, '', '', '', false, 300, '', false, false, 0, 'CM');
}

// Customer signature (right)
$pdf->SetXY(15 + $boxWidth + 10, $curY);
$pdf->Cell($boxWidth, 5, "Kunde:", 0, 1);
$pdf->Rect(15 + $boxWidth + 10, $curY + 5, $boxWidth, $boxHeight);

// Add customer signature if exists
if ($signatureFile && file_exists($signatureFile)) {
    $pdf->Image($signatureFile, 17 + $boxWidth + 10, $curY + 7, $boxWidth - 4, $boxHeight - 8, '', '', '', false, 300, '', false, false, 0, 'CM');
}

// Date and place
$pdf->SetY($curY + $boxHeight + 10);
$pdf->SetFont('', '', $default_font_size - 1);
$pdf->Cell(0, 5, "Ort, Datum: _________________________, ".dol_print_date(dol_now(), 'day'), 0, 1);

// Output PDF
$pdf->Output("Abnahmeprotokoll_".$fichinter->ref.".pdf", 'I');
