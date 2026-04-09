<?php
/* Copyright (C) 2024 Equipment Manager
 * Bulk equipment creation — create multiple equipment records at once
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
dol_include_once('/equipmentmanager/class/equipment.class.php');

$langs->loadLangs(array('equipmentmanager@equipmentmanager', 'companies', 'other'));

$action = GETPOST('action', 'aZ09');

$form        = new Form($db);
$formcompany = new FormCompany($db);

if (!$user->rights->equipmentmanager->equipment->write) {
    accessforbidden();
}

// ─── Action: bulk create ─────────────────────────────────────────────────────
if ($action == 'bulk_create') {
    $error    = 0;
    $qty      = max(1, min(50, (int) GETPOST('qty', 'int')));
    $eq_type  = GETPOST('equipment_type', 'alpha');
    $fk_soc   = (int) GETPOST('fk_soc', 'int');
    $fk_addr  = (int) GETPOST('fk_address', 'int');
    $fk_cont  = (int) GETPOST('fk_contract', 'int');
    $label    = GETPOST('label', 'alphanohtml');
    $manuf    = GETPOST('manufacturer', 'alphanohtml');
    $duration = (int) GETPOST('planned_duration', 'int');
    $interval = GETPOST('maintenance_interval', 'alpha');

    $contract_status = GETPOST('contract_status', 'int');
    if (!in_array($contract_status, array(0, 1))) {
        $contract_status = -1;
    }

    if (empty($eq_type)) {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Type')), null, 'errors');
        $error++;
    }
    if ($fk_soc <= 0) {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('ThirdParty')), null, 'errors');
        $error++;
    }
    if ($fk_addr <= 0) {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('ObjectAddress')), null, 'errors');
        $error++;
    }
    if ($contract_status < 0) {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('MaintenanceContract')), null, 'errors');
        $error++;
    }

    if (!$error) {
        // If no label given, use the translated type name as default
        $equipmentTypes = Equipment::getEquipmentTypes($db);
        $defaultLabel   = isset($equipmentTypes[$eq_type]) ? $langs->trans($equipmentTypes[$eq_type]) : $eq_type;

        $created = 0;
        for ($i = 0; $i < $qty; $i++) {
            $eq = new Equipment($db);
            $eq->ref                  = 'auto';
            $eq->equipment_number_mode = 'auto';
            $eq->equipment_type       = $eq_type;
            $eq->label                = $label ? $label : $defaultLabel;
            $eq->fk_soc               = $fk_soc;
            $eq->fk_address           = $fk_addr > 0 ? $fk_addr : 0;
            $eq->fk_contract          = $fk_cont > 0 ? $fk_cont : 0;
            $eq->manufacturer         = $manuf;
            $eq->planned_duration     = $duration > 0 ? $duration : 0;
            $eq->maintenance_interval = $interval;
            $eq->status               = $contract_status;

            $result = $eq->create($user);
            if ($result > 0) {
                $created++;
            } else {
                setEventMessages($eq->error, $eq->errors, 'errors');
                break;
            }
        }

        if ($created > 0) {
            setEventMessages(sprintf($langs->trans('BulkCreateSuccess'), $created), null, 'mesgs');
            header('Location: '.dol_buildpath('/equipmentmanager/equipment_list.php', 1));
            exit;
        }
    }
}

// ─── View ────────────────────────────────────────────────────────────────────
llxHeader('', $langs->trans('BulkCreateEquipment'));

print load_fiche_titre($langs->trans('BulkCreateEquipment'), '', 'object_generic');

print '<form method="POST" action="'.dol_buildpath('/equipmentmanager/equipment_bulk_create.php', 1).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="bulk_create">';

print '<div class="div-table-responsive-no-min">';
print '<table class="border centpercent">';

// ── Anzahl ────────────────────────────────────────────────────────────────────
print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('BulkCreateQty').'</td><td>';
print '<input type="number" name="qty" min="1" max="50" value="'.((int)GETPOST('qty','int') ?: 1).'" class="flat" style="width:80px;" required>';
print ' <span class="opacitymedium">(max. 50)</span>';
print '</td></tr>';

// ── Typ ───────────────────────────────────────────────────────────────────────
print '<tr><td class="fieldrequired">'.$langs->trans('Type').'</td><td>';
print '<select name="equipment_type" class="flat minwidth200" required>';
print '<option value=""></option>';
$equipmentTypes = Equipment::getEquipmentTypes($db);
$postedType = GETPOST('equipment_type', 'alpha');
foreach ($equipmentTypes as $code => $labelKey) {
    $sel = ($postedType == $code) ? ' selected' : '';
    print '<option value="'.dol_escape_htmltag($code).'"'.$sel.'>'.$langs->trans($labelKey).'</option>';
}
print '</select>';
print '</td></tr>';

// ── Auftraggeber ──────────────────────────────────────────────────────────────
print '<tr><td class="fieldrequired">'.$langs->trans('ThirdParty').'</td><td>';
$postedSoc = (int) GETPOST('fk_soc', 'int');
print $form->select_company($postedSoc, 'fk_soc', '', 1, 0, 1, array(), 0, 'minwidth300',
    'onchange="loadAddresses(this.value); loadContracts(this.value);"');
print '</td></tr>';

// ── Objektadresse ─────────────────────────────────────────────────────────────
print '<tr><td class="fieldrequired">'.$langs->trans('ObjectAddress').'</td><td>';
print '<select name="fk_address" id="fk_address_select" class="flat minwidth300" required>';
print '<option value="">---</option>';
$postedAddr = (int) GETPOST('fk_address', 'int');
if ($postedSoc > 0) {
    $sqlAddr = "SELECT rowid, CONCAT(lastname, ' ', firstname) as name, address, zip, town FROM ".MAIN_DB_PREFIX."socpeople";
    $sqlAddr .= " WHERE fk_soc = ".$postedSoc." ORDER BY lastname, firstname";
    $resAddr = $db->query($sqlAddr);
    if ($resAddr) {
        while ($addr = $db->fetch_object($resAddr)) {
            $sel = ($postedAddr == $addr->rowid) ? ' selected' : '';
            $disp = trim($addr->name);
            if ($addr->town) {
                $disp .= ' - '.$addr->town;
            }
            print '<option value="'.$addr->rowid.'"'.$sel.'>'.dol_escape_htmltag($disp).'</option>';
        }
    }
}
print '</select>';
print ' <span class="opacitymedium" id="address_hint" style="'.($postedSoc > 0 ? 'display:none' : '').'">'.
      $langs->trans('SelectThirdPartyFirst').'</span>';
print '</td></tr>';

// ── Wartungsvertrag ───────────────────────────────────────────────────────────
$postedContStatus = GETPOST('action') == 'bulk_create' ? (int)GETPOST('contract_status','int') : -1;
print '<tr><td class="fieldrequired">'.$langs->trans('MaintenanceContract').'</td><td>';
print '<select name="contract_status" id="contract_status_select" class="flat" required onchange="toggleContractRow(this.value)">';
print '<option value=""></option>';
print '<option value="1"'.($postedContStatus===1?' selected':'').'>'.$langs->trans('ActiveContract').'</option>';
print '<option value="0"'.($postedContStatus===0?' selected':'').'>'.$langs->trans('NoContract').'</option>';
print '</select>';
print '</td></tr>';

// ── Vertrag ───────────────────────────────────────────────────────────────────
$contractRowStyle = ($postedContStatus === 1) ? '' : 'display:none;';
print '<tr id="contract_row" style="'.$contractRowStyle.'">';
print '<td>'.$langs->trans('Contract').'</td><td>';
print '<select name="fk_contract" id="fk_contract_select" class="flat minwidth300">';
print '<option value="">---</option>';
$postedCont = (int) GETPOST('fk_contract', 'int');
if ($postedSoc > 0) {
    $sqlCont = "SELECT c.rowid, c.ref, c.ref_customer FROM ".MAIN_DB_PREFIX."contrat as c";
    $sqlCont .= " WHERE c.fk_soc = ".$postedSoc." AND c.entity IN (".getEntity('contrat').")";
    $sqlCont .= " ORDER BY c.ref";
    $resCont = $db->query($sqlCont);
    if ($resCont) {
        while ($cont = $db->fetch_object($resCont)) {
            $contLabel = $cont->ref.($cont->ref_customer ? ' ('.$cont->ref_customer.')' : '');
            $sel = ($postedCont == $cont->rowid) ? ' selected' : '';
            print '<option value="'.$cont->rowid.'"'.$sel.'>'.dol_escape_htmltag($contLabel).'</option>';
        }
    }
}
print '</select>';
print '</td></tr>';

// ── Bezeichnung (optional) ────────────────────────────────────────────────────
print '<tr><td>'.$langs->trans('Label').'</td><td>';
print '<input type="text" name="label" class="flat minwidth300" value="'.dol_escape_htmltag(GETPOST('label','alphanohtml')).'" ';
print 'placeholder="'.$langs->trans('BulkCreateLabelHint').'">';
print '</td></tr>';

// ── Hersteller (optional) ─────────────────────────────────────────────────────
print '<tr><td>'.$langs->trans('Manufacturer').'</td><td>';
print '<input type="text" name="manufacturer" class="flat minwidth300" value="'.dol_escape_htmltag(GETPOST('manufacturer','alphanohtml')).'">';
print '</td></tr>';

// ── Planzeit (optional) ───────────────────────────────────────────────────────
print '<tr><td>'.$langs->trans('PlannedDuration').'</td><td>';
$postedDur = (int) GETPOST('planned_duration', 'int');
print '<input type="number" name="planned_duration" min="0" value="'.($postedDur ?: '').'" class="flat" style="width:80px;">';
print ' <span class="opacitymedium">'.$langs->trans('Minutes').'</span>';
print '</td></tr>';

// ── Wartungsintervall (optional) ──────────────────────────────────────────────
print '<tr><td>'.$langs->trans('MaintenanceInterval').'</td><td>';
$postedInt = GETPOST('maintenance_interval', 'alpha');
print '<select name="maintenance_interval" class="flat">';
print '<option value="">---</option>';
print '<option value="yearly"'.($postedInt=='yearly'?' selected':'').'>'.$langs->trans('IntervalYearly').'</option>';
print '<option value="semi_annual"'.($postedInt=='semi_annual'?' selected':'').'>'.$langs->trans('IntervalSemiAnnual').'</option>';
print '</select>';
print '</td></tr>';

print '</table>';
print '</div>';

print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans('BulkCreateSubmit').'">';
print ' <a class="butActionDelete" href="'.dol_buildpath('/equipmentmanager/equipment_list.php', 1).'">'.$langs->trans('Cancel').'</a>';
print '</div>';

print '</form>';

// ── JavaScript: AJAX load addresses & contracts on company change ─────────────
print '<script>';
print 'function toggleContractRow(val) {';
print '  var row = document.getElementById("contract_row");';
print '  if (row) row.style.display = (val == "1") ? "" : "none";';
print '}';
print 'function loadAddresses(socId) {';
print '  var sel = document.getElementById("fk_address_select");';
print '  var hint = document.getElementById("address_hint");';
print '  sel.innerHTML = "<option value=\"\">---</option>";';
print '  if (!socId) { if(hint) hint.style.display=""; return; }';
print '  if(hint) hint.style.display="none";';
print '  var xhr = new XMLHttpRequest();';
print '  xhr.open("GET", "'.dol_buildpath('/equipmentmanager/ajax/get_addresses.php', 1).'?socid=" + socId, true);';
print '  xhr.onload = function() {';
print '    if (xhr.status === 200) {';
print '      try { var data = JSON.parse(xhr.responseText); }';
print '      catch(e) { return; }';
print '      data.forEach(function(a) {';
print '        var opt = document.createElement("option");';
print '        opt.value = a.id;';
print '        opt.textContent = a.label;';
print '        sel.appendChild(opt);';
print '      });';
print '    }';
print '  };';
print '  xhr.send();';
print '}';
print 'function loadContracts(socId) {';
print '  var sel = document.getElementById("fk_contract_select");';
print '  sel.innerHTML = "<option value=\"\">---</option>";';
print '  if (!socId) return;';
print '  var xhr = new XMLHttpRequest();';
print '  xhr.open("GET", "'.dol_buildpath('/equipmentmanager/ajax/get_contracts.php', 1).'?socid=" + socId, true);';
print '  xhr.onload = function() {';
print '    if (xhr.status === 200) {';
print '      try { var data = JSON.parse(xhr.responseText); }';
print '      catch(e) { return; }';
print '      data.forEach(function(c) {';
print '        var opt = document.createElement("option");';
print '        opt.value = c.id;';
print '        opt.textContent = c.label;';
print '        sel.appendChild(opt);';
print '      });';
print '    }';
print '  };';
print '  xhr.send();';
print '}';
print '</script>';

llxFooter();
$db->close();
