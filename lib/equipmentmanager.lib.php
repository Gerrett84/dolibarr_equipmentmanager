<?php
/* Copyright (C) 2024 Equipment Manager
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Tabs of the admin setup pages (admin/*.php)
 *
 * @return array Tab definitions for dol_get_fiche_head()
 */
function equipmentmanagerAdminPrepareHead()
{
	global $langs;

	$head = array();
	$base = '/equipmentmanager/admin/';
	$tabs = array(
		array('setup.php', 'ModuleSetup', 'setup'),
		array('pdf.php', 'PdfAndDesign', 'pdf'),
		array('equipment_types.php', 'EquipmentTypesSetup', 'equipment_types'),
		array('checklists.php', 'ChecklistTemplates', 'checklists'),
	);
	foreach ($tabs as $i => $tab) {
		$head[$i][0] = dol_buildpath($base.$tab[0], 1);
		$head[$i][1] = $langs->trans($tab[1]);
		$head[$i][2] = $tab[2];
	}

	return $head;
}

/**
 * Standard page opening for the per-user / settings pages reachable from the left menu
 *
 * @param  string $title  Translation key of the page title
 * @param  string $picto  Title picto
 * @return void
 */
function equipmentmanagerSettingsHeader($title, $picto = 'setup')
{
	global $langs;

	llxHeader('', $langs->trans($title));
	print load_fiche_titre($langs->trans($title), '', $picto);
}
