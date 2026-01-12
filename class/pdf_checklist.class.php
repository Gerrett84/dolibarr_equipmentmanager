<?php
/* Copyright (C) 2024-2025 Equipment Manager
 * PDF Generator for Checklists v3.0
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 * Class to generate PDF for Checklists
 */
class pdf_checklist
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Error message
     */
    public $error = '';

    /**
     * @var int Page width
     */
    public $page_largeur;

    /**
     * @var int Page height
     */
    public $page_hauteur;

    /**
     * @var array Page format
     */
    public $format;

    /**
     * @var int Left margin
     */
    public $marge_gauche;

    /**
     * @var int Right margin
     */
    public $marge_droite;

    /**
     * @var int Top margin
     */
    public $marge_haute;

    /**
     * @var int Bottom margin
     */
    public $marge_basse;

    /**
     * @var Societe Emitter company
     */
    public $emetteur;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $conf, $langs, $mysoc;

        $this->db = $db;

        // Page format
        $formatarray = pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        $this->page_hauteur = $formatarray['height'];
        $this->format = array($this->page_largeur, $this->page_hauteur);

        // Page margins
        $this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
        $this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
        $this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
        $this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

        // Emitter
        $this->emetteur = $mysoc;
        if (empty($this->emetteur->country_code)) {
            $this->emetteur->country_code = substr($langs->defaultlang, -2);
        }
    }

    /**
     * Write the PDF file
     *
     * @param ChecklistResult $checklist Checklist result object
     * @param Equipment $equipment Equipment object
     * @param ChecklistTemplate $template Template with sections and items
     * @param Fichinter $intervention Intervention object
     * @param User $user User object
     * @param Translate $outputlangs Language object
     * @return string|bool File path on success, false on failure
     */
    public function write_file($checklist, $equipment, $template, $intervention, $user, $outputlangs)
    {
        global $conf, $mysoc, $db;

        if (!is_object($outputlangs)) {
            global $langs;
            $outputlangs = $langs;
        }

        $outputlangs->loadLangs(array("main", "dict", "companies", "equipmentmanager@equipmentmanager"));

        // Load checklist item results
        $checklist->fetchItemResults();

        // Define output directory - use intervention document folder
        $objectref = dol_sanitizeFileName($intervention->ref);
        $dir = $conf->ficheinter->dir_output.'/'.$objectref;
        if (!file_exists($dir)) {
            dol_mkdir($dir);
        }

        // Filename: Checklist_EquipmentNumber_Date.pdf
        $safe_equipment_number = dol_sanitizeFileName($equipment->equipment_number);
        $date_str = dol_print_date($checklist->date_completion, '%Y%m%d');
        $filename = $dir.'/Checklist_'.$safe_equipment_number.'_'.$date_str.'.pdf';
        $filename_short = 'Checklist_'.$safe_equipment_number.'_'.$date_str.'.pdf';

        // Create PDF instance
        $pdf = pdf_getInstance($this->format);
        $default_font_size = pdf_getPDFFontSize($outputlangs);

        if (class_exists('TCPDF')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }

        $pdf->SetFont(pdf_getPDFFont($outputlangs));
        $pdf->Open();
        $pdf->SetDrawColor(128, 128, 128);

        $pdf->SetTitle($outputlangs->convToOutputCharset($checklist->ref));
        $pdf->SetSubject($outputlangs->convToOutputCharset($outputlangs->transnoentities('ChecklistProtocol')));
        $pdf->SetCreator("Dolibarr ".DOL_VERSION);
        $pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));

        $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
        $pdf->SetAutoPageBreak(1, $this->marge_basse + 20);

        // Add first page
        $pdf->AddPage();
        $pagenb = 1;

        // Draw header
        $posy = $this->_pagehead($pdf, $checklist, $equipment, $template, $intervention, $outputlangs);
        $posy += 5;

        // Draw equipment info box
        $posy = $this->_drawEquipmentInfo($pdf, $equipment, $intervention, $outputlangs, $posy);
        $posy += 5;

        // Draw checklist sections and items
        $posy = $this->_drawChecklistContent($pdf, $checklist, $template, $outputlangs, $posy);

        // Draw result and signature
        $posy = $this->_drawResult($pdf, $checklist, $user, $outputlangs, $posy);

        // Draw footer
        $this->_pagefoot($pdf, $outputlangs, $pagenb);

        // Save PDF
        $pdf->Output($filename, 'F');

        if (file_exists($filename)) {
            // Add to linked files of the intervention
            require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

            // Update intervention to refresh file list
            $intervention->add_object_linked('fichinter', $intervention->id);

            return $filename;
        }

        $this->error = 'Error creating PDF file';
        return false;
    }

    /**
     * Draw page header
     *
     * @param TCPDF $pdf PDF object
     * @param ChecklistResult $checklist Checklist
     * @param Equipment $equipment Equipment
     * @param ChecklistTemplate $template Template
     * @param Fichinter $intervention Intervention
     * @param Translate $outputlangs Language object
     * @return int Y position after header
     */
    protected function _pagehead(&$pdf, $checklist, $equipment, $template, $intervention, $outputlangs)
    {
        global $conf, $mysoc;

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $posy = $this->marge_haute;

        // Logo
        $logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
        if ($mysoc->logo && file_exists($logo)) {
            $height = pdf_getHeightForLogo($logo);
            $pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);
            $posy += $height + 5;
        } else {
            $posy += 5;
        }

        // Company name
        $pdf->SetFont('', 'B', $default_font_size + 2);
        $pdf->SetXY($this->marge_gauche, $posy);
        $pdf->Cell(0, 6, $outputlangs->convToOutputCharset($mysoc->name), 0, 1, 'L');
        $posy += 8;

        // Title
        $pdf->SetFont('', 'B', $default_font_size + 4);
        $pdf->SetXY($this->marge_gauche, $posy);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 10, $outputlangs->convToOutputCharset($outputlangs->transnoentities('ChecklistProtocol')), 1, 1, 'C', true);
        $posy += 12;

        // Template info
        $pdf->SetFont('', '', $default_font_size);
        $pdf->SetXY($this->marge_gauche, $posy);
        $pdf->Cell(0, 5, $outputlangs->convToOutputCharset($outputlangs->trans($template->label)).' - '.$outputlangs->convToOutputCharset($template->norm_reference), 0, 1, 'L');
        $posy += 7;

        // Reference and date
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->SetXY($this->marge_gauche, $posy);
        $pdf->Cell(50, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Ref')).': '.$checklist->ref, 0, 0, 'L');
        $pdf->Cell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Date')).': '.dol_print_date($checklist->date_completion, 'day'), 0, 1, 'R');
        $posy += 7;

        return $posy;
    }

    /**
     * Draw equipment info box
     *
     * @param TCPDF $pdf PDF object
     * @param Equipment $equipment Equipment
     * @param Fichinter $intervention Intervention
     * @param Translate $outputlangs Language object
     * @param int $posy Current Y position
     * @return int New Y position
     */
    protected function _drawEquipmentInfo(&$pdf, $equipment, $intervention, $outputlangs, $posy)
    {
        global $db;

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;

        // Box
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetFillColor(250, 250, 250);
        $pdf->Rect($this->marge_gauche, $posy, $width, 35, 'DF');

        $posy += 3;
        $pdf->SetFont('', 'B', $default_font_size);
        $pdf->SetXY($this->marge_gauche + 3, $posy);
        $pdf->Cell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Equipment')), 0, 1, 'L');
        $posy += 6;

        $pdf->SetFont('', '', $default_font_size - 1);

        // Equipment number
        $pdf->SetXY($this->marge_gauche + 3, $posy);
        $pdf->Cell(40, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentities('EquipmentNumber')).':', 0, 0, 'L');
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->Cell(0, 4, $outputlangs->convToOutputCharset($equipment->equipment_number), 0, 1, 'L');
        $posy += 5;

        $pdf->SetFont('', '', $default_font_size - 1);

        // Label
        $pdf->SetXY($this->marge_gauche + 3, $posy);
        $pdf->Cell(40, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Label')).':', 0, 0, 'L');
        $pdf->Cell(0, 4, $outputlangs->convToOutputCharset($equipment->label), 0, 1, 'L');
        $posy += 5;

        // Manufacturer
        $pdf->SetXY($this->marge_gauche + 3, $posy);
        $pdf->Cell(40, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Manufacturer')).':', 0, 0, 'L');
        $pdf->Cell(0, 4, $outputlangs->convToOutputCharset($equipment->manufacturer), 0, 1, 'L');
        $posy += 5;

        // Location
        if ($equipment->fk_address > 0) {
            require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
            $contact = new Contact($db);
            $contact->fetch($equipment->fk_address);

            $pdf->SetXY($this->marge_gauche + 3, $posy);
            $pdf->Cell(40, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentities('ObjectAddress')).':', 0, 0, 'L');
            $address_text = $contact->getFullName($outputlangs);
            if ($contact->address) $address_text .= ', '.$contact->address;
            if ($contact->zip || $contact->town) $address_text .= ', '.$contact->zip.' '.$contact->town;
            $pdf->Cell(0, 4, $outputlangs->convToOutputCharset($address_text), 0, 1, 'L');
            $posy += 5;
        }

        // Intervention ref
        $pdf->SetXY($this->marge_gauche + 3, $posy);
        $pdf->Cell(40, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Intervention')).':', 0, 0, 'L');
        $pdf->Cell(0, 4, $intervention->ref, 0, 1, 'L');
        $posy += 8;

        return $posy;
    }

    /**
     * Draw checklist content
     *
     * @param TCPDF $pdf PDF object
     * @param ChecklistResult $checklist Checklist
     * @param ChecklistTemplate $template Template
     * @param Translate $outputlangs Language object
     * @param int $posy Current Y position
     * @return int New Y position
     */
    protected function _drawChecklistContent(&$pdf, $checklist, $template, $outputlangs, $posy)
    {
        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
        $col1_width = $width * 0.55;
        $col2_width = $width * 0.25;
        $col3_width = $width * 0.20;

        foreach ($template->sections as $section) {
            // Check page break
            if ($posy > $this->page_hauteur - 60) {
                $pdf->AddPage();
                $posy = $this->marge_haute + 10;
            }

            // Section header
            $pdf->SetFont('', 'B', $default_font_size);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->SetXY($this->marge_gauche, $posy);
            $pdf->Cell($width, 7, $outputlangs->convToOutputCharset($outputlangs->trans($section->label)), 1, 1, 'L', true);
            $posy += 8;

            // Column headers
            $pdf->SetFont('', 'B', $default_font_size - 2);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetXY($this->marge_gauche, $posy);
            $pdf->Cell($col1_width, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('CheckPoint')), 1, 0, 'L', true);
            $pdf->Cell($col2_width, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Result')), 1, 0, 'C', true);
            $pdf->Cell($col3_width, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Notes')), 1, 1, 'L', true);
            $posy += 6;

            // Items
            $pdf->SetFont('', '', $default_font_size - 2);
            foreach ($section->items as $item) {
                // Check page break
                if ($posy > $this->page_hauteur - 30) {
                    $pdf->AddPage();
                    $posy = $this->marge_haute + 10;
                }

                $item_result = isset($checklist->item_results[$item->id]) ? $checklist->item_results[$item->id] : array();
                $answer = isset($item_result['answer']) ? $item_result['answer'] : '';
                $answer_text = isset($item_result['answer_text']) ? $item_result['answer_text'] : '';
                $note = isset($item_result['note']) ? $item_result['note'] : '';

                // Item label
                $pdf->SetXY($this->marge_gauche, $posy);
                $pdf->Cell($col1_width, 6, $outputlangs->convToOutputCharset($outputlangs->trans($item->label)), 1, 0, 'L');

                // Result with color
                if ($item->answer_type == 'info') {
                    $display_answer = $outputlangs->convToOutputCharset($answer_text);
                    $pdf->SetTextColor(0, 0, 0);
                } else {
                    if ($answer == 'ok' || $answer == 'ja') {
                        $display_answer = $outputlangs->convToOutputCharset($outputlangs->trans('Answer'.ucfirst($answer)));
                        $pdf->SetTextColor(0, 128, 0); // Green
                    } elseif ($answer == 'mangel' || $answer == 'nein') {
                        $display_answer = $outputlangs->convToOutputCharset($outputlangs->trans('Answer'.ucfirst($answer)));
                        $pdf->SetTextColor(200, 0, 0); // Red
                    } elseif ($answer == 'nv') {
                        $display_answer = $outputlangs->convToOutputCharset($outputlangs->trans('AnswerNv'));
                        $pdf->SetTextColor(128, 128, 128); // Gray
                    } else {
                        $display_answer = '-';
                        $pdf->SetTextColor(0, 0, 0);
                    }
                }

                $pdf->Cell($col2_width, 6, $display_answer, 1, 0, 'C');
                $pdf->SetTextColor(0, 0, 0);

                // Note
                $pdf->Cell($col3_width, 6, $outputlangs->convToOutputCharset($note), 1, 1, 'L');
                $posy += 7;
            }

            $posy += 3;
        }

        return $posy;
    }

    /**
     * Draw result and signature
     *
     * @param TCPDF $pdf PDF object
     * @param ChecklistResult $checklist Checklist
     * @param User $user User
     * @param Translate $outputlangs Language object
     * @param int $posy Current Y position
     * @return int New Y position
     */
    protected function _drawResult(&$pdf, $checklist, $user, $outputlangs, $posy)
    {
        global $conf, $db;

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;

        // Check page break for signature section
        if ($posy > $this->page_hauteur - 60) {
            $pdf->AddPage();
            $posy = $this->marge_haute + 10;
        }

        $posy += 10;

        // Load technician info
        $technician = new User($db);
        $technician->fetch($checklist->fk_user_completion);

        // Signature box with frame
        $signature_box_width = 80;
        $signature_box_height = 35;

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($this->marge_gauche, $posy, $signature_box_width, $signature_box_height, 'D');

        // Signature header inside box
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->SetXY($this->marge_gauche + 2, $posy + 2);
        $pdf->Cell($signature_box_width - 4, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentities('TechnicianSignature')), 0, 1, 'L');

        // Try to get signature from equipmentmanager settings
        $signature_file = DOL_DATA_ROOT.'/equipmentmanager/signatures/user_'.$technician->id.'.png';

        if (file_exists($signature_file)) {
            $pdf->Image($signature_file, $this->marge_gauche + 5, $posy + 8, 45, 18);
        }

        // Technician name at bottom of signature box
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->SetXY($this->marge_gauche + 2, $posy + $signature_box_height - 6);
        $pdf->Cell($signature_box_width - 4, 4, $outputlangs->convToOutputCharset($technician->getFullName($outputlangs)), 0, 1, 'L');

        // Date box next to signature
        $date_box_x = $this->marge_gauche + $signature_box_width + 10;
        $pdf->Rect($date_box_x, $posy, 60, $signature_box_height, 'D');

        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->SetXY($date_box_x + 2, $posy + 2);
        $pdf->Cell(56, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Date')), 0, 1, 'L');

        $pdf->SetFont('', '', $default_font_size);
        $pdf->SetXY($date_box_x + 2, $posy + 14);
        $pdf->Cell(56, 6, dol_print_date($checklist->date_completion, 'day'), 0, 1, 'C');

        $posy += $signature_box_height + 5;

        return $posy;
    }

    /**
     * Draw page footer
     *
     * @param TCPDF $pdf PDF object
     * @param Translate $outputlangs Language object
     * @param int $pagenb Page number
     */
    protected function _pagefoot(&$pdf, $outputlangs, $pagenb)
    {
        global $conf, $mysoc;

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $posy = $this->page_hauteur - $this->marge_basse;

        $pdf->SetFont('', '', $default_font_size - 3);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY($this->marge_gauche, $posy);

        // Company info
        $footer_text = $mysoc->name;
        if ($mysoc->address) $footer_text .= ' - '.$mysoc->address;
        if ($mysoc->zip || $mysoc->town) $footer_text .= ' - '.$mysoc->zip.' '.$mysoc->town;

        $pdf->Cell(0, 4, $footer_text, 0, 1, 'C');

        // Page number
        $pdf->SetXY($this->marge_gauche, $posy + 4);
        $pdf->Cell(0, 4, $outputlangs->transnoentities('Page').' '.$pagenb, 0, 0, 'C');

        $pdf->SetTextColor(0, 0, 0);
    }
}
