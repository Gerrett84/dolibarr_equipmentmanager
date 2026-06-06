<?php
/* Copyright (C) 2024-2026 Equipment Manager
 * Price List Admin - v5.3.0
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
dol_include_once('/equipmentmanager/class/pricelistitem.class.php');

$langs->loadLangs(array("equipmentmanager@equipmentmanager", "products", "companies"));

if (!$user->rights->equipmentmanager->equipment->read) accessforbidden();

$action  = GETPOST('action', 'aZ09');
$tab     = GETPOST('tab', 'aZ09') ?: 'rate';
$item_id = (int) GETPOST('item_id', 'int');

// ─── AJAX: product data ──────────────────────────────────────────────────────

if ($action === 'get_product_json') {
    $pid = (int) GETPOST('product_id', 'int');
    $data = array('label' => '', 'description' => '', 'unit' => '');
    if ($pid > 0) {
        $sql = "SELECT p.label, p.description, p.duration, u.label AS unit_label"
            ." FROM ".MAIN_DB_PREFIX."product p"
            ." LEFT JOIN ".MAIN_DB_PREFIX."c_units u ON u.rowid = p.fk_unit"
            ." WHERE p.rowid = ".$pid;
        $res2 = $db->query($sql);
        if ($res2 && ($obj = $db->fetch_object($res2))) {
            $data['label'] = $obj->label;
            // Strip HTML from description, use first non-empty line
            $clean = trim(strip_tags(str_replace(array('<br>', '<br/>', '<br />', '</p>', '</li>'), "\n", $obj->description ?? '')));
            $lines = array_filter(array_map('trim', explode("\n", $clean)));
            $data['description'] = implode(' ', array_slice(array_values($lines), 0, 3));
            // Unit: prefer linked unit label, then parse duration string
            if (!empty($obj->unit_label)) {
                $data['unit'] = $obj->unit_label;
            } elseif (!empty($obj->duration)) {
                $dur = strtolower(trim($obj->duration));
                if (strpos($dur, 'h') !== false) $data['unit'] = 'Std.';
                elseif (strpos($dur, 'd') !== false) $data['unit'] = 'Tag';
                elseif (strpos($dur, 'm') !== false) $data['unit'] = 'Monat';
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$form     = new Form($db);
$formComp = new FormCompany($db);

// ─── Actions ────────────────────────────────────────────────────────────────

if ($action === 'save_item' && $user->rights->equipmentmanager->equipment->write) {
    $item              = new PriceListItem($db);
    $item->rowid       = $item_id;
    $item->list_type   = $tab;
    $item->fk_product  = (int) GETPOST('fk_product', 'int');
    $item->label       = trim(GETPOST('label', 'alphanohtml'));
    $item->description = trim(GETPOST('description', 'restricthtml'));
    $item->unit        = trim(GETPOST('unit', 'alphanohtml'));

    if (empty($item->label)) {
        setEventMessages($langs->trans('PriceListLabelRequired'), null, 'errors');
    } else {
        if ($item->rowid > 0) {
            $existing = new PriceListItem($db);
            $existing->fetch($item->rowid);
            $item->position = $existing->position;
            $ret = $item->update($user);
        } else {
            $item->position = $item->getNextPosition($tab);
            $ret = $item->create($user);
        }
        if ($ret < 0) {
            setEventMessages($item->error, null, 'errors');
        } else {
            setEventMessages($langs->trans($item_id > 0 ? 'PriceListItemUpdated' : 'PriceListItemAdded'), null, 'mesgs');
        }
    }
    header("Location: ".$_SERVER["PHP_SELF"]."?tab=".$tab);
    exit;
}

if ($action === 'delete_item' && $item_id > 0 && $user->rights->equipmentmanager->equipment->write) {
    $item = new PriceListItem($db);
    if ($item->fetch($item_id) > 0) $item->delete($user);
    header("Location: ".$_SERVER["PHP_SELF"]."?tab=".$tab);
    exit;
}

if ($action === 'move_up' && $item_id > 0) {
    $item = new PriceListItem($db);
    if ($item->fetch($item_id) > 0) $item->moveUp($item->list_type);
    header("Location: ".$_SERVER["PHP_SELF"]."?tab=".$tab);
    exit;
}

if ($action === 'move_down' && $item_id > 0) {
    $item = new PriceListItem($db);
    if ($item->fetch($item_id) > 0) $item->moveDown($item->list_type);
    header("Location: ".$_SERVER["PHP_SELF"]."?tab=".$tab);
    exit;
}

if ($action === 'delete_archive' && $item_id > 0 && $user->rights->equipmentmanager->equipment->write) {
    $sql_da = "SELECT pdf_filename FROM ".MAIN_DB_PREFIX."equipmentmanager_pricelist_archive WHERE rowid=".$item_id;
    $res_da = $db->query($sql_da);
    if ($res_da && ($obj_da = $db->fetch_object($res_da))) {
        $fpath = DOL_DATA_ROOT.'/equipmentmanager/pricelist/'.$obj_da->pdf_filename;
        if (is_file($fpath)) @unlink($fpath);
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_pricelist_archive WHERE rowid=".$item_id);
    }
    header("Location: ".$_SERVER["PHP_SELF"]."?tab=".$tab);
    exit;
}

// ─── Load data ──────────────────────────────────────────────────────────────

$proto      = new PriceListItem($db);
$items_rate = $proto->fetchAllByType('rate');
$items_mnt  = $proto->fetchAllByType('maintenance');

$archives = array();
$sql_arc = "SELECT a.rowid, a.list_type, a.fk_soc, a.discount, a.saved_date, a.pdf_filename, u.login, s.nom AS customer_name"
    ." FROM ".MAIN_DB_PREFIX."equipmentmanager_pricelist_archive a"
    ." LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = a.fk_user_creat"
    ." LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = a.fk_soc"
    ." WHERE a.list_type = '".$db->escape($tab)."'"
    ." ORDER BY a.saved_date DESC, a.rowid DESC";
$res_arc = $db->query($sql_arc);
if ($res_arc) { while ($obj = $db->fetch_object($res_arc)) $archives[] = $obj; }

$edit_item = null;
$scroll_to_form = false;
if ($action === 'edit_item' && $item_id > 0) {
    $edit_item = new PriceListItem($db);
    if ($edit_item->fetch($item_id) <= 0) $edit_item = null;
    else $scroll_to_form = true;
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function formatPrice($price)
{
    return number_format((float)$price, 2, ',', '.') . ' €';
}

// ─── View ───────────────────────────────────────────────────────────────────

$title = $langs->trans('PriceList');
llxHeader('', $title);

print load_fiche_titre($title, '', 'price');

$head = array();
$head[0][0] = $_SERVER['PHP_SELF'].'?tab=rate';
$head[0][1] = $langs->trans('PriceListRate');
$head[0][2] = 'rate';
$head[1][0] = $_SERVER['PHP_SELF'].'?tab=maintenance';
$head[1][1] = $langs->trans('PriceListMaintenance');
$head[1][2] = 'maintenance';
print dol_get_fiche_head($head, $tab, '', -1);

$current_items = ($tab === 'maintenance') ? $items_mnt : $items_rate;

// ─── PDF buttons ─────────────────────────────────────────────────────────────
print '<div class="tabsAction">';
print '<a class="butAction" href="pricelist_pdf.php?list_type='.$tab.'" target="_blank">'.img_picto('', 'pdf').' '.$langs->trans('PriceListGeneratePDF').'</a>';
print ' <a class="butAction" id="btnCustomerPdf" href="#">'.img_picto('', 'company').' '.$langs->trans('PriceListCustomerPDF').'</a>';
if ($user->rights->equipmentmanager->equipment->write) {
    print ' <a class="butAction" href="pricelist_pdf.php?list_type='.$tab.'&save=1&token='.newToken().'" onclick="return confirm(\''.dol_escape_js($langs->trans('PriceListArchiveConfirm')).'\')">'.img_picto('', 'bookmark').' '.$langs->trans('PriceListArchive').'</a>';
}
print '</div>';

// ─── Inline customer PDF panel ────────────────────────────────────────────────
print '<div id="customerPdfPanel" style="display:none;border:1px solid var(--colorborderlight,#ddd);border-radius:4px;padding:14px 18px;margin-bottom:14px;background:var(--colorbacktitle1,#f8f8f8)">';
print '<strong>'.img_picto('', 'company').' '.$langs->trans('PriceListCustomerPDF').'</strong>';
print '<form id="custPdfForm" method="GET" action="pricelist_pdf.php" target="_blank" style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;margin-top:10px">';
print '<input type="hidden" name="list_type" value="'.$tab.'">';
print '<input type="hidden" name="save" id="custPdfSaveInput" value="0">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<div><label style="display:block;font-size:.85em;margin-bottom:3px;font-weight:600">'.$langs->trans('Customer').'</label>';
print $formComp->select_company('', 'fk_soc', '', $langs->trans('SelectCustomer'), 0, 0, array(), 0, 'maxwidth300');
print '</div>';
print '<div><label style="display:block;font-size:.85em;margin-bottom:3px;font-weight:600">'.$langs->trans('PriceListDiscount').' (%)</label>';
print '<input type="number" name="discount" min="0" max="99" step="0.5" value="0" style="width:80px"> %</div>';
print '<div style="padding-top:2px;display:flex;flex-wrap:wrap;gap:6px">';
print '<button type="submit" id="custPdfBtnView" class="button">'.img_picto('', 'pdf').' '.$langs->trans('PriceListGeneratePDF').'</button>';
if ($user->rights->equipmentmanager->equipment->write) {
    print ' <button type="submit" id="custPdfBtnArchive" class="button">'.img_picto('', 'bookmark').' '.$langs->trans('PriceListArchive').'</button>';
}
print ' <button type="button" class="button button-cancel" id="btnCancelCustomerPdf">'.$langs->trans('Close').'</button>';
print '</div></form></div>';

// ─── Add / Edit form ──────────────────────────────────────────────────────────
$is_edit      = ($edit_item !== null);
$form_item_id = $is_edit ? $edit_item->rowid : 0;
$pf_product   = $is_edit ? (int)$edit_item->fk_product : 0;
$pf_label     = $is_edit ? htmlspecialchars($edit_item->label) : '';
$pf_unit      = $is_edit ? htmlspecialchars($edit_item->unit) : '';
$pf_desc      = $is_edit ? htmlspecialchars($edit_item->description ?? '') : '';

print '<div id="plForm" style="margin-bottom:16px">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="4">'.($is_edit ? $langs->trans('PriceListEditItem') : $langs->trans('PriceListAddItem')).'</th></tr>';
print '<tr class="oddeven"><td style="vertical-align:top;padding-top:10px;width:140px"><label for="fk_product">'.$langs->trans('PriceListProduct').'</label></td>';
print '<td colspan="3">';
print '<form id="plItemForm" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_item">';
print '<input type="hidden" name="tab" value="'.dol_escape_htmltag($tab).'">';
print '<input type="hidden" name="item_id" value="'.$form_item_id.'">';
print '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:10px">';
print $form->select_produits($pf_product ?: '', 'fk_product', '', 0, 0, -1, 2, '– '.$langs->trans('PriceListProductOptional').' –', 0, array(), 0, '1', 0, 'maxwidth350', 0, 'id="pl_product_select"');
print '</div>';
print '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:10px">';
print '<div><label style="display:block;font-size:.85em;margin-bottom:2px">'.$langs->trans('PriceListLabel').' *</label>';
print '<input type="text" id="pl_label" name="label" class="minwidth250" value="'.$pf_label.'" placeholder="'.$langs->trans('PriceListLabelHint').'" required></div>';
print '<div><label style="display:block;font-size:.85em;margin-bottom:2px">'.$langs->trans('Unit').'</label>';
print '<input type="text" id="pl_unit" name="unit" style="width:100px" value="'.$pf_unit.'" placeholder="Std., Pausch.…"></div>';
print '</div>';
print '<div style="margin-bottom:10px"><label style="display:block;font-size:.85em;margin-bottom:2px">'.$langs->trans('PriceListDescription').'</label>';
print '<input type="text" id="pl_desc" name="description" class="minwidth400" value="'.$pf_desc.'" placeholder="'.$langs->trans('PriceListDescriptionHint').'"></div>';
print '<input type="submit" class="button" value="'.($is_edit ? $langs->trans('Save') : $langs->trans('Add')).'">';
if ($is_edit) {
    print ' <a href="'.$_SERVER["PHP_SELF"].'?tab='.$tab.'" class="button button-cancel">'.$langs->trans('Cancel').'</a>';
}
print '</form></td></tr></table></div>';

// ─── Items table ─────────────────────────────────────────────────────────────
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th style="width:36px">Pos.</th>';
print '<th>'.$langs->trans('PriceListLabel').'</th>';
print '<th>'.$langs->trans('PriceListDescription').'</th>';
print '<th style="width:80px">'.$langs->trans('Unit').'</th>';
print '<th style="width:100px;text-align:right">'.$langs->trans('PriceNet').'</th>';
print '<th style="width:60px;text-align:center">MwSt.</th>';
print '<th style="width:110px"></th>';
print '</tr>';

if (empty($current_items)) {
    print '<tr><td colspan="7" class="opacitymedium" style="padding:12px">'.$langs->trans('PriceListEmpty').'</td></tr>';
}

$base = $_SERVER["PHP_SELF"];
foreach ($current_items as $i => $it) {
    $price_net = $it->product_price !== null ? formatPrice($it->product_price) : '<span class="opacitymedium">–</span>';
    $vat       = $it->product_tva_tx !== null ? number_format((float)$it->product_tva_tx, 0).' %' : '';
    print '<tr class="oddeven">';
    print '<td style="text-align:center">'.($i + 1).'.</td>';
    print '<td><strong>'.dol_escape_htmltag($it->label).'</strong>';
    if ($it->product_ref) print '<br><small class="opacitymedium">'.$it->product_ref.'</small>';
    print '</td>';
    print '<td><small>'.dol_escape_htmltag($it->description ?? '').'</small></td>';
    print '<td>'.dol_escape_htmltag($it->unit).'</td>';
    print '<td style="text-align:right">'.$price_net.'</td>';
    print '<td style="text-align:center"><small>'.$vat.'</small></td>';
    print '<td style="white-space:nowrap;text-align:right">';
    if ($user->rights->equipmentmanager->equipment->write) {
        $tok = newToken();
        if ($i > 0) {
            print '<a href="'.$base.'?action=move_up&tab='.$tab.'&item_id='.$it->rowid.'&token='.$tok.'" title="'.$langs->trans('Up').'">'.img_picto('', 'uparrow').'</a> ';
        }
        if ($i < count($current_items) - 1) {
            print '<a href="'.$base.'?action=move_down&tab='.$tab.'&item_id='.$it->rowid.'&token='.$tok.'" title="'.$langs->trans('Down').'">'.img_picto('', 'downarrow').'</a> ';
        }
        print '<a href="'.$base.'?action=edit_item&tab='.$tab.'&item_id='.$it->rowid.'&token='.$tok.'">'.img_picto('', 'edit').'</a> ';
        print '<a href="'.$base.'?action=delete_item&tab='.$tab.'&item_id='.$it->rowid.'&token='.$tok.'" onclick="return confirm(\''.dol_escape_js($langs->trans('ConfirmDeletePriceListItem')).'\')">'.img_picto('', 'delete').'</a>';
    }
    print '</td></tr>';
}
print '</table>';

// ─── Archive history ──────────────────────────────────────────────────────────
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Date').'</th>';
print '<th>'.$langs->trans('Customer').'</th>';
print '<th>'.$langs->trans('PriceListDiscount').'</th>';
print '<th>'.$langs->trans('CreatedBy').'</th>';
print '<th></th>';
print '</tr>';
if (empty($archives)) {
    print '<tr><td colspan="5" class="opacitymedium" style="padding:12px">'.$langs->trans('PriceListArchiveEmpty').'</td></tr>';
} else {
    foreach ($archives as $arc) {
        $tok2 = newToken();
        print '<tr class="oddeven">';
        print '<td>'.dol_print_date(strtotime($arc->saved_date), '%d.%m.%Y').'</td>';
        print '<td>'.dol_escape_htmltag($arc->customer_name ?? '–').'</td>';
        print '<td>'.($arc->discount > 0 ? number_format((float)$arc->discount, 1, ',', '.').' %' : '–').'</td>';
        print '<td>'.dol_escape_htmltag($arc->login ?? '').'</td>';
        print '<td style="text-align:right;white-space:nowrap">';
        print '<a href="pricelist_pdf.php?archive_id='.(int)$arc->rowid.'&token='.$tok2.'" target="_blank">'.img_picto('', 'search').' '.$langs->trans('Preview').'</a>';
        print ' &nbsp; <a href="pricelist_pdf.php?archive_id='.(int)$arc->rowid.'&dl=1&token='.$tok2.'">'.img_picto('', 'download').' '.$langs->trans('Download').'</a>';
        if ($user->rights->equipmentmanager->equipment->write) {
            print ' &nbsp; <a href="'.$_SERVER["PHP_SELF"].'?action=delete_archive&tab='.$tab.'&item_id='.(int)$arc->rowid.'&token='.$tok2.'" onclick="return confirm(\''.dol_escape_js($langs->trans('PriceListArchiveConfirmDelete')).'\')">'.img_picto('', 'delete').'</a>';
        }
        print '</td></tr>';
    }
}
print '</table>';

print dol_get_fiche_end();

// ─── JavaScript ──────────────────────────────────────────────────────────────
$ajax_url = dol_buildpath('/equipmentmanager/pricelist.php', 1).'?action=get_product_json&token='.newToken();

print '<script>
(function(){
    // ── Customer PDF inline panel ────────────────────────────────────────
    document.getElementById("btnCustomerPdf").addEventListener("click", function(e){
        e.preventDefault();
        var p = document.getElementById("customerPdfPanel");
        p.style.display = (p.style.display === "none") ? "block" : "none";
        if (p.style.display === "block") p.scrollIntoView({behavior:"smooth", block:"nearest"});
    });
    document.getElementById("btnCancelCustomerPdf").addEventListener("click", function(){
        document.getElementById("customerPdfPanel").style.display = "none";
    });
    // View button: open in new tab, no save
    var btnView = document.getElementById("custPdfBtnView");
    if (btnView) btnView.addEventListener("click", function(){
        document.getElementById("custPdfSaveInput").value = "0";
        document.getElementById("custPdfForm").target = "_blank";
    });
    // Archive button: save on server, redirect back
    var btnArchive = document.getElementById("custPdfBtnArchive");
    if (btnArchive) btnArchive.addEventListener("click", function(){
        if (!confirm('.json_encode($langs->trans('PriceListArchiveConfirm')).')) { return false; }
        document.getElementById("custPdfSaveInput").value = "1";
        document.getElementById("custPdfForm").target = "_self";
    });

    // ── Scroll to form if editing ────────────────────────────────────────
    '.($scroll_to_form ? 'document.getElementById("plForm").scrollIntoView({behavior:"smooth",block:"start"});' : '').'

    // ── Product autofill ─────────────────────────────────────────────────
    function fillFromProduct(productId) {
        if (!productId || productId <= 0) return;
        fetch("'.addslashes($ajax_url).'&product_id=" + productId)
            .then(function(r){ return r.json(); })
            .then(function(d){
                var lbl  = document.getElementById("pl_label");
                var unit = document.getElementById("pl_unit");
                var desc = document.getElementById("pl_desc");
                // Only overwrite if field is currently empty
                if (lbl  && !lbl.value.trim()  && d.label)       lbl.value  = d.label;
                if (unit && !unit.value.trim() && d.unit)        unit.value = d.unit;
                if (desc && !desc.value.trim() && d.description) desc.value = d.description;
            })
            .catch(function(){});
    }

    // Dolibarr select_produits() generates a <select id="fk_product">
    // It may also use select2 — listen on both native change and select2 event
    var sel = document.getElementById("pl_product_select");
    if (!sel) sel = document.querySelector("select[name=\"fk_product\"]");
    if (sel) {
        sel.addEventListener("change", function(){
            fillFromProduct(this.value);
        });
        // select2 compatibility
        if (window.jQuery && jQuery(sel).data("select2")) {
            jQuery(sel).on("select2:select", function(e){
                fillFromProduct(e.params.data.id);
            });
        } else if (window.jQuery) {
            jQuery(sel).on("change", function(){
                fillFromProduct(this.value);
            });
        }
    }
})();
</script>';

llxFooter();
$db->close();
