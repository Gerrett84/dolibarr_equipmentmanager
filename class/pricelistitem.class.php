<?php
/* Copyright (C) 2024-2026 Equipment Manager
 * Price List Item - v5.3.0
 */

class PriceListItem
{
    public $db;
    public $error = '';

    public $rowid;
    public $list_type;
    public $position;
    public $fk_product;
    public $label;
    public $description;
    public $unit;
    public $tms;
    public $fk_user_creat;
    public $fk_user_modif;

    // Transient fields from JOIN with llx_product
    public $product_ref;
    public $product_price;
    public $product_tva_tx;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function create($user)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_pricelist_item";
        $sql .= " (list_type, position, fk_product, label, description, unit, tms, fk_user_creat)";
        $sql .= " VALUES (";
        $sql .= "'".$this->db->escape($this->list_type)."'";
        $sql .= ", ".(int)$this->position;
        $sql .= ", ".($this->fk_product > 0 ? (int)$this->fk_product : "NULL");
        $sql .= ", '".$this->db->escape($this->label)."'";
        $sql .= ", ".($this->description !== '' && $this->description !== null ? "'".$this->db->escape($this->description)."'" : "NULL");
        $sql .= ", '".$this->db->escape($this->unit)."'";
        $sql .= ", NOW()";
        $sql .= ", ".(int)$user->id;
        $sql .= ")";

        if ($this->db->query($sql)) {
            $this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX."equipmentmanager_pricelist_item");
            return $this->rowid;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }

    public function update($user)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_pricelist_item SET";
        $sql .= " list_type='".$this->db->escape($this->list_type)."'";
        $sql .= ", position=".(int)$this->position;
        $sql .= ", fk_product=".($this->fk_product > 0 ? (int)$this->fk_product : "NULL");
        $sql .= ", label='".$this->db->escape($this->label)."'";
        $sql .= ", description=".($this->description !== '' && $this->description !== null ? "'".$this->db->escape($this->description)."'" : "NULL");
        $sql .= ", unit='".$this->db->escape($this->unit)."'";
        $sql .= ", tms=NOW()";
        $sql .= ", fk_user_modif=".(int)$user->id;
        $sql .= " WHERE rowid=".(int)$this->rowid;

        if ($this->db->query($sql)) return 1;
        $this->error = $this->db->lasterror();
        return -1;
    }

    public function delete($user)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_pricelist_item WHERE rowid=".(int)$this->rowid;
        if ($this->db->query($sql)) return 1;
        $this->error = $this->db->lasterror();
        return -1;
    }

    public function fetch($id)
    {
        $sql = $this->_buildSelect()." WHERE i.rowid=".(int)$id;
        $res = $this->db->query($sql);
        if ($res) {
            $obj = $this->db->fetch_object($res);
            if ($obj) {
                $this->_setVars($obj);
                return 1;
            }
            return 0;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }

    public function fetchAllByType($list_type)
    {
        $items = array();
        $sql = $this->_buildSelect()." WHERE i.list_type='".$this->db->escape($list_type)."' ORDER BY i.position ASC, i.rowid ASC";
        $res = $this->db->query($sql);
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $item = new PriceListItem($this->db);
                $item->_setVars($obj);
                $items[] = $item;
            }
        }
        return $items;
    }

    public function moveUp($list_type)
    {
        // Find the item with next lower position
        $sql = "SELECT rowid, position FROM ".MAIN_DB_PREFIX."equipmentmanager_pricelist_item";
        $sql .= " WHERE list_type='".$this->db->escape($list_type)."' AND position < ".(int)$this->position;
        $sql .= " ORDER BY position DESC LIMIT 1";
        $res = $this->db->query($sql);
        if ($res && ($prev = $this->db->fetch_object($res))) {
            $this->_swapPositions($this->rowid, $this->position, $prev->rowid, $prev->position);
        }
    }

    public function moveDown($list_type)
    {
        $sql = "SELECT rowid, position FROM ".MAIN_DB_PREFIX."equipmentmanager_pricelist_item";
        $sql .= " WHERE list_type='".$this->db->escape($list_type)."' AND position > ".(int)$this->position;
        $sql .= " ORDER BY position ASC LIMIT 1";
        $res = $this->db->query($sql);
        if ($res && ($next = $this->db->fetch_object($res))) {
            $this->_swapPositions($this->rowid, $this->position, $next->rowid, $next->position);
        }
    }

    private function _swapPositions($id1, $pos1, $id2, $pos2)
    {
        $this->db->query("UPDATE ".MAIN_DB_PREFIX."equipmentmanager_pricelist_item SET position=".(int)$pos2." WHERE rowid=".(int)$id1);
        $this->db->query("UPDATE ".MAIN_DB_PREFIX."equipmentmanager_pricelist_item SET position=".(int)$pos1." WHERE rowid=".(int)$id2);
    }

    public function getNextPosition($list_type)
    {
        $sql = "SELECT MAX(position) as maxpos FROM ".MAIN_DB_PREFIX."equipmentmanager_pricelist_item WHERE list_type='".$this->db->escape($list_type)."'";
        $res = $this->db->query($sql);
        if ($res) {
            $obj = $this->db->fetch_object($res);
            return ($obj->maxpos !== null ? (int)$obj->maxpos + 10 : 10);
        }
        return 10;
    }

    private function _buildSelect()
    {
        return "SELECT i.*, p.ref as product_ref, p.price as product_price, p.tva_tx as product_tva_tx"
            ." FROM ".MAIN_DB_PREFIX."equipmentmanager_pricelist_item i"
            ." LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = i.fk_product";
    }

    public function _setVars($obj)
    {
        $this->rowid       = $obj->rowid;
        $this->list_type   = $obj->list_type;
        $this->position    = $obj->position;
        $this->fk_product  = $obj->fk_product;
        $this->label       = $obj->label;
        $this->description = $obj->description;
        $this->unit        = $obj->unit;
        $this->tms         = $obj->tms;
        $this->fk_user_creat = $obj->fk_user_creat;
        $this->fk_user_modif = $obj->fk_user_modif;
        $this->product_ref   = $obj->product_ref   ?? null;
        $this->product_price = $obj->product_price ?? null;
        $this->product_tva_tx = $obj->product_tva_tx ?? null;
    }
}
