<?php

namespace App\Models;

use CodeIgniter\Model;

class Generator extends Model
{
    protected $DBGroup = 'meters';

    function modifGenerator($request)
    {
        $generator = [
            'name' => $request->getVar('gName'),
            'coeff' => $request->getVar('gCoeff'),
            'serialNum' => $request->getVar('gSerialNum'),
            'unit' => $request->getVar('gArr'),
            'type' => $request->getVar('gType'),
            'state' => $request->getVar('gState') === 'true' ? true : false
        ];
        if ($request->getVar('gId')) {
            $this->db->table('generator')->update($generator, ['id' => $request->getVar('gId')]);
        } else {
            $this->db->table('generator')->insert($generator);
        }
    }

    function addCanister($request)
    {
        $canister = [
            'date' => $request->getVar('sendCanisterDate'),
            'canister' => $request->getVar('countCanistr'),
            'fuel' => $request->getVar('fuelVolume'),
            'type' => $request->getVar('type'),
            'unit' => $request->getVar('unit'),
            'status' => 1
        ];
        $this->db->table('trackingCanister')->insert($canister);
    }

    function getGenerators($request)
    {
        $generators = $this->getSpecificGenerator($request->getVar('gUnit'), $request->getVar('gType'));
        $columns = ["Назва", "Номер", "Підрозділ", "Адреса", "Коеф", "Паливо", "Каністр", "Тип", "ID", "Стан"];
        $ref = [
            "Назва" => "name", "Номер" => "serialNum", "Підрозділ" => "unit", "Адреса" => "addr", "Коеф" => "coeff",
            "Паливо" => "fuel", "Каністр" => "canister", "Тип" => "type", "ID" => "trade_point_id", "Стан" => "state"
        ];
        header("Content-Type: application/json");
        echo json_encode(["columns" => $columns, "generators" => $generators, "ref" => $ref], JSON_UNESCAPED_UNICODE);
    }

    private function getSpecificGenerator($gUnit, $gType)
    {
        $condition = "";
        if ($gUnit) $condition =  " g.unit = '" . $gUnit . "' ";
        if ($gType) $condition = ($condition != "") ? $condition . " AND g.type = " . $gType : " g.type = " . $gType;
        if ($condition) $condition = " WHERE " . $condition;

        return $this->db->query("SELECT 
                                    g.id, g.name, g.serialNum, a.addr, a.unit, g.coeff, g.fuel, g.canister, t.type, g.state,
                                    (
                                        CASE WHEN a.trade_point_id = 0 THEN '' ELSE a.trade_point_id 
                                            END
                                    ) AS trade_point_id
                                FROM 
                                    generator AS g
                                    JOIN area AS a ON a.id = g.unit
                                    JOIN typeGenerator AS t ON g.type = t.id 
                                    " . $condition . " 
                                ORDER BY 
                                    g.state DESC, a.unit")->getResultArray();
    }

    function getGeneratorArea()
    {
        return $this->db->query("SELECT a.unit, a.id 
                                    FROM generator AS g 
                                    JOIN area AS a ON a.id = g.unit 
                                    GROUP BY a.unit, a.id 
                                    ORDER BY a.unit")->getResultArray();
    }

    function getTypeGenerator()
    {
        return $this->db->query("SELECT * FROM typeGenerator ORDER BY id")->getResultArray();
    }

    function getCanisterArea()
    {
        return $this->db->query("SELECT a.unit, a.id 
                                    FROM trackingCanister AS c 
                                    JOIN area AS a ON a.id = c.unit 
                                    GROUP BY a.unit, a.id 
                                    ORDER BY a.unit")->getResultArray();
    }

    function getCanisterStatus()
    {
        return $this->db->query("SELECT * FROM statusCanister ORDER BY id")->getResultArray();
    }

    function getCanisters($request)
    {
        $canisters = $this->getSpecificCanister($request->getVar('unit'), $request->getVar('type'), $request->getVar('status'));
        $columns = ["Підрозділ", "Адреса", "Паливо", "Каністр", "Літраж", "Статус"];
        $ref = [
            "Підрозділ" => "unit", "Адреса" => "addr", "Паливо" => "type",
            "Літраж" => "fuel", "Каністр" => "canister", "Статус" => "status"
        ];
        header("Content-Type: application/json");
        echo json_encode(["columns" => $columns, "canisters" => $canisters, "ref" => $ref], JSON_UNESCAPED_UNICODE);
    }

    private function getSpecificCanister($unit, $type, $status)
    {
        $condition = "";
        if ($unit) $condition =  " c.unit = " . $unit;
        if ($type) $condition = ($condition != "") ? $condition . " AND c.type = " . $type : " c.type = " . $type;
        if ($status) $condition = ($condition != "") ? $condition . " AND c.status = " . $status : " c.status = " . $status;
        if ($condition) $condition = " WHERE " . $condition;
        return $this->db->query("SELECT 
                                    c.id, tg.type, c.fuel, c.canister, a.addr, a.unit, sc.name AS status
                                FROM 
                                    trackingCanister AS c
                                    LEFT JOIN area AS a ON a.id = c.unit
                                    LEFT JOIN typeGenerator AS tg ON tg.id = c.type
                                    LEFT JOIN statusCanister AS sc ON sc.id = c.status 
                                    " . $condition . " 
                                ORDER BY 
                                    c.date")->getResultArray();
    }

    function canisterWritingOff($request)
    {
        $idCanister = $request->getVar('idCanister');
        $backCanistr = $request->getVar('backCanistr');
        $dataTargetCanister = $this->db->query("SELECT c.canister FROM trackingCanister AS c WHERE c.id = " . $idCanister)->getResultObject();

        header("Content-Type: application/json");
        if (!$dataTargetCanister || $dataTargetCanister[0]->canister < $backCanistr) {
            echo json_encode(["response" => 500, "message" => "Перевірте введені дані та оновіть сторінку. Якщо проблема не вирішилась, зверніться в ІТ відділ"], JSON_UNESCAPED_UNICODE);
        } else {
            if ($dataTargetCanister[0]->canister == $backCanistr) {
                $this->db->table('trackingCanister')->delete(['id' => $idCanister]);
            } else {
                $this->db->table('trackingCanister')->update(
                    ['canister' => $dataTargetCanister[0]->canister - $backCanistr],
                    ['id' => $idCanister]
                );
            }
            echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
        }
    }
}
