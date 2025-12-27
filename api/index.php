<?php
/**
 * Equipment Manager PWA API
 * REST-like endpoints for offline sync
 */

// Prevent direct browser access without proper headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
    $res = include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = include "../../../../main.inc.php";
}
if (!$res) {
    http_response_code(500);
    echo json_encode(['error' => 'Dolibarr environment not found']);
    exit;
}

require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
dol_include_once('/equipmentmanager/class/equipment.class.php');
dol_include_once('/equipmentmanager/class/interventiondetail.class.php');
dol_include_once('/equipmentmanager/class/interventionmaterial.class.php');

// Check authentication
if (!$user->id) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Remove base path to get endpoint
$basePath = '/custom/equipmentmanager/api';
$pos = strpos($path, $basePath);
if ($pos !== false) {
    $endpoint = substr($path, $pos + strlen($basePath));
} else {
    $endpoint = $path;
}
$endpoint = trim($endpoint, '/');
$parts = explode('/', $endpoint);

// Get JSON body for POST/PUT
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Route requests
    switch ($parts[0] ?? 'index') {
        case 'index':
        case '':
            echo json_encode([
                'status' => 'ok',
                'version' => '1.0',
                'user' => $user->login,
                'endpoints' => [
                    'GET /interventions' => 'List user interventions',
                    'GET /intervention/{id}' => 'Get intervention details',
                    'GET /intervention/{id}/equipment' => 'Get equipment for intervention',
                    'GET /detail/{intervention_id}/{equipment_id}' => 'Get service report',
                    'POST /detail/{intervention_id}/{equipment_id}' => 'Save service report',
                    'POST /sync' => 'Batch sync offline changes',
                    'POST /signature/{intervention_id}' => 'Upload signature'
                ]
            ]);
            break;

        case 'interventions':
            handleInterventions($method, $parts, $input);
            break;

        case 'intervention':
            handleIntervention($method, $parts, $input);
            break;

        case 'detail':
            handleDetail($method, $parts, $input);
            break;

        case 'sync':
            handleSync($method, $input);
            break;

        case 'signature':
            handleSignature($method, $parts, $input);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found: ' . $endpoint]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * GET /interventions - List interventions for current user
 */
function handleInterventions($method, $parts, $input) {
    global $db, $user;

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Get interventions where user is assigned or is author
    $sql = "SELECT f.rowid, f.ref, f.datec, f.dateo, f.datee, f.duree, f.fk_statut as status,";
    $sql .= " f.description, f.note_public, f.note_private,";
    $sql .= " s.rowid as socid, s.nom as customer_name, s.address, s.zip, s.town";
    $sql .= " FROM ".MAIN_DB_PREFIX."fichinter f";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc";
    $sql .= " WHERE f.entity = ".(int)$user->entity;

    // Filter by status (draft=0, validated=1, closed=3)
    if (isset($_GET['status'])) {
        $sql .= " AND f.fk_statut = ".(int)$_GET['status'];
    } else {
        // By default, show validated (open) interventions
        $sql .= " AND f.fk_statut IN (0, 1)";
    }

    // Filter by date
    if (isset($_GET['from'])) {
        $sql .= " AND f.dateo >= '".$db->escape($_GET['from'])."'";
    }

    $sql .= " ORDER BY f.dateo DESC";
    $sql .= " LIMIT 100";

    $resql = $db->query($sql);

    if (!$resql) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $db->lasterror()]);
        return;
    }

    $interventions = [];
    while ($obj = $db->fetch_object($resql)) {
        $interventions[] = [
            'id' => (int)$obj->rowid,
            'ref' => $obj->ref,
            'date_creation' => $obj->datec,
            'date_start' => $obj->dateo,
            'date_end' => $obj->datee,
            'duration' => (int)$obj->duree,
            'status' => (int)$obj->status,
            'description' => $obj->description,
            'note_public' => $obj->note_public,
            'customer' => [
                'id' => (int)$obj->socid,
                'name' => $obj->customer_name,
                'address' => $obj->address,
                'zip' => $obj->zip,
                'town' => $obj->town
            ]
        ];
    }

    echo json_encode([
        'status' => 'ok',
        'count' => count($interventions),
        'interventions' => $interventions
    ]);
}

/**
 * GET /intervention/{id} - Get single intervention with equipment
 * GET /intervention/{id}/equipment - Get equipment list
 */
