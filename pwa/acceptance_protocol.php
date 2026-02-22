<?php
/**
 * Acceptance Protocol PDF Generator (v4.5.2)
 * Generates acceptance protocol for service interventions
 * Two-column layout: Inbetriebnahme | Abnahme (equal height)
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

// ========== LOGO ==========
$posy = 15; // Start Y position
$logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
if ($mysoc->logo && file_exists($logo)) {
    $height = pdf_getHeightForLogo($logo);
    $pdf->Image($logo, $leftMargin, $posy, 0, $height);
    $posy += $height + 5;
} else {
    $posy += 5;
}

// Company name
$pdf->SetFont('', 'B', $default_font_size + 2);
$pdf->SetXY($leftMargin, $posy);
$pdf->Cell(0, 6, $outputlangs->convToOutputCharset($mysoc->name), 0, 1, 'L');
$posy += 8;

// ========== HEADER (gray box like checklist) ==========
$pdf->SetFont('', 'B', $default_font_size + 4);
$pdf->SetXY($leftMargin, $posy);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell($contentWidth, 10, "Inbetriebnahme- und Abnahmeprotokoll", 1, 1, 'C', true);
$posy += 12;

// Date (left) and Intervention ref (right)
$pdf->SetFont('', '', $default_font_size - 1);
$pdf->SetXY($leftMargin, $posy);
$pdf->Cell(50, 5, "Datum: ".dol_print_date(dol_now(), 'day'), 0, 0, 'L');
$pdf->Cell(0, 5, "Serviceauftrag: ".$fichinter->ref, 0, 1, 'R');
$posy += 7;

// ========== CUSTOMER / OBJECT ADDRESS (Two columns) ==========
$colWidth = ($contentWidth / 2) - 5;

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

$pdf->SetY(max($customerEndY, $objectEndY) + 8);

// ========== EQUIPMENT LIST ==========
$equipmentCount = count($equipmentList);
$equipmentIndex = 0;

foreach ($equipmentList as $eq) {
    $equipmentIndex++;

    // Check if we need a new page (need ~70mm for one equipment block)
    if ($pdf->GetY() > 190) {
        $pdf->AddPage();
    }

    $typeLabel = isset($typeLabels[$eq->equipment_type]) ? $typeLabels[$eq->equipment_type] : $eq->equipment_type;

    // ===== EQUIPMENT HEADER =====
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);

    // Equipment header (simple, like before)
    $pdf->SetFillColor(230, 230, 230);
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

    $pdf->SetLineWidth(0.2);

    $pdf->Ln(1);

    // ===== TWO COLUMN LAYOUT: IBN | ABNAHME (EQUAL HEIGHT) =====
    $halfWidth = ($contentWidth / 2) - 2;
    $boxStartY = $pdf->GetY();
    $rowHeight = 5;
    $headerHeight = 6;

    // Fixed height for both columns (4 rows + header)
    $fixedBoxHeight = $headerHeight + ($rowHeight * 4);

    // ----- LEFT COLUMN: INBETRIEBNAHME -----
    $pdf->SetXY($leftMargin, $boxStartY);
    $pdf->SetFont('', 'B', $default_font_size - 1);
    $pdf->Cell($halfWidth, $headerHeight, "Inbetriebnahme", 1, 1, 'L'); // With bottom border (underline)

    // Row 1: Erfolgt (label bold)
    $pdf->SetX($leftMargin);
    $erfolgtIBN = $eq->commissioning_done ? "Ja" : "Nein";
    $pdf->SetFont('', 'B', $default_font_size - 1);
    $pdf->Cell(18, $rowHeight, "Erfolgt:", 'L', 0, 'L');
    $pdf->SetFont('', '', $default_font_size - 1);
    $pdf->Cell($halfWidth - 18, $rowHeight, $erfolgtIBN, 'R', 1, 'L');

    // Row 2: Datum or Bemerkung
    $pdf->SetX($leftMargin);
    if ($eq->commissioning_done) {
        $dateStr = $eq->commissioning_date ? dol_print_date($db->jdate($eq->commissioning_date), 'day') : '-';
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->Cell(18, $rowHeight, "Datum:", 'L', 0, 'L');
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->Cell($halfWidth - 18, $rowHeight, $dateStr, 'R', 1, 'L');
    } else {
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->Cell(22, $rowHeight, "Bemerkung:", 'L', 0, 'L');
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->Cell($halfWidth - 22, $rowHeight, "", 'R', 1, 'L');
    }

    // Row 3: Note content or empty
    $pdf->SetX($leftMargin);
    $pdf->SetFont('', '', $default_font_size - 1);
    if ($eq->commissioning_done) {
        $pdf->Cell($halfWidth, $rowHeight, "", 'LR', 1, 'L');
    } else {
        $note = $eq->commissioning_note ?: '-';
        $pdf->Cell($halfWidth, $rowHeight, $note, 'LR', 1, 'L');
    }

    // Row 4: Empty (for equal height)
    $pdf->SetX($leftMargin);
    $pdf->Cell($halfWidth, $rowHeight, "", 'LRB', 1, 'L');

    // ----- RIGHT COLUMN: ABNAHME -----
    $pdf->SetXY($leftMargin + $halfWidth + 4, $boxStartY);
    $pdf->SetFont('', 'B', $default_font_size - 1);
    $pdf->Cell($halfWidth, $headerHeight, "Abnahme", 1, 1, 'L'); // With bottom border (underline)

    // Row 1: Erfolgt (label bold)
    $pdf->SetX($leftMargin + $halfWidth + 4);
    $erfolgtAbn = $eq->acceptance_done ? "Ja" : "Nein";
    $pdf->SetFont('', 'B', $default_font_size - 1);
    $pdf->Cell(18, $rowHeight, "Erfolgt:", 'L', 0, 'L');
    $pdf->SetFont('', '', $default_font_size - 1);
    $pdf->Cell($halfWidth - 18, $rowHeight, $erfolgtAbn, 'R', 1, 'L');

    // Row 2
    $pdf->SetX($leftMargin + $halfWidth + 4);
    if ($eq->acceptance_done) {
        // Abnahme erfolgreich - Datum anzeigen
        $dateStr = $eq->acceptance_date ? dol_print_date($db->jdate($eq->acceptance_date), 'day') : '-';
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->Cell(18, $rowHeight, "Datum:", 'L', 0, 'L');
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->Cell($halfWidth - 18, $rowHeight, $dateStr, 'R', 1, 'L');
    } else {
        // Abnahme nicht erfolgt wegen Mängel
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->Cell($halfWidth, $rowHeight, "Wesentliche Mängel:", 'LR', 1, 'L');
        $pdf->SetFont('', '', $default_font_size - 1);
    }

    // Row 3
    $pdf->SetX($leftMargin + $halfWidth + 4);
    if ($eq->acceptance_done) {
        // Optional: Bemerkung bei erfolgreicher Abnahme
        if (!empty($eq->acceptance_note)) {
            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->Cell(22, $rowHeight, "Bemerkung:", 'L', 0, 'L');
            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->Cell($halfWidth - 22, $rowHeight, $eq->acceptance_note, 'R', 1, 'L');
        } else {
            $pdf->Cell($halfWidth, $rowHeight, "", 'LR', 1, 'L');
        }
    } else {
        // Mängelbeschreibung
        $maengel = $eq->acceptance_note ?: '-';
        $pdf->Cell($halfWidth, $rowHeight, $maengel, 'LR', 1, 'L');
    }

    // Row 4
    $pdf->SetX($leftMargin + $halfWidth + 4);
    $pdf->Cell($halfWidth, $rowHeight, "", 'LRB', 1, 'L');

    // Move to after both columns
    $pdf->SetY($boxStartY + $fixedBoxHeight + 1);

    // ===== ADDITIONAL OPTIONS ROW =====
    $pdf->SetFont('', '', $default_font_size - 1);
    $checkYes = "[X]";
    $checkNo = "[  ]";

    $instruction = $eq->instruction_done ? $checkYes : $checkNo;
    $testbook = $eq->testbook_handed ? $checkYes : $checkNo;

    $pdf->Cell($halfWidth, 5, $instruction." Einweisung erfolgt", 1, 0, 'L');
    $pdf->Cell(4, 5, "", 0, 0); // Spacer
    $pdf->Cell($halfWidth, 5, $testbook." Prüfbuch übergeben", 1, 1, 'L');

    // Space and separator between equipment blocks
    if ($equipmentIndex < $equipmentCount) {
        $pdf->Ln(4);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($leftMargin, $pdf->GetY(), $leftMargin + $contentWidth, $pdf->GetY());
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->Ln(4);
    } else {
        $pdf->Ln(2);
    }
}

// ========== SIGNATURE SECTION (anchored at bottom) ==========
// Disable auto page break for signature section
$pdf->SetAutoPageBreak(false);

// A4 page = 297mm, we want signatures starting at ~240mm from top
$signatureStartY = 240;

// If content goes past signature area, add new page
if ($pdf->GetY() > $signatureStartY - 5) {
    $pdf->AddPage();
}

// Jump to fixed position at bottom
$pdf->SetY($signatureStartY);

// Find customer signature from intervention (filename: {date}_signature.png)
$signatureFile = null;
$signatureDir = $conf->ficheinter->dir_output.'/'.$fichinter->ref.'/signatures';
if (is_dir($signatureDir)) {
    $files = scandir($signatureDir);
    foreach ($files as $file) {
        // Match pattern: 20250221153045_signature.png
        if (preg_match('/_signature\.png$/', $file)) {
            $signatureFile = $signatureDir.'/'.$file;
            break;
        }
    }
}

// Find technician signature from EquipmentManager - use CURRENT logged in user
$techSignatureFile = null;
$techFile = DOL_DATA_ROOT.'/equipmentmanager/signatures/user_'.$user->id.'.png';
if (file_exists($techFile)) {
    $techSignatureFile = $techFile;
}

// Two-column signature boxes
$boxWidth = ($contentWidth / 2) - 5;
$boxHeight = 25;
$curY = $pdf->GetY();

// Technician signature (left)
$pdf->SetFont('', 'B', $default_font_size - 1);
$pdf->SetXY($leftMargin, $curY);
$pdf->Cell($boxWidth, 5, "Techniker: ".$user->getFullName($langs), 0, 1);
$pdf->SetLineWidth(0.3);
$pdf->Rect($leftMargin, $curY + 5, $boxWidth, $boxHeight);

if ($techSignatureFile) {
    $pdf->Image($techSignatureFile, $leftMargin + 2, $curY + 7, $boxWidth - 4, $boxHeight - 6, '', '', '', false, 300, '', false, false, 0, 'CM');
} else {
    // Show placeholder text if no signature
    $pdf->SetFont('', 'I', 7);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetXY($leftMargin + 2, $curY + 12);
    $pdf->Cell($boxWidth - 4, 5, "(Keine Unterschrift hinterlegt)", 0, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

// Get signer name from database (set when customer signs)
$signerName = '';
$sqlSigner = "SELECT online_sign_name FROM ".MAIN_DB_PREFIX."fichinter WHERE rowid = ".(int)$fichinter->id;
$resSigner = $db->query($sqlSigner);
if ($resSigner && $objSigner = $db->fetch_object($resSigner)) {
    $signerName = $objSigner->online_sign_name;
}

// Fallback: Try to get name from BILLING contact if no signer name
if (empty($signerName)) {
    $contacts = $fichinter->liste_contact(-1, 'external');
    if (is_array($contacts)) {
        foreach ($contacts as $contact) {
            if ($contact['code'] == 'BILLING' || $contact['code'] == 'CUSTOMER') {
                $signerName = trim($contact['firstname'].' '.$contact['lastname']);
                break;
            }
        }
    }
}

// Customer signature (right)
$pdf->SetFont('', 'B', $default_font_size - 1);
$pdf->SetXY($leftMargin + $boxWidth + 10, $curY);
$customerLabel = "Auftraggeber:";
if (!empty($signerName)) {
    $customerLabel .= " ".$signerName;
}
$pdf->Cell($boxWidth, 5, $customerLabel, 0, 1);
$pdf->Rect($leftMargin + $boxWidth + 10, $curY + 5, $boxWidth, $boxHeight);

if ($signatureFile && file_exists($signatureFile)) {
    $pdf->Image($signatureFile, $leftMargin + $boxWidth + 12, $curY + 7, $boxWidth - 4, $boxHeight - 6, '', '', '', false, 300, '', false, false, 0, 'CM');
} else {
    // Show placeholder text if no signature
    $pdf->SetFont('', 'I', 7);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetXY($leftMargin + $boxWidth + 12, $curY + 12);
    $pdf->Cell($boxWidth - 4, 5, "(Keine Unterschrift vorhanden)", 0, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

// Date and place line - use object address town
$ortText = '';
if ($objectAddress && !empty($objectAddress->town)) {
    $ortText = $objectAddress->town;
} elseif ($fichinter->thirdparty && !empty($fichinter->thirdparty->town)) {
    $ortText = $fichinter->thirdparty->town;
}
$pdf->SetY($curY + $boxHeight + 6);
$pdf->SetFont('', '', $default_font_size - 1);
$pdf->Cell(0, 5, $ortText.", ".dol_print_date(dol_now(), 'day'), 0, 1);

// Reset line settings to prevent stray lines
$pdf->SetLineWidth(0);
$pdf->SetDrawColor(255, 255, 255);

// Generate PDF content
$pdfFilename = "Abnahmeprotokoll_".$fichinter->ref.".pdf";

// Save PDF to documents directory
$docDir = $conf->ficheinter->dir_output.'/'.$fichinter->ref;
if (!is_dir($docDir)) {
    dol_mkdir($docDir);
}
$pdfPath = $docDir.'/'.$pdfFilename;

// Get PDF as string, save to file, then output
$pdfContent = $pdf->Output($pdfFilename, 'S'); // Get as string
file_put_contents($pdfPath, $pdfContent);

// Index the file in Dolibarr
dol_include_once('/core/lib/files.lib.php');
addFileIntoDatabaseIndex($docDir, basename($pdfPath), '', 'generated', 0);

// Output PDF to browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$pdfFilename.'"');
header('Content-Length: '.strlen($pdfContent));
echo $pdfContent;
