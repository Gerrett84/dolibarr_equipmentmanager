<?php
/* Copyright (C) 2024 Equipment Manager
 * PDF Template for Fichinter with Equipment Details v1.6.1
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/fichinter/modules_fichinter.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/equipmentmanager/class/equipment.class.php');
dol_include_once('/equipmentmanager/class/interventiondetail.class.php');
dol_include_once('/equipmentmanager/class/interventionmaterial.class.php');

/**
 * Class to generate PDF for Fichinter with Equipment Manager details
 */
class pdf_equipmentmanager extends ModelePDFFicheinter
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string model name
     */
    public $name;

    /**
     * @var string model description (short)
     */
    public $description;

    /**
     * @var int     Save the name of generated file as the main doc when generating a doc with this template
     */
    public $update_main_doc_field;

    /**
     * @var string document type
     */
    public $type;

    /**
     * Dolibarr version of the loaded document
     * @var string
     */
    public $version = 'dolibarr';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $conf, $langs, $mysoc;

        $this->db = $db;
        $this->name = "equipmentmanager";
        $this->description = "Equipment Manager PDF Template";
        $this->update_main_doc_field = 1;
        $this->type = 'pdf';

        // Page format - use pdf_getFormat() like core templates
        $formatarray = pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        $this->page_hauteur = $formatarray['height'];
        $this->format = array($this->page_largeur, $this->page_hauteur);

        // Page margins
        $this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
        $this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
        $this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
        $this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
        $this->corner_radius = getDolGlobalInt('MAIN_PDF_FRAME_CORNER_RADIUS', 0);

        $this->option_logo = 1;
        $this->option_multilang = 1;
        $this->option_freetext = 1;
        $this->option_draft_watermark = 1;

        $this->franchise = !empty($mysoc->tva_assuj);

        $this->tva = array();
        $this->localtax1 = array();
        $this->localtax2 = array();
        $this->atleastonediscount = 0;

        // Get source company (emetteur)
        if ($mysoc === null) {
            dol_syslog(get_class($this).'::__construct() Global $mysoc should not be null.', LOG_ERR);
            return;
        }
        $this->emetteur = $mysoc;
        if (empty($this->emetteur->country_code)) {
            $this->emetteur->country_code = substr($langs->defaultlang, -2);
        }
    }

    /**
     * Write the PDF file
     *
     * @param Fichinter $object Object fichinter
     * @param Translate $outputlangs Lang object for output language
     * @param string $srctemplatepath Full path of source filename for generator using a template file
     * @param int $hidedetails Do not show line details
     * @param int $hidedesc Do not show desc
     * @param int $hideref Do not show ref
     * @return int 1=OK, 0=KO
     */
    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $user, $langs, $conf, $mysoc, $db, $hookmanager;

        if (!is_object($outputlangs)) {
            $outputlangs = $langs;
        }

        $outputlangs->loadLangs(array("main", "dict", "companies", "interventions", "equipmentmanager@equipmentmanager"));

        // Definition of $dir and $file
        if ($object->specimen) {
            $dir = $conf->ficheinter->dir_output;
            $file = $dir."/SPECIMEN.pdf";
        } else {
            $objectref = dol_sanitizeFileName($object->ref);
            $dir = $conf->ficheinter->dir_output."/".$objectref;
            $file = $dir."/".$objectref.".pdf";
        }

        if (!file_exists($dir)) {
            if (dol_mkdir($dir) < 0) {
                $this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
                return 0;
            }
        }

        if (file_exists($dir)) {
            // Add pdfgeneration hook
            if (!is_object($hookmanager)) {
                include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
                $hookmanager = new HookManager($this->db);
            }
            $hookmanager->initHooks(array('pdfgeneration'));
            $parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
            global $action;
            $reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);

            // Create PDF
            $pdf = pdf_getInstance($this->format);
            $default_font_size = pdf_getPDFFontSize($outputlangs);
            $heightforinfotot = 40;
            $heightforfreetext = (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT) ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : 5);
            $heightforfooter = $this->marge_basse + 8;

            if (class_exists('TCPDF')) {
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
            }
            $pdf->SetFont(pdf_getPDFFont($outputlangs));

            $pdf->Open();
            $pagenb = 0;
            $pdf->SetDrawColor(128, 128, 128);

            $pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
            $pdf->SetSubject($outputlangs->transnoentities("Intervention"));
            $pdf->SetCreator("Dolibarr ".DOL_VERSION);
            $pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
            $pdf->SetKeywords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("Intervention"));
            if (!empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) {
                $pdf->SetCompression(false);
            }

            $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
            $pdf->SetAutoPageBreak(1, 0);

            // New page
            $pdf->AddPage();
            if (!empty($tplidx)) {
                $pdf->useTemplate($tplidx);
            }
            $pagenb++;
            $top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs);
            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->SetTextColor(0, 0, 0);

            $tab_top = 90;
            $tab_top_newpage = (!empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD) ? 10 : 50);
            $tab_height = 130;
            $tab_height_newpage = 150;

            // Display notes
            $notetoshow = empty($object->note_public) ? '' : $object->note_public;
            if ($notetoshow) {
                $substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
                complete_substitutions_array($substitutionarray, $outputlangs, $object);
                $notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
                $notetoshow = $outputlangs->convToOutputCharset($notetoshow);

                $tab_top = 88;

                $pdf->SetFont('', '', $default_font_size - 1);
                $pdf->writeHTMLCell(190, 3, $this->marge_gauche, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
                $nexY = $pdf->GetY();
                $height_note = $nexY - $tab_top;

                $pdf->SetDrawColor(192, 192, 192);
                $pdf->Rect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_note + 1);

                $tab_height = $tab_height - $height_note;
                $tab_top = $nexY + 6;
            } else {
                $height_note = 0;
            }

            $iniY = $tab_top + 7;
            $curY = $tab_top + 7;
            $nexY = $tab_top + 7;

            // Get linked equipments
            $sql = "SELECT DISTINCT fk_equipment FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
            $sql .= " WHERE fk_intervention = ".(int)$object->id;
            $sql .= " ORDER BY rowid ASC";

            $resql = $this->db->query($sql);
            $equipment_list = array();

            if ($resql) {
                $num = $this->db->num_rows($resql);
                for ($i = 0; $i < $num; $i++) {
                    $obj = $this->db->fetch_object($resql);
                    $equipment = new Equipment($this->db);
                    if ($equipment->fetch($obj->fk_equipment) > 0) {
                        $equipment_list[] = $equipment;
                    }
                }
                $this->db->free($resql);
            }

            // Display equipment sections
            if (count($equipment_list) > 0) {
                $total_material = 0;
                $total_duration = 0;
                $equipment_count = count($equipment_list);

                foreach ($equipment_list as $index => $equipment) {
                    $is_first = ($index === 0);
                    $is_last = ($index === $equipment_count - 1);

                    // Load equipment details
                    $detail = new InterventionDetail($this->db);
                    $detail->fetchByInterventionEquipment($object->id, $equipment->id);

                    // Load materials
                    $materials = InterventionMaterial::fetchAllForEquipment($this->db, $object->id, $equipment->id);

                    // Calculate material total for this equipment
                    $equipment_material_total = InterventionMaterial::getTotalForEquipment($this->db, $object->id, $equipment->id);
                    $total_material += $equipment_material_total;

                    // Add work duration (exclude maintenance equipment - user requested: no time for maintenance)
                    if ($detail->work_duration > 0 && empty($equipment->maintenance_month)) {
                        $total_duration += $detail->work_duration;
                    }

                    // Estimate minimum space needed for this equipment section
                    $estimated_height = 15; // Base: title + equipment info
                    if ($detail->work_done) $estimated_height += 15;
                    if ($detail->issues_found) $estimated_height += 15;
                    if ($detail->recommendations) $estimated_height += 15;
                    if ($detail->notes) $estimated_height += 10;
                    if (count($materials) > 0) {
                        $estimated_height += 10 + (count($materials) * 5); // Material table header + rows
                    }

                    // Check if equipment section would fit on current page
                    // If not enough space (less than estimated height + 20mm safety margin), start new page
                    $available_space = 280 - $pdf->GetY(); // Page height is ~297mm, footer at ~280mm
                    if ($available_space < $estimated_height + 20) {
                        $pdf->AddPage();
                        $pagenb++;
                        $curY = $tab_top_newpage;
                    }

                    // Render equipment section
                    $curY = $this->_renderEquipmentSection($pdf, $equipment, $detail, $materials, $equipment_material_total, $curY, $outputlangs, $default_font_size, $is_first, $is_last, $total_duration);
                }

                // Add summary table after all equipment sections
                if ($total_duration > 0) {
                    $curY = $pdf->GetY() + 5;

                    // Calculate duration text
                    $hours = floor($total_duration / 60);
                    $minutes = $total_duration % 60;
                    $duration_text = $hours."h";
                    if ($minutes > 0) {
                        $duration_text .= " ".$minutes."min";
                    }

                    // Summary in full-width table - same font size as description
                    $pdf->SetFont('', '', $default_font_size - 1);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetDrawColor(0, 0, 0);

                    // Draw full-width table with border
                    $leftMargin = $this->marge_gauche;
                    $rightMargin = $this->marge_droite;
                    $pageWidth = $this->page_largeur;
                    $summaryWidth = $pageWidth - $leftMargin - $rightMargin;

                    // Set cell padding for summary table
                    $pdf->SetCellPadding(1);

                    $pdf->SetXY($leftMargin, $curY);
                    $pdf->Cell($summaryWidth, 5, "Gesamtdauer: ".$duration_text, 1, 1, 'R');

                    // Reset cell padding
                    $pdf->SetCellPadding(0);

                    $curY = $pdf->GetY();
                }
            }

            // Signature section
            $curY = $pdf->GetY() + 10;
            if ($curY > 230) {
                $pdf->AddPage();
                $pagenb++;
                $curY = $tab_top_newpage;
            }

            $this->_renderSignatures($pdf, $object, $curY, $outputlangs, $default_font_size);

            // Footer
            $this->_pagefoot($pdf, $object, $outputlangs);
            if (method_exists($pdf, 'AliasNbPages')) {
                $pdf->AliasNbPages();
            }

            $pdf->Close();
            $pdf->Output($file, 'F');

            // Add pdfgeneration hook
            $hookmanager->initHooks(array('pdfgeneration'));
            $parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
            global $action;
            $reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);

            if (!empty($conf->global->MAIN_UMASK)) {
                @chmod($file, octdec($conf->global->MAIN_UMASK));
            }

            $this->result = array('fullpath' => $file);

            return 1;
        } else {
            $this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
            return 0;
        }
    }

    /**
     * Show page header
     *
     * @param TCPDF $pdf PDF object
     * @param Fichinter $object Object fichinter
     * @param int $showaddress Show address (1=yes, 0=no)
     * @param Translate $outputlangs Output language object
     * @return int Top position after header
     */
    protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
    {
        global $conf, $langs, $mysoc;

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

        $pdf->SetTextColor(0, 0, 60);
        $pdf->SetFont('', 'B', $default_font_size + 3);

        $posy = $this->marge_haute;
        $posx = $this->page_largeur - $this->marge_droite - 100;

        $pdf->SetXY($this->marge_gauche, $posy);

        // Logo (moderately increased size)
        $logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
        if ($mysoc->logo) {
            if (is_readable($logo)) {
                $height = pdf_getHeightForLogo($logo);
                $pdf->Image($logo, $this->marge_gauche, $posy, 0, $height * 1.3);  // Increased logo size by 30%
            } else {
                $pdf->SetTextColor(200, 0, 0);
                $pdf->SetFont('', 'B', $default_font_size - 2);
                $pdf->MultiCell(100, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
                $pdf->MultiCell(100, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
            }
        } else {
            $pdf->MultiCell(100, 4, $outputlangs->convToOutputCharset($mysoc->name), 0, 'L');
        }

        // Title
        $pdf->SetFont('', 'B', $default_font_size + 3);
        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->MultiCell(100, 4, "Serviceauftrag - Arbeitsbericht", '', 'R');

        $pdf->SetFont('', '', $default_font_size);
        $posy += 5;
        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->MultiCell(100, 4, $outputlangs->transnoentities("Ref")." : ".$outputlangs->convToOutputCharset($object->ref), '', 'R');

        // Date
        $posy += 4;
        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->MultiCell(100, 4, $outputlangs->transnoentities("Date")." : ".dol_print_date($object->dateo, 'day', false, $outputlangs, true), '', 'R');

        // Customer number (get from thirdparty)
        if ($object->socid > 0) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $soc = new Societe($this->db);
            $soc->fetch($object->socid);

            if (!empty($soc->code_client)) {
                $posy += 4;
                $pdf->SetXY($posx, $posy);
                $pdf->SetTextColor(0, 0, 60);
                $pdf->MultiCell(100, 4, $outputlangs->transnoentities("CustomerCode")." : ".$soc->code_client, '', 'R');
            }
        }

        if ($showaddress) {
            // Left side: Customer - with background and phone/email
            $posy = 42;
            $posx = $this->marge_gauche;

            if ($object->socid > 0) {
                require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
                $soc = new Societe($this->db);
                $soc->fetch($object->socid);

                $pdf->SetXY($posx, $posy);
                $pdf->SetFillColor(230, 230, 230);
                $pdf->SetDrawColor(230, 230, 230);
                $pdf->Rect($posx, $posy, 82, 50, 'F'); // Larger box for customer + object address

                // Customer name
                $pdf->SetXY($posx + 2, $posy + 2);
                $pdf->SetFont('', 'B', $default_font_size);
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->MultiCell(78, 4, $outputlangs->convToOutputCharset($soc->name), 0, 'L');

                // Customer address
                $pdf->SetFont('', '', $default_font_size - 1);
                $curY = $pdf->GetY();
                if ($soc->address) {
                    $pdf->SetXY($posx + 2, $curY);
                    $pdf->MultiCell(78, 3, $outputlangs->convToOutputCharset($soc->address), 0, 'L');
                    $curY = $pdf->GetY();
                }

                $pdf->SetXY($posx + 2, $curY);
                $pdf->MultiCell(78, 3, $soc->zip.' '.$soc->town, 0, 'L');
                $curY = $pdf->GetY();

                // Phone
                if ($soc->phone) {
                    $pdf->SetXY($posx + 2, $curY);
                    $pdf->MultiCell(78, 3, $outputlangs->transnoentities("Phone").": ".$soc->phone, 0, 'L');
                    $curY = $pdf->GetY();
                }

                // Email
                if ($soc->email) {
                    $pdf->SetXY($posx + 2, $curY);
                    $pdf->MultiCell(78, 3, $outputlangs->transnoentities("Email").": ".$soc->email, 0, 'L');
                    $curY = $pdf->GetY();
                }

                // Object/Site address - get from equipment's fk_address (contact/socpeople)
                $pdf->SetFont('', '', $default_font_size - 2);
                $objectAddr = '';

                // Get object address from first equipment's fk_address
                $sql_addr = "SELECT DISTINCT e.fk_address FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link l";
                $sql_addr .= " INNER JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e ON l.fk_equipment = e.rowid";
                $sql_addr .= " WHERE l.fk_intervention = ".(int)$object->id;
                $sql_addr .= " AND e.fk_address IS NOT NULL";
                $sql_addr .= " ORDER BY l.rowid ASC LIMIT 1";

                $resql_addr = $this->db->query($sql_addr);
                if ($resql_addr && $this->db->num_rows($resql_addr) > 0) {
                    $obj_addr = $this->db->fetch_object($resql_addr);
                    if ($obj_addr->fk_address > 0) {
                        // Load contact details from socpeople
                        require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
                        $contact = new Contact($this->db);
                        if ($contact->fetch($obj_addr->fk_address) > 0) {
                            $objectAddr = '';
                            if ($contact->lastname || $contact->firstname) {
                                $objectAddr .= trim($contact->firstname.' '.$contact->lastname)."\n";
                            }
                            if ($contact->address) {
                                $objectAddr .= $contact->address."\n";
                            }
                            if ($contact->zip || $contact->town) {
                                $objectAddr .= trim($contact->zip.' '.$contact->town);
                            }
                            $objectAddr = trim($objectAddr);
                        }
                    }
                    $this->db->free($resql_addr);
                }

                // Only display if we have an object address
                if (!empty($objectAddr)) {
                    $curY += 2;
                    $pdf->SetXY($posx + 2, $curY);
                    $pdf->SetFont('', 'B', $default_font_size - 2);
                    $pdf->MultiCell(78, 3, "Objektadresse:", 0, 'L');
                    $curY = $pdf->GetY();

                    $pdf->SetFont('', '', $default_font_size - 2);
                    $pdf->SetXY($posx + 2, $curY);
                    $pdf->MultiCell(78, 3, $outputlangs->convToOutputCharset($objectAddr), 0, 'L');
                }
            }

            // Right side: Company - with border only
            // Position at right edge with same margin as description table
            $posx = $this->page_largeur - $this->marge_droite - 82;
            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->SetXY($posx, $posy);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($posx, $posy, 82, 50); // Increased height for phone/email

            // Company name
            $pdf->SetXY($posx + 2, $posy + 2);
            $pdf->SetFont('', 'B', $default_font_size);
            $pdf->MultiCell(78, 4, $outputlangs->convToOutputCharset($mysoc->name), 0, 'L');

            // Company address
            $pdf->SetFont('', '', $default_font_size - 1);
            $curY = $pdf->GetY();
            if ($mysoc->address) {
                $pdf->SetXY($posx + 2, $curY);
                $pdf->MultiCell(78, 3, $outputlangs->convToOutputCharset($mysoc->address), 0, 'L');
                $curY = $pdf->GetY();
            }

            $pdf->SetXY($posx + 2, $curY);
            $pdf->MultiCell(78, 3, $mysoc->zip.' '.$mysoc->town, 0, 'L');
            $curY = $pdf->GetY();

            // Phone
            if ($mysoc->phone) {
                $pdf->SetXY($posx + 2, $curY);
                $pdf->MultiCell(78, 3, $outputlangs->transnoentities("Phone").": ".$mysoc->phone, 0, 'L');
                $curY = $pdf->GetY();
            }

            // Email
            if ($mysoc->email) {
                $pdf->SetXY($posx + 2, $curY);
                $pdf->MultiCell(78, 3, $outputlangs->transnoentities("Email").": ".$mysoc->email, 0, 'L');
                $curY = $pdf->GetY();
            }
        }

        return $posy + 55; // Space for larger customer box (50mm) with object address
    }

    /**
     * Render equipment section
     *
     * @param TCPDF $pdf PDF object
     * @param Equipment $equipment Equipment object
     * @param InterventionDetail $detail Detail object
     * @param array $materials Array of material objects
     * @param float $material_total Material total for this equipment
     * @param float $curY Current Y position
     * @param Translate $outputlangs Output language object
     * @param int $default_font_size Default font size
     * @return float New Y position
     */
    protected function _renderEquipmentSection(&$pdf, $equipment, $detail, $materials, $material_total, $curY, $outputlangs, $default_font_size, $is_first = false, $is_last = false, $total_duration = 0)
    {
        // Store start position for border
        $startY = $curY;
        $leftMargin = $this->marge_gauche;
        $rightMargin = $this->marge_droite;
        $pageWidth = $this->page_largeur;

        // If first equipment, draw "Beschreibung" header (fully bordered)
        if ($is_first) {
            $pdf->SetFont('', 'B', $default_font_size);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetXY($leftMargin, $curY);
            $pdf->Cell($pageWidth - $leftMargin - $rightMargin, 6, "Beschreibung", 'TLR', 1, 'L', 1);
            $curY = $pdf->GetY();
        }

        $pdf->SetFont('', 'B', $default_font_size + 1);
        $pdf->SetXY($this->marge_gauche, $curY);
        $pdf->SetTextColor(0, 0, 100);
        $pdf->MultiCell(0, 5, "Anlage: ".$equipment->equipment_number." - ".$outputlangs->convToOutputCharset($equipment->label), 0, 'L');

        $curY = $pdf->GetY() + 2;

        // Equipment details
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->SetTextColor(0, 0, 0);

        // Type
        $type_label = $equipment->equipment_type;
        if (isset($equipment->fields['equipment_type']['arrayofkeyval'][$equipment->equipment_type])) {
            $type_label = $outputlangs->trans($equipment->fields['equipment_type']['arrayofkeyval'][$equipment->equipment_type]);
        }

        $pdf->SetXY($this->marge_gauche, $curY);
        $pdf->MultiCell(90, 4, $outputlangs->transnoentities("Type").": ".$type_label, 0, 'L');

        // Location (Standort)
        if ($equipment->location_note) {
            $pdf->SetXY(110, $curY);
            $pdf->MultiCell(90, 4, "Standort: ".$outputlangs->convToOutputCharset($equipment->location_note), 0, 'L');
        }

        $curY = $pdf->GetY() + 3;

        // Date and duration in gray background table
        $pdf->SetFont('', '', $default_font_size - 2);
        $pdf->SetFillColor(220, 220, 220);
        $sectionWidth = $pageWidth - $leftMargin - $rightMargin;

        $date_text = '';
        if ($detail->work_date) {
            $date_text = $outputlangs->transnoentities("Date").": ".dol_print_date($detail->work_date, 'day', false, $outputlangs, true);
        }

        $duration_text = '';
        // Only show duration if NOT a maintenance equipment (user requested: no time for maintenance equipment)
        if ($detail->work_duration > 0 && empty($equipment->maintenance_month)) {
            $hours = floor($detail->work_duration / 60);
            $minutes = $detail->work_duration % 60;
            $duration_text = $outputlangs->transnoentities("Duration").": ".$hours."h";
            if ($minutes > 0) {
                $duration_text .= " ".$minutes."min";
            }
        }

        // Set cell padding for date/duration table
        $pdf->SetCellPadding(1);

        $pdf->SetXY($leftMargin, $curY);
        $halfWidth = $sectionWidth / 2;
        $pdf->Cell($halfWidth, 5, $date_text, 'LTB', 0, 'L', 1);
        $pdf->Cell($halfWidth, 5, $duration_text, 'RTB', 1, 'R', 1);

        // Reset cell padding
        $pdf->SetCellPadding(0);

        $curY = $pdf->GetY() + 3;

        // Work done
        if ($detail->work_done) {
            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->SetXY($this->marge_gauche, $curY);
            $pdf->MultiCell(0, 4, $outputlangs->transnoentities("WorkDone").":", 0, 'L');
            $curY = $pdf->GetY();

            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->SetXY($this->marge_gauche, $curY);
            $work_done_text = str_replace("\n", "\n- ", "- ".$outputlangs->convToOutputCharset($detail->work_done));
            $pdf->MultiCell(0, 4, $work_done_text, 0, 'L');
            $curY = $pdf->GetY() + 2;
        }

        // Issues found
        if ($detail->issues_found) {
            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->SetXY($this->marge_gauche, $curY);
            $pdf->MultiCell(0, 4, $outputlangs->transnoentities("IssuesFound").":", 0, 'L');
            $curY = $pdf->GetY();

            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->SetXY($this->marge_gauche, $curY);
            $issues_text = str_replace("\n", "\n- ", "- ".$outputlangs->convToOutputCharset($detail->issues_found));
            $pdf->MultiCell(0, 4, $issues_text, 0, 'L');
            $curY = $pdf->GetY() + 2;
        }

        // Recommendations
        if ($detail->recommendations) {
            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->SetXY($this->marge_gauche, $curY);
            $pdf->MultiCell(0, 4, $outputlangs->transnoentities("Recommendations").":", 0, 'L');
            $curY = $pdf->GetY();

            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->SetXY($this->marge_gauche, $curY);
            $recommendations_text = str_replace("\n", "\n- ", "- ".$outputlangs->convToOutputCharset($detail->recommendations));
            $pdf->MultiCell(0, 4, $recommendations_text, 0, 'L');
            $curY = $pdf->GetY() + 2;
        }

        // Materials table
        if (count($materials) > 0) {
            $curY = $pdf->GetY() + 2;

            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->SetXY($this->marge_gauche, $curY);
            $pdf->MultiCell(0, 4, $outputlangs->transnoentities("UsedMaterial").":", 0, 'L');
            $curY = $pdf->GetY() + 1;

            // Table header (without price column) - continuous columns
            $pdf->SetFont('', 'B', $default_font_size - 2);
            $pdf->SetFillColor(220, 220, 220);
            $sectionWidth = $pageWidth - $leftMargin - $rightMargin;

            // Set cell padding for table
            $pdf->SetCellPadding(1);

            $pdf->SetXY($leftMargin, $curY);
            $pdf->Cell(120, 5, "Material", 'LT', 0, 'L', 1);
            $pdf->Cell(25, 5, $outputlangs->transnoentities("Qty"), 'T', 0, 'C', 1);
            $pdf->Cell($sectionWidth - 145, 5, $outputlangs->transnoentities("Unit"), 'RT', 1, 'C', 1);

            $curY = $pdf->GetY();

            // Table rows (without price column) - continuous columns
            $pdf->SetFont('', '', $default_font_size - 2);
            $materialCount = count($materials);
            $materialIndex = 0;
            foreach ($materials as $material) {
                $materialIndex++;
                $isLast = ($materialIndex === $materialCount);
                $pdf->SetXY($leftMargin, $curY);
                $pdf->Cell(120, 5, $outputlangs->convToOutputCharset($material->material_name), 'L'.($isLast ? 'B' : ''), 0, 'L');
                $pdf->Cell(25, 5, $material->quantity, ($isLast ? 'B' : ''), 0, 'C');
                $pdf->Cell($sectionWidth - 145, 5, $outputlangs->convToOutputCharset($material->unit), 'R'.($isLast ? 'B' : ''), 1, 'C');

                $curY = $pdf->GetY();
            }

            // Reset cell padding
            $pdf->SetCellPadding(0);

            // Materials close the section - no additional spacing needed
            $curY = $pdf->GetY();
        } else {
            // Add spacing if no materials
            $curY = $pdf->GetY() + 2;
        }

        // Draw borders around equipment section content
        $pdf->SetDrawColor(0, 0, 0);
        $sectionHeight = $curY - $startY;
        $sectionWidth = $pageWidth - $leftMargin - $rightMargin;

        // Left border
        $pdf->Line($leftMargin, $startY + ($is_first ? 6 : 0), $leftMargin, $curY);
        // Right border
        $pdf->Line($leftMargin + $sectionWidth, $startY + ($is_first ? 6 : 0), $leftMargin + $sectionWidth, $curY);
        // Bottom border - don't draw if last equipment with materials (materials table has bottom border)
        if (!(count($materials) > 0 && $is_last)) {
            $pdf->Line($leftMargin, $curY, $leftMargin + $sectionWidth, $curY);
        }
        // Top border (only if not first, first has "Beschreibung" header)
        if (!$is_first) {
            $pdf->Line($leftMargin, $startY, $leftMargin + $sectionWidth, $startY);
        }

        return $curY + 5;
    }

    /**
     * Render summary section
     *
     * @param TCPDF $pdf PDF object
     * @param int $equipment_count Number of equipments
     * @param int $total_duration Total duration in minutes
     * @param float $total_material Total material cost
     * @param float $curY Current Y position
     * @param Translate $outputlangs Output language object
     * @param int $default_font_size Default font size
     * @return void
     */
    protected function _renderSummary(&$pdf, $equipment_count, $total_duration, $total_material, $curY, $outputlangs, $default_font_size)
    {
        $pdf->SetFont('', 'B', $default_font_size + 1);
        $pdf->SetXY($this->marge_gauche, $curY);
        $pdf->SetTextColor(0, 0, 100);
        $pdf->MultiCell(0, 5, $outputlangs->transnoentities("Summary"), 0, 'L');

        $curY = $pdf->GetY() + 3;

        $pdf->SetFont('', '', $default_font_size);
        $pdf->SetTextColor(0, 0, 0);

        // Equipment count
        $pdf->SetXY($this->marge_gauche, $curY);
        $pdf->MultiCell(0, 5, $outputlangs->transnoentities("EquipmentCount").": ".$equipment_count, 0, 'L');

        // Total duration
        if ($total_duration > 0) {
            $hours = floor($total_duration / 60);
            $minutes = $total_duration % 60;
            $duration_text = $hours."h";
            if ($minutes > 0) {
                $duration_text .= " ".$minutes."min";
            }

            $curY = $pdf->GetY();
            $pdf->SetXY($this->marge_gauche, $curY);
            $pdf->MultiCell(0, 5, $outputlangs->transnoentities("TotalDuration").": ".$duration_text, 0, 'L');
        }

        // Material costs are not displayed (removed as per user request)
    }

    /**
     * Render signature section
     *
     * @param TCPDF $pdf PDF object
     * @param Fichinter $object Fichinter object
     * @param float $curY Current Y position
     * @param Translate $outputlangs Output language object
     * @param int $default_font_size Default font size
     * @return void
     */
    protected function _renderSignatures(&$pdf, $object, $curY, $outputlangs, $default_font_size)
    {
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->SetTextColor(0, 0, 0);

        // Calculate symmetric positions for signature boxes
        $boxWidth = 80;
        $leftX = $this->marge_gauche;
        $rightX = $this->page_largeur - $this->marge_droite - $boxWidth;

        // Technician signature label - like Soleil
        $pdf->SetXY($leftX, $curY);
        $pdf->MultiCell($boxWidth, 5, $outputlangs->transnoentities("NameAndSignatureOfInternalContact"), 0, 'L', false);

        // Customer signature label - like Soleil
        $pdf->SetXY($rightX, $curY);
        $pdf->MultiCell($boxWidth, 5, $outputlangs->transnoentities("NameAndSignatureOfExternalContact"), 0, 'L', false);

        $curY += 5;

        // Signature boxes - like Soleil: larger boxes (80x25mm) with border
        $pdf->SetXY($leftX, $curY);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->MultiCell($boxWidth, 25, '', 1, 'L'); // Border box for technician

        $pdf->SetXY($rightX, $curY);
        $pdf->MultiCell($boxWidth, 25, '', 1); // Border box for customer

        // Note: Online signature will be added by Dolibarr's signature module below these boxes
        // as "Unterschrift: DD.MM.YYYY - Name"
    }

    /**
     * Show page footer
     *
     * @param TCPDF $pdf PDF object
     * @param Fichinter $object Object fichinter
     * @param Translate $outputlangs Output language object
     * @return int Height of footer
     */
    protected function _pagefoot(&$pdf, $object, $outputlangs)
    {
        global $conf;

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        return pdf_pagefoot($pdf, $outputlangs, 'FICHINTER_FREE_TEXT', $conf->mycompany, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object);
    }
}