function handleIntervention($method, $parts, $input) {
    global $db, $user;

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $id = (int)($parts[1] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Intervention ID required']);
        return;
    }

    // Get intervention
    $fichinter = new Fichinter($db);
    if ($fichinter->fetch($id) <= 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Intervention not found']);
        return;
    }

    // If requesting equipment list
    if (isset($parts[2]) && $parts[2] === 'equipment') {
        $equipment = getInterventionEquipment($id);
        echo json_encode([
            'status' => 'ok',
            'intervention_id' => $id,
            'equipment' => $equipment
        ]);
        return;
    }

    // Return full intervention with equipment
    $equipment = getInterventionEquipment($id);

    echo json_encode([
        'status' => 'ok',
        'intervention' => [
            'id' => (int)$fichinter->id,
            'ref' => $fichinter->ref,
            'date_creation' => $fichinter->datec,
            'date_start' => $fichinter->dateo,
            'date_end' => $fichinter->datee,
            'duration' => (int)$fichinter->duree,
            'status' => (int)$fichinter->statut,
            'description' => $fichinter->description,
            'note_public' => $fichinter->note_public,
            'note_private' => $fichinter->note_private,
            'signed_status' => (int)$fichinter->signed_status
        ],
        'equipment' => $equipment
    ]);
}

/**
 * Get equipment linked to intervention
 */
function getInterventionEquipment($intervention_id) {
    global $db;

    $sql = "SELECT e.rowid, e.ref, e.label, e.equipment_type, e.serial_number,";
    $sql .= " e.location_floor, e.location_building, e.location_room,";
    $sql .= " l.link_type,";
    $sql .= " d.rowid as detail_id, d.work_done, d.issues_found, d.recommendations,";
    $sql .= " d.notes, d.work_date, d.work_duration";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link l";
    $sql .= " JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e ON e.rowid = l.fk_equipment";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail d";
    $sql .= "   ON d.fk_intervention = l.fk_intervention AND d.fk_equipment = l.fk_equipment";
    $sql .= " WHERE l.fk_intervention = ".(int)$intervention_id;
    $sql .= " ORDER BY e.ref";

    $resql = $db->query($sql);
    $equipment = [];

    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $eq = [
                'id' => (int)$obj->rowid,
                'ref' => $obj->ref,
                'label' => $obj->label,
                'type' => $obj->equipment_type,
                'serial_number' => $obj->serial_number,
                'location' => trim($obj->location_building . ' ' . $obj->location_floor . ' ' . $obj->location_room),
                'link_type' => $obj->link_type,
                'detail' => null
            ];

            if ($obj->detail_id) {
                $eq['detail'] = [
                    'id' => (int)$obj->detail_id,
                    'work_done' => $obj->work_done,
                    'issues_found' => $obj->issues_found,
                    'recommendations' => $obj->recommendations,
                    'notes' => $obj->notes,
                    'work_date' => $obj->work_date,
                    'work_duration' => (int)$obj->work_duration
                ];
            }

            // Get materials for this equipment
            $eq['materials'] = getEquipmentMaterials($intervention_id, $obj->rowid);

            $equipment[] = $eq;
        }
    }

    return $equipment;
}

/**
 * Get materials for equipment in intervention
 */
function getEquipmentMaterials($intervention_id, $equipment_id) {
    global $db;

    $sql = "SELECT rowid, material_name, material_description, quantity, unit, unit_price, total_price, serial_number, notes";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_material";
    $sql .= " WHERE fk_intervention = ".(int)$intervention_id;
    $sql .= " AND fk_equipment = ".(int)$equipment_id;

    $resql = $db->query($sql);
    $materials = [];

    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $materials[] = [
                'id' => (int)$obj->rowid,
                'name' => $obj->material_name,
                'description' => $obj->material_description,
                'quantity' => (float)$obj->quantity,
                'unit' => $obj->unit,
                'unit_price' => (float)$obj->unit_price,
                'total_price' => (float)$obj->total_price,
                'serial_number' => $obj->serial_number,
                'notes' => $obj->notes
            ];
        }
    }

    return $materials;
}

/**
 * GET/POST /detail/{intervention_id}/{equipment_id}
 */
