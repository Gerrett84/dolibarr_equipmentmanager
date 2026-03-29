<?php
/* Copyright (C) 2025 Equipment Manager - Gerrett84
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/custom/equipmentmanager/core/modules/facture/doc/pdf_sponge_objektadresse.modules.php
 *  \ingroup    invoice
 *  \brief      PDF invoice template based on sponge, with bold Objektadresse rendering
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_sponge.modules.php';

/**
 * Class to manage PDF invoice template sponge with bold Objektadresse
 */
class pdf_sponge_objektadresse extends pdf_sponge
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);

		global $langs;

		$this->name = "sponge_objektadresse";
		$this->description = $langs->trans('PDFSpongeDescription').' (mit Objektadresse)';
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Show top section of page. Need this->emetteur object
	 *
	 * @param  TCPDF        $pdf            Object to distant TCPDF
	 * @param  Facture      $object         Object to show
	 * @param  int          $showaddress    0=no, 1=yes
	 * @param  Translate    $outputlangs    Object lang for output
	 * @param  Translate    $outputlangsbis Object lang for output bis
	 * @return array                        top shift of linked object lines
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null)
	{
		// phpcs:enable
		global $conf, $langs;

		$ltrdirection = 'L';
		if ($outputlangs->trans("DIRECTION") == 'rtl') {
			$ltrdirection = 'R';
		}

		// Load traductions files required by page
		$outputlangs->loadLangs(array("main", "bills", "propal", "companies"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 110;

		$posy = $this->marge_haute;
		$posx = $this->page_largeur - $this->marge_droite - $w;

		$pdf->SetXY($this->marge_gauche, $posy);

		// Logo
		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
			if ($this->emetteur->logo) {
				$logodir = $conf->mycompany->dir_output;
				if (!empty($conf->mycompany->multidir_output[$object->entity ?? $conf->entity])) {
					$logodir = $conf->mycompany->multidir_output[$object->entity ?? $conf->entity];
				}
				if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO')) {
					$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
				} else {
					$logo = $logodir.'/logos/'.$this->emetteur->logo;
				}
				if (is_readable($logo)) {
					$height = pdf_getHeightForLogo($logo);
					$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height); // width=0 (auto)
				} else {
					$pdf->SetTextColor(200, 0, 0);
					$pdf->SetFont('', 'B', $default_font_size - 2);
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
				}
			} else {
				$text = $this->emetteur->name;
				$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($text), 0, $ltrdirection);
			}
		}

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$subtitle = "";
		$title = $outputlangs->transnoentities("PdfInvoiceTitle");
		if ($object->type == 1) {
			$title = $outputlangs->transnoentities("InvoiceReplacement");
		}
		if ($object->type == 2) {
			$title = $outputlangs->transnoentities("InvoiceAvoir");
		}
		if ($object->type == 3) {
			$title = $outputlangs->transnoentities("InvoiceDeposit");
		}
		if ($object->type == 4) {
			$title = $outputlangs->transnoentities("InvoiceProForma");
		}
		if ($this->situationinvoice) {
			$outputlangs->loadLangs(array("other"));
			$title = $outputlangs->transnoentities("PDFInvoiceSituation") . " " . $outputlangs->transnoentities("NumberingShort") . $object->situation_counter . " -";
			$subtitle = $outputlangs->transnoentities("PDFSituationTitle", (string) $object->situation_counter);
		}
		if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
			$title .= ' - ';
			if ($object->type == 0) {
				if ($this->situationinvoice) {
					$title .= $outputlangsbis->transnoentities("PDFInvoiceSituation");
				}
				$title .= $outputlangsbis->transnoentities("PdfInvoiceTitle");
			} elseif ($object->type == 1) {
				$title .= $outputlangsbis->transnoentities("InvoiceReplacement");
			} elseif ($object->type == 2) {
				$title .= $outputlangsbis->transnoentities("InvoiceAvoir");
			} elseif ($object->type == 3) {
				$title .= $outputlangsbis->transnoentities("InvoiceDeposit");
			} elseif ($object->type == 4) {
				$title .= $outputlangsbis->transnoentities("InvoiceProForma");
			}
		}
		$title .= ' '.$outputlangs->convToOutputCharset($object->ref);
		if ($object->status == $object::STATUS_DRAFT) {
			$pdf->SetTextColor(128, 0, 0);
			$title .= ' - '.$outputlangs->transnoentities("NotValidated");
		}

		$pdf->MultiCell($w, 3, $title, '', 'R');
		if (!empty($subtitle)) {
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetXY($posx, $posy + 5);
			$pdf->MultiCell($w, 6, $subtitle, '', 'R');
			$posy += 2;
		}

		$pdf->SetFont('', '', $default_font_size - 2);

		pdfWriteBlockedLogSignature($pdf, $outputlangs, $this->page_hauteur, $object, $w, $posx, $posy);

		/*
		$posy += 5;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size);
		$textref = $outputlangs->transnoentities("Ref")." : ".$outputlangs->convToOutputCharset($object->ref);
		if ($object->status == $object::STATUS_DRAFT) {
		$pdf->SetTextColor(128, 0, 0);
		$textref .= ' - '.$outputlangs->transnoentities("NotValidated");
		}
		$pdf->MultiCell($w, 4, $textref, '', 'R');*/

		$posy += 3;
		$pdf->SetFont('', '', $default_font_size - 2);

		if ($object->ref_customer) {
			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefCustomer")." : ".dol_trunc($outputlangs->convToOutputCharset($object->ref_customer), 65), '', 'R');
		}

		if (getDolGlobalString('PDF_SHOW_PROJECT_TITLE')) {
			$object->fetchProject();
			if (!empty($object->project->ref)) {
				$posy += 3;
				$pdf->SetXY($posx, $posy);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("Project")." : ".(empty($object->project->title) ? '' : $object->project->title), '', 'R');
			}
		}

		if (getDolGlobalString('PDF_SHOW_PROJECT')) {
			$object->fetchProject();
			if (!empty($object->project->ref)) {
				$outputlangs->load("projects");
				$posy += 3;
				$pdf->SetXY($posx, $posy);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefProject")." : ".(empty($object->project->ref) ? '' : $object->project->ref), '', 'R');
			}
		}

		$objectidnext = $object->getIdReplacingInvoice('validated');
		if ($object->type == 0 && $objectidnext) {
			$objectreplacing = new Facture($this->db);
			$objectreplacing->fetch($objectidnext);

			$posy += 3;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ReplacementByInvoice").' : '.$outputlangs->convToOutputCharset($objectreplacing->ref), '', 'R');
		}
		if ($object->type == 1) {
			$objectreplaced = new Facture($this->db);
			$objectreplaced->fetch($object->fk_facture_source);

			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ReplacementInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref), '', 'R');
		}
		if ($object->type == 2 && !empty($object->fk_facture_source)) {
			$objectreplaced = new Facture($this->db);
			$objectreplaced->fetch($object->fk_facture_source);

			$posy += 3;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CorrectionInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref), '', 'R');
		}

		$posy += 4;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);

		$title = $outputlangs->transnoentities("DateInvoice");
		if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
			$title .= ' - '.$outputlangsbis->transnoentities("DateInvoice");
		}
		$pdf->MultiCell($w, 3, $title." : ".dol_print_date($object->date, "day", false, $outputlangs, true), '', 'R');

		if (getDolGlobalString('INVOICE_POINTOFTAX_DATE')) {
			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("DatePointOfTax")." : ".dol_print_date($object->date_pointoftax, "day", false, $outputlangs), '', 'R');
		}

		if ($object->type != 2) {
			$posy += 3;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$title = $outputlangs->transnoentities("DateDue");
			if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
				$title .= ' - '.$outputlangsbis->transnoentities("DateDue");
			}
			$pdf->MultiCell($w, 3, $title." : ".dol_print_date($object->date_lim_reglement, "day", false, $outputlangs, true), '', 'R');
		}

		if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_CODE') && $object->thirdparty->code_client) {
			$posy += 3;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerCode")." : ".$outputlangs->transnoentities($object->thirdparty->code_client), '', 'R');
		}

		if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_ACCOUNTING_CODE') && $object->thirdparty->code_compta_client) {
			$posy += 3;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerAccountancyCode")." : ".$outputlangs->transnoentities($object->thirdparty->code_compta_client), '', 'R');
		}

		// Get contact
		if (getDolGlobalString('DOC_SHOW_FIRST_SALES_REP')) {
			$arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
			if (count($arrayidcontact) > 0) {
				$usertmp = new User($this->db);
				$usertmp->fetch($arrayidcontact[0]);
				$posy += 4;
				$pdf->SetXY($posx, $posy);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("SalesRepresentative")." : ".$usertmp->getFullName($langs), '', 'R');
			}
		}

		$posy += 1;

		$top_shift = 0;
		$shipp_shift = 0;
		// Show list of linked objects
		if (!getDolGlobalString('INVOICE_HIDE_LINKED_OBJECT')) {
			$current_y = $pdf->getY();
			$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, 3, 'R', $default_font_size);
			if ($current_y < $pdf->getY()) {
				$top_shift = $pdf->getY() - $current_y;
			}
		}

		if ($showaddress) {
			// Sender properties
			$carac_emetteur = '';
			// Add internal contact of object if defined
			$arrayidcontact = $object->getIdContact('internal', 'BILLING');
			if (count($arrayidcontact) > 0) {
				$object->fetch_user($arrayidcontact[0]);
				$labelbeforecontactname = ($outputlangs->transnoentities("FromContactName") != 'FromContactName' ? $outputlangs->transnoentities("FromContactName") : $outputlangs->transnoentities("Name"));
				$carac_emetteur .= ($carac_emetteur ? "\n" : '').$labelbeforecontactname." ".$outputlangs->convToOutputCharset($object->user->getFullName($outputlangs));
				$carac_emetteur .= "\n";
			}

			$carac_emetteur .= pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

			// Show sender
			$posy = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
			$posy += $top_shift;
			$posx = $this->marge_gauche;
			if (getDolGlobalString('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->page_largeur - $this->marge_droite - 80;
			}

			$hautcadre = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 38 : 40;
			$widthrecbox = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 82;

			// Show sender frame
			if (!getDolGlobalString('MAIN_PDF_NO_SENDER_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx, $posy - 5);
				$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillFrom"), 0, $ltrdirection);
				$pdf->SetXY($posx, $posy);
				$pdf->SetFillColor(230, 230, 230);
				$pdf->RoundedRect($posx, $posy, $widthrecbox, $hautcadre, $this->corner_radius, '1234', 'F');
				$pdf->SetTextColor(0, 0, 60);
			}

			// Show sender name
			if (!getDolGlobalString('MAIN_PDF_HIDE_SENDER_NAME')) {
				$pdf->SetXY($posx + 2, $posy + 3);
				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, $ltrdirection);
				$posy = $pdf->getY();
			}

			// Show sender information
			$pdf->SetXY($posx + 2, $posy);
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->MultiCell($widthrecbox - 2, 4, $carac_emetteur, 0, $ltrdirection);

			// If BILLING contact defined on invoice, we use it
			$usecontact = false;
			$arrayidcontact = $object->getIdContact('external', 'BILLING');
			if (count($arrayidcontact) > 0) {
				$usecontact = true;
				$result = $object->fetch_contact($arrayidcontact[0]);
			}

			// Recipient name
			if ($usecontact && ($object->contact->socid != $object->thirdparty->id && (!isset($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT) || getDolGlobalString('MAIN_USE_COMPANY_NAME_OF_CONTACT')))) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			$carac_client_name = is_object($thirdparty) ? pdfBuildThirdpartyName($thirdparty, $outputlangs) : '';

			$mode = 'target';
			$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($usecontact ? $object->contact : ''), ($usecontact ? 1 : 0), $mode, $object);

			// Strip the plain-text Objektadresse appended by the hook (we will render it in bold below)
			$outputlangs->load("equipmentmanager@equipmentmanager");
			$objaddr_marker = "\n".$outputlangs->transnoentities("ObjectAddress").":\n";
			$marker_pos = strpos($carac_client, $objaddr_marker);
			if ($marker_pos !== false) {
				$carac_client = substr($carac_client, 0, $marker_pos);
			}

			// Show recipient
			$widthrecbox = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 100;
			if ($this->page_largeur < 210) {
				$widthrecbox = 84; // To work with US executive format
			}
			$posy = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
			$posy += $top_shift;
			$posx = $this->page_largeur - $this->marge_droite - $widthrecbox;
			if (getDolGlobalString('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->marge_gauche;
			}

			// Check for Objektadresse (OBJ contact type)
			$carac_objektadresse = '';
			$idaddressobj = $object->getIdContact('external', 'OBJ');
			if (!empty($idaddressobj) && is_array($idaddressobj) && count($idaddressobj) > 0) {
				require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
				$contactobj = new Contact($this->db);
				if ($contactobj->fetch($idaddressobj[0]) > 0) {
					// Build Objektadresse string
					if ($contactobj->lastname || $contactobj->firstname) {
						$carac_objektadresse .= trim($contactobj->firstname.' '.$contactobj->lastname)."\n";
					}
					if ($contactobj->address) {
						$carac_objektadresse .= $contactobj->address."\n";
					}
					$cityline = '';
					if ($contactobj->zip) {
						$cityline .= $contactobj->zip;
					}
					if ($contactobj->town) {
						$cityline .= ($cityline ? ' ' : '').$contactobj->town;
					}
					if ($cityline) {
						$carac_objektadresse .= $cityline;
					}
				}
			}

			// Calculate frame height - increase if Objektadresse exists
			$hautcadre_recipient = $hautcadre;
			if (!empty($carac_objektadresse)) {
				$hautcadre_recipient = $hautcadre + 25;
			}

			// Show recipient frame
			if (!getDolGlobalString('MAIN_PDF_NO_RECIPENT_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx + 2, $posy - 5);
				$pdf->MultiCell($widthrecbox - 2, 5, $outputlangs->transnoentities("BillTo"), 0, $ltrdirection);
				$pdf->RoundedRect($posx, $posy, $widthrecbox, $hautcadre_recipient, $this->corner_radius, '1234', 'D');
			}

			// Show recipient name
			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetFont('', 'B', $default_font_size);
			// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
			$pdf->MultiCell($widthrecbox - 2, 2, $carac_client_name, 0, $ltrdirection);

			$posy = $pdf->getY();

			// Show recipient information
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetXY($posx + 2, $posy);
			// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
			$pdf->MultiCell($widthrecbox - 2, 4, $carac_client, 0, $ltrdirection);

			// Show Objektadresse AFTER customer address if exists
			if (!empty($carac_objektadresse)) {
				// Get Y position after customer address was fully printed
				$posy_after_client = $pdf->getY();

				// Add spacing
				$posy_after_client += 2;

				// Load translations
				$langs->load("equipmentmanager@equipmentmanager");

				// Print Objektadresse label (bold)
				$pdf->SetXY($posx + 2, $posy_after_client);
				$pdf->SetFont('', 'B', $default_font_size - 1);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->transnoentities("ObjectAddress").':', 0, $ltrdirection);

				// Print Objektadresse content
				$posy_after_label = $pdf->getY();
				$pdf->SetXY($posx + 2, $posy_after_label);
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell($widthrecbox - 2, 4, $carac_objektadresse, 0, $ltrdirection);
			}

			// Show shipping/delivery address
			if (getDolGlobalInt('INVOICE_SHOW_SHIPPING_ADDRESS')) {
				$idaddressshipping = $object->getIdContact('external', 'SHIPPING');

				if (!empty($idaddressshipping)) {
					$object->fetch_contact($idaddressshipping[0]);	// Load $object->contact
					$companystatic = new Societe($this->db);
					$companystatic->fetch($object->contact->fk_soc);
					$carac_client_name_shipping = pdfBuildThirdpartyName($object->contact, $outputlangs);
					$carac_client_shipping = pdf_build_address($outputlangs, $this->emetteur, $companystatic, $object->contact, 1, 'target', $object);
				} else {
					$carac_client_name_shipping = pdfBuildThirdpartyName($object->thirdparty, $outputlangs);
					$carac_client_shipping = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'target', $object);
				}
				if (!empty($carac_client_shipping)) {
					$posy += $hautcadre;

					$hautcadre -= 10;	// Height for the shipping address does not need to be as high as main box

					// Show shipping frame
					$pdf->SetXY($posx + 2, $posy - 5);
					$pdf->SetFont('', '', $default_font_size - 2);
					$pdf->MultiCell($widthrecbox, 0, $outputlangs->transnoentities('ShippingTo'), 0, 'L', false);
					$pdf->RoundedRect($posx, $posy, $widthrecbox, $hautcadre, $this->corner_radius, '1234', 'D');

					// Show shipping name
					$pdf->SetXY($posx + 2, $posy + 3);
					$pdf->SetFont('', 'B', $default_font_size);
					$pdf->MultiCell($widthrecbox - 2, 2, $carac_client_name_shipping, '', 'L');

					$posy = $pdf->getY();

					// Show shipping information
					$pdf->SetXY($posx + 2, $posy);
					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->MultiCell($widthrecbox - 2, 2, $carac_client_shipping, '', 'L');

					$shipp_shift += $hautcadre + 10;
				}
			}
		}

		$pdf->SetTextColor(0, 0, 0);

		$pagehead = array('top_shift' => $top_shift, 'shipp_shift' => $shipp_shift);

		return $pagehead;
	}
}
