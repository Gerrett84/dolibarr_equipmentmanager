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

$action   = GETPOST('action', 'aZ09');
$tab      = GETPOST('tab', 'aZ09') ?: 'rate';
$item_id  = (int) GETPOST('item_id', 'int');

$form     = new Form($db);
$formComp = new FormCompany($db);

// ─── Actions ────────────────────────────────────────────────────────────────

if ($action === 'save_item' && $user->rights->equipmentmanager->equipment->write) {
    $item = new PriceListItem($db);
    $item->rowid       = $item_id;
    $item->list_type   = $tab;
    $item->fk_product  = (int) GETPOST('fk_product', 'int');
    $item->label       = trim(GETPOST('label', 'alphanohtml'));
    $item->description = trim(GETPOST('description', 'none'));
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
            setEventMessages($item->rowid > 0 ? $langs->trans('PriceListItemUpdated') : $langs->trans('PriceListItemAdded'), null, 'mesgs');
        }
    }
    header("Location: ".$_SERVER["PHP_SELF"]."?tab=".$tab);
    exit;
}

if ($action === 'delete_item' && $item_id > 0 && $user->rights->equipmentmanager->equipment->write) {
    $item = new PriceListItem($db);
    if ($item->fetch($item_id) > 0) {
        $item->delete($user);
    }
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

// ─── Load data ──────────────────────────────────────────────────────────────

$proto      = new PriceListItem($db);
$items_rate = $proto->fetchAllByType('rate');
$items_mnt  = $proto->fetchAllByType('maintenance');

// Edit mode: pre-load item
$edit_item = null;
if ($action === 'edit_item' && $item_id > 0) {
    $edit_item = new PriceListItem($db);
    if ($edit_item->fetch($item_id) <= 0) $edit_item = null;
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function formatPrice($price)
{
    return number_format((float)$price, 2, ',', '.') . ' €';
}

function renderItemsTable($items, $tab, $edit_item, $langs)
{
    global $db, $user, $form;
    $base = $_SERVER["PHP_SELF"];

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th style="width:40px">'.$langs->trans('Position').'</th>';
    print '<th>'.$langs->trans('PriceListLabel').'</th>';
    print '<th>'.$langs->trans('PriceListDescription').'</th>';
    print '<th style="width:80px">'.$langs->trans('Unit').'</th>';
    print '<th style="width:100px;text-align:right">'.$langs->trans('PriceNet').'</th>';
    print '<th style="width:100px;text-align:right">'.$langs->trans('PriceTax').'</th>';
    print '<th style="width:130px"></th>';
    print '</tr>';

    if (empty($items)) {
        print '<tr><td colspan="7" class="opacitymedium">'.$langs->trans('PriceListEmpty').'</td></tr>';
    }

    foreach ($items as $i => $it) {
        $editing = ($edit_item && $edit_item->rowid == $it->rowid);
        $pos_num = $i + 1;

        if ($editing) {
            print '<tr class="oddeven">';
            print '<td>'.$pos_num.'</td>';
            print '<td colspan="5">';
            renderForm($tab, $it, $langs, $form, 'inline');
            print '</td>';
            print '<td><a href="'.$base.'?tab='.$tab.'">'.img_picto('', 'x').' '.$langs->trans('Cancel').'</a></td>';
            print '</tr>';
        } else {
            $price_net = $it->product_price !== null ? formatPrice($it->product_price) : '<span class="opacitymedium">–</span>';
            $vat       = $it->product_tva_tx !== null ? number_format((float)$it->product_tva_tx, 0).'%' : '';
            print '<tr class="oddeven">';
            print '<td style="text-align:center">'.$pos_num.'</td>';
            print '<td><strong>'.dol_escape_htmltag($it->label).'</strong>';
            if ($it->product_ref) print ' <small class="opacitymedium">('.$it->product_ref.')</small>';
            print '</td>';
            print '<td><small>'.nl2br(dol_escape_htmltag($it->description ?? '')).'</small></td>';
            print '<td>'.dol_escape_htmltag($it->unit).'</td>';
            print '<td style="text-align:right">'.$price_net.'</td>';
            print '<td style="text-align:right"><small>'.$vat.'</small></td>';
            print '<td style="white-space:nowrap;text-align:right">';
            if ($user->rights->equipmentmanager->equipment->write) {
                // Move up/down
                if ($i > 0) {
                    print '<a href="'.$base.'?action=move_up&tab='.$tab.'&item_id='.$it->rowid.'" title="'.$langs->trans('Up').'">'.img_picto('', 'uparrow').'</a> ';
                }
                if ($i < count($items) - 1) {
                    print '<a href="'.$base.'?action=move_down&tab='.$tab.'&item_id='.$it->rowid.'" title="'.$langs->trans('Down').'">'.img_picto('', 'downarrow').'</a> ';
                }
                print '<a href="'.$base.'?action=edit_item&tab='.$tab.'&item_id='.$it->rowid.'">'.img_picto('', 'edit').'</a> ';
                print '<a href="'.$base.'?action=delete_item&tab='.$tab.'&item_id='.$it->rowid.'" onclick="return confirm(\''.$langs->trans('ConfirmDeletePriceListItem').'\')">'.img_picto('', 'delete').'</a>';
            }
            print '</td>';
            print '</tr>';
        }
    }
    print '</table>';
}

function renderForm($tab, $prefill, $langs, $form, $mode = 'full')
{
    global $db;
    $base    = $_SERVER["PHP_SELF"];
    $is_edit = ($prefill && $prefill->rowid > 0);
    $inline  = ($mode === 'inline');

    if (!$inline) {
        print '<div class="div-table-responsive">';
        print '<form method="POST" action="'.$base.'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="save_item">';
        print '<input type="hidden" name="tab" value="'.dol_escape_htmltag($tab).'">';
        print '<input type="hidden" name="item_id" value="'.($is_edit ? $prefill->rowid : 0).'">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre"><th colspan="2">'.($is_edit ? $langs->trans('PriceListEditItem') : $langs->trans('PriceListAddItem')).'</th></tr>';
    } else {
        print '<form method="POST" action="'.$base.'" style="display:inline">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="save_item">';
        print '<input type="hidden" name="tab" value="'.dol_escape_htmltag($tab).'">';
        print '<input type="hidden" name="item_id" value="'.($prefill ? $prefill->rowid : 0).'">';
    }

    // Product select
    $product_id = $prefill ? (int)$prefill->fk_product : 0;
    print $inline ? '<table style="width:100%"><tr>' : '<tr><td style="width:160px"><label>'.$langs->trans('PriceListProduct').'</label></td><td>';
    if ($inline) print '<td>';
    print $form->select_produits($product_id ?: '', 'fk_product', '', 0, 0, -1, 2, $langs->trans('PriceListProductOptional'), 0, array(), 0, '1', 0, 'maxwidth300');
    print $inline ? '</td>' : '</td></tr>';

    // Label
    $lbl = $prefill ? htmlspecialchars($prefill->label) : '';
    print $inline ? '<td>' : '<tr><td><label>'.$langs->trans('PriceListLabel').' *</label></td><td>';
    print '<input type="text" name="label" class="minwidth200" value="'.$lbl.'" placeholder="'.$langs->trans('PriceListLabelHint').'" required>';
    print $inline ? '</td>' : '</td></tr>';

    // Unit
    $unit = $prefill ? htmlspecialchars($prefill->unit) : '';
    print $inline ? '<td>' : '<tr><td><label>'.$langs->trans('Unit').'</label></td><td>';
    print '<input type="text" name="unit" class="minwidth100" value="'.$unit.'" placeholder="Std., Pauschal, …">';
    print $inline ? '</td>' : '</td></tr>';

    // Description
    $desc = $prefill ? htmlspecialchars($prefill->description ?? '') : '';
    print $inline ? '<td colspan="2">' : '<tr><td><label>'.$langs->trans('PriceListDescription').'</label></td><td>';
    print '<input type="text" name="description" class="minwidth200" value="'.$desc.'" placeholder="'.$langs->trans('PriceListDescriptionHint').'">';
    print $inline ? '</td>' : '</td></tr>';

    // Buttons
    print $inline ? '<td>' : '<tr><td></td><td>';
    print '<input type="submit" class="button" value="'.($is_edit ? $langs->trans('Save') : $langs->trans('Add')).'">';
    if ($is_edit && !$inline) {
        print ' <a href="'.$base.'?tab='.$tab.'" class="button button-cancel">'.$langs->trans('Cancel').'</a>';
    }
    print $inline ? '</td></tr></table>' : '</td></tr>';

    if (!$inline) {
        print '</table>';
        print '</form>';
        print '</div>';
    } else {
        print '</form>';
    }
}

// ─── View ───────────────────────────────────────────────────────────────────

$title = $langs->trans('PriceList');
llxHeader('', $title);

print load_fiche_titre($title, '', 'price');

// Tabs
$tabs = array(
    'rate'        => $langs->trans('PriceListRate'),
    'maintenance' => $langs->trans('PriceListMaintenance'),
);
print '<div class="tabs">';
print '<ul>';
foreach ($tabs as $key => $label) {
    $active = ($tab === $key) ? ' active' : '';
    print '<li class="tab'.$active.'"><a href="'.$_SERVER["PHP_SELF"].'?tab='.$key.'">'.$label.'</a></li>';
}
print '</ul>';
print '</div>';
print '<br>';

$current_items = ($tab === 'maintenance') ? $items_mnt : $items_rate;
$list_title = $tabs[$tab] ?? '';

// ─── PDF buttons ────────────────────────────────────────────────────────────
print '<div class="tabsAction">';
print '<a class="butAction" href="pricelist_pdf.php?list_type='.$tab.'" target="_blank">'.img_picto('', 'pdf').' '.$langs->trans('PriceListGeneratePDF').'</a>';
print ' <a class="butAction" id="btnCustomerPdf" href="#">'.img_picto('', 'company').' '.$langs->trans('PriceListCustomerPDF').'</a>';
print '</div>';

// ─── Customer PDF modal ─────────────────────────────────────────────────────
print '<div id="customerPdfModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">';
print '<div style="background:var(--colorbackbody,#fff);border-radius:8px;padding:28px 32px;min-width:380px;box-shadow:0 8px 32px rgba(0,0,0,.18);">';
print '<h3 style="margin:0 0 16px">'.$langs->trans('PriceListCustomerPDF').'</h3>';
print '<form id="customerPdfForm" method="GET" action="pricelist_pdf.php" target="_blank">';
print '<input type="hidden" name="list_type" value="'.$tab.'">';
print '<div style="margin-bottom:12px">';
print '<label style="display:block;margin-bottom:4px;font-weight:600">'.$langs->trans('Customer').'</label>';
print $formComp->select_company('', 'fk_soc', '', $langs->trans('SelectCustomer'), 0, 0, array(), 0, 'maxwidth300');
print '</div>';
print '<div style="margin-bottom:12px">';
print '<label style="display:block;margin-bottom:4px;font-weight:600">'.$langs->trans('PriceListDiscount').' (%)</label>';
print '<input type="number" name="discount" min="0" max="99" step="0.5" value="0" class="maxwidth100"> %';
print '</div>';
print '<div>';
print '<button type="submit" class="button">'.$langs->trans('PriceListGeneratePDF').'</button>';
print ' <button type="button" class="button button-cancel" id="btnCancelCustomerPdf">'.$langs->trans('Cancel').'</button>';
print '</div>';
print '</form>';
print '</div>';
print '</div>';

// ─── Add / Edit form ────────────────────────────────────────────────────────
if ($action !== 'edit_item') {
    renderForm($tab, null, $langs, $form);
}

// ─── Items table ────────────────────────────────────────────────────────────
print '<br>';
renderItemsTable($current_items, $tab, $edit_item, $langs);

print '<script>
document.getElementById("btnCustomerPdf").addEventListener("click", function(e){
    e.preventDefault();
    var m = document.getElementById("customerPdfModal");
    m.style.display = "flex";
});
document.getElementById("btnCancelCustomerPdf").addEventListener("click", function(){
    document.getElementById("customerPdfModal").style.display = "none";
});
document.getElementById("customerPdfModal").addEventListener("click", function(e){
    if (e.target === this) this.style.display = "none";
});
</script>';

llxFooter();
$db->close();