function handleDetail($method, $parts, $input) {
    global $db, $user;

    $intervention_id = (int)($parts[1] ?? 0);
    $equipment_id = (int)($parts[2] ?? 0);

    if (!$intervention_id || !$equipment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Intervention ID and Equipment ID required']);
        return;
    }

    $detail = new InterventionDetail($db);

    if ($method === 'GET') {
        $result = $detail->fetchByInterventionEquipment($intervention_id, $equipment_id);

        if ($result > 0) {
            echo json_encode([
                'status' => 'ok',
                'detail' => [
                    'id' => (int)$detail->id,
                    'intervention_id' => (int)$detail->fk_intervention,
                    'equipment_id' => (int)$detail->fk_equipment,
                    'work_done' => $detail->work_done,
                    'issues_found' => $detail->issues_found,
                    'recommendations' => $detail->recommendations,
                    'notes' => $detail->notes,
                    'work_date' => $detail->work_date ? dol_print_date($detail->work_date, 'dayrfc') : null,
                    'work_duration' => (int)$detail->work_duration
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'ok',
                'detail' => null
            ]);
        }
    } elseif ($method === 'POST' || $method === 'PUT') {
        // Save detail
        $detail->fk_intervention = $intervention_id;
        $detail->fk_equipment = $equipment_id;
        $detail->work_done = $input['work_done'] ?? '';
        $detail->issues_found = $input['issues_found'] ?? '';
        $detail->recommendations = $input['recommendations'] ?? '';
        $detail->notes = $input['notes'] ?? '';
        $detail->work_date = !empty($input['work_date']) ? strtotime($input['work_date']) : null;
        $detail->work_duration = (int)($input['work_duration'] ?? 0);

        $result = $detail->createOrUpdate($user);

        if ($result > 0) {
            echo json_encode([
                'status' => 'ok',
                'message' => 'Detail saved',
                'id' => (int)$detail->id
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to save detail',
                'details' => $detail->errors
            ]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * POST /sync - Batch sync offline changes
 */
function handleSync($method, $input) {
    global $db, $user;

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (!isset($input['changes']) || !is_array($input['changes'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No changes provided']);
        return;
    }

    $results = [];
    $errors = [];

    foreach ($input['changes'] as $change) {
        $type = $change['type'] ?? '';
        $data = $change['data'] ?? [];

        try {
            switch ($type) {
                case 'detail':
                    $detail = new InterventionDetail($db);
                    $detail->fk_intervention = (int)$data['intervention_id'];
                    $detail->fk_equipment = (int)$data['equipment_id'];
                    $detail->work_done = $data['work_done'] ?? '';
                    $detail->issues_found = $data['issues_found'] ?? '';
                    $detail->recommendations = $data['recommendations'] ?? '';
                    $detail->notes = $data['notes'] ?? '';
                    $detail->work_date = !empty($data['work_date']) ? strtotime($data['work_date']) : null;
                    $detail->work_duration = (int)($data['work_duration'] ?? 0);

                    $result = $detail->createOrUpdate($user);

                    $results[] = [
                        'type' => 'detail',
                        'intervention_id' => $detail->fk_intervention,
                        'equipment_id' => $detail->fk_equipment,
                        'success' => $result > 0,
                        'id' => $result > 0 ? $detail->id : null
                    ];
                    break;

                case 'material':
                    // Handle material sync
                    $material = new InterventionMaterial($db);
                    $material->fk_intervention = (int)$data['intervention_id'];
                    $material->fk_equipment = (int)$data['equipment_id'];
                    $material->material_name = $data['material_name'] ?? '';
                    $material->quantity = (float)($data['quantity'] ?? 0);
                    $material->unit = $data['unit'] ?? '';
                    $material->unit_price = (float)($data['unit_price'] ?? 0);
                    $material->notes = $data['notes'] ?? '';

                    if (!empty($data['id'])) {
                        $material->id = (int)$data['id'];
                        $result = $material->update($user);
                    } else {
                        $result = $material->create($user);
                    }

                    $results[] = [
                        'type' => 'material',
                        'success' => $result > 0,
                        'id' => $result > 0 ? $material->id : null
                    ];
                    break;

                default:
                    $errors[] = "Unknown change type: $type";
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    echo json_encode([
        'status' => count($errors) === 0 ? 'ok' : 'partial',
        'results' => $results,
        'errors' => $errors
    ]);
}

/**
 * POST /signature/{intervention_id} - Upload signature
 */
function handleSignature($method, $parts, $input) {
    global $db, $user, $conf;

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $intervention_id = (int)($parts[1] ?? 0);
    if (!$intervention_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Intervention ID required']);
        return;
    }

    if (empty($input['signature'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Signature data required']);
        return;
    }

    // Load intervention
    $fichinter = new Fichinter($db);
    if ($fichinter->fetch($intervention_id) <= 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Intervention not found']);
        return;
    }

    // Decode signature (expects base64 PNG without data:image/png;base64, prefix)
    $signatureData = $input['signature'];
    if (strpos($signatureData, 'base64,') !== false) {
        $signatureData = explode('base64,', $signatureData)[1];
    }
    $signatureImage = base64_decode($signatureData);

    if (!$signatureImage) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid signature data']);
        return;
    }

    // Create signatures directory
    $upload_dir = $conf->fichinter->dir_output . '/' . $fichinter->ref;
    $signatures_dir = $upload_dir . '/signatures';

    if (!file_exists($signatures_dir)) {
        dol_mkdir($signatures_dir);
    }

    // Save signature file
    $timestamp = dol_print_date(dol_now(), '%Y%m%d%H%M%S');
    $filename = $timestamp . '_signature.png';
    $filepath = $signatures_dir . '/' . $filename;

    if (file_put_contents($filepath, $signatureImage) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save signature file']);
        return;
    }

    // Update intervention signed status
    $fichinter->signed_status = 3; // STATUS_SIGNED_RECEIVER_ONLINE
    $fichinter->online_sign_ip = getUserRemoteIP();
    $fichinter->online_sign_name = $input['signer_name'] ?? $user->getFullName($langs);

    $result = $fichinter->update($user);

    if ($result > 0) {
        echo json_encode([
            'status' => 'ok',
            'message' => 'Signature saved',
            'file' => $filename,
            'signed_status' => 3
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to update intervention status',
            'file_saved' => true
        ]);
    }
}
