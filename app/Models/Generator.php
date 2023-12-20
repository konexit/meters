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
        $generators = $this->getSpecificGenerator($request->getVar('gUnit'), $request->getVar('gType'), '');
        $columns = ["Назва", "Номер", "Підрозділ", "Адреса", "Коеф", "Паливо", "Каністр", "Тип", "ID", "Стан"];
        $ref = [
            "Назва" => "name", "Номер" => "serialNum", "Підрозділ" => "unit", "Адреса" => "addr", "Коеф" => "coeff",
            "Паливо" => "fuel", "Каністр" => "canister", "Тип" => "type", "ID" => "trade_point_id", "Стан" => "state"
        ];
        header("Content-Type: application/json");
        echo json_encode(["columns" => $columns, "generators" => $generators, "ref" => $ref], JSON_UNESCAPED_UNICODE);
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
        $canisters = $this->getSpecificCanister($request->getVar('unit'), $request->getVar('type'), $request->getVar('status'), '');
        $columns = ["Підрозділ", "Адреса", "Паливо", "Каністр", "Літраж", "Статус"];
        $ref = [
            "Підрозділ" => "unit", "Адреса" => "addr", "Паливо" => "type",
            "Літраж" => "fuel", "Каністр" => "canister", "Статус" => "status"
        ];
        header("Content-Type: application/json");
        echo json_encode(["columns" => $columns, "canisters" => $canisters, "ref" => $ref], JSON_UNESCAPED_UNICODE);
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
            $canisters = $this->getSpecificCanister('', '', '', $idCanister);
            $this->db->table('fuelArea')->update(
                ['canister' => $dataTargetCanister[0]->canister - $backCanistr],
                ['areaId' => $canisters[0]['areaId'], 'type' => $canisters[0]['typeId']]
            );
            echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
        }
    }

    function addGeneratorPokaz($request)
    {
        $consumed = $request->getVar('consumed');
        $genId = $request->getVar('genId');
        $dataTargetGenerator = $this->getSpecificGenerator('', '', $genId);

        header("Content-Type: application/json");
        if (!$dataTargetGenerator || $dataTargetGenerator[0]['fuel'] < $consumed) {
            echo json_encode(["response" => 500, "message" => "Перевірте введені дані та оновіть сторінку. Якщо проблема не вирішилась, зверніться в ІТ відділ"], JSON_UNESCAPED_UNICODE);
        } else {
            $userRights = session("usRights");
            $user = new User();
            if ($userRights == 3) $user->addUserLog(session("mLogin"), ['login' => session("mLogin"), 'message' => "Подав час роботи = " . $consumed . " хв, генератору = " . $dataTargetGenerator[0]['serialNum']]);

            $this->db->table('genaratorPokaz')->insert([
                'date' => $request->getVar('date'),
                'year' => $request->getVar('year'),
                'month' => $request->getVar('month'),
                'day' => $request->getVar('day'),
                'startTime' => $request->getVar('startTime'),
                'endTime' => $request->getVar('endTime'),
                'workingTime' => $request->getVar('workingTime'),
                'consumed' => $consumed,
                'genId' => $genId
            ]);
            $this->db->table('fuelArea')->update(
                ['fuel' => $dataTargetGenerator[0]['fuel']  - $consumed],
                ['areaId' => $dataTargetGenerator[0]['genAreaId'], 'type' => $dataTargetGenerator[0]['genTypeId']]
            );
            echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
        }
    }

    function confirmCanister($request)
    {
        $genId = $request->getVar('idCanister');
        $dataTargetCanister = $this->getSpecificCanister('', '', '', $genId);
        header("Content-Type: application/json");
        if (!$dataTargetCanister) {
            echo json_encode(["response" => 500, "message" => "Перевірте введені дані та оновіть сторінку. Якщо проблема не вирішилась, зверніться в ІТ відділ"], JSON_UNESCAPED_UNICODE);
        } else {
            $this->db->query('UPDATE trackingCanister SET status = 2 WHERE id = ' . $genId);
            $fuelArea = $this->db->query('SELECT * FROM fuelArea WHERE areaId = ' . $dataTargetCanister[0]['areaId'] . ' AND type = ' . $dataTargetCanister[0]['typeId'])->getResultArray();
            if ($fuelArea) {
                $this->db->query('UPDATE fuelArea SET fuel = ROUND(fuel + ' . $dataTargetCanister[0]['fuel'] . ', 2), canister = canister + ' . $dataTargetCanister[0]['canister'] . '
                WHERE type = ' . $dataTargetCanister[0]['typeId'] . ' AND areaId = ' . $dataTargetCanister[0]['areaId']);
            } else {
                $this->db->query('INSERT INTO fuelArea(fuel, canister, areaId, type) 
                VALUES (' . $dataTargetCanister[0]['fuel'] . ', ' . $dataTargetCanister[0]['canister'] . ', ' . $dataTargetCanister[0]['areaId'] . ', ' . $dataTargetCanister[0]['typeId'] . ')');
            }
            echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
        }
    }

    function getGeneratorsAndCanisters()
    {
        header("Content-Type: application/json");
        echo json_encode([
            "generators" =>  $this->getSpecificGenerator(session()->get('usArea'), '', ''),
            "canisters" => $this->getSpecificCanister(session()->get('usArea'), '', 1, '')
        ], JSON_UNESCAPED_UNICODE);
    }

    function getReportGenerator($request)
    {
        $json = json_decode($request->getBody());

        $reports = [];
        foreach ($json->companies as $company) {

            // array_push($reports, $this->createValidJSONForReportMonth($json, $company, $this->getDataReport($company, $json), $this->remnants()));
        }

        $reports = [];
        switch ($json->groupBy) {
            case "month": {
                    foreach ($json->companies as $company) {
                        array_push($reports, $this->createValidJSONForReportMonth($json, $company, $this->getDataReport($company, $json), $this->remnants()));
                    }
                    break;
                }
                // case "day": {
                //         foreach ($json->companies as $company) {
                //             array_push($reports, $this->createValidJSONForReport($json, $company, $this->getDataReport($company, $json)));
                //         }
                //         break;
                //     }
        }


    }


    private function getSpecificCanister($unit, $type, $status, $canisterId)
    {
        $condition = "";
        if ($unit) $condition =  " c.unit = " . $unit;
        if ($type) $condition = ($condition != "") ? $condition . " AND c.type = " . $type : " c.type = " . $type;
        if ($status) $condition = ($condition != "") ? $condition . " AND c.status = " . $status : " c.status = " . $status;
        if ($canisterId) $condition = ($condition != "") ? $condition . " AND c.id = " . $canisterId : " c.id = " . $canisterId;
        if ($condition) $condition = " WHERE " . $condition;
        return $this->db->query("SELECT 
                                    c.id, tg.type, a.addr, a.unit, sc.name AS status, c.date, a.id as areaId, c.type AS typeId,
                                    (
                                        CASE WHEN c.fuel IS NULL THEN 0 ELSE ROUND(c.fuel, 2)
                                            END
                                    ) AS fuel, 
                                    (
                                        CASE WHEN c.canister IS NULL THEN 0 ELSE c.canister
                                            END
                                    ) AS canister
                                FROM 
                                    trackingCanister AS c
                                    LEFT JOIN area AS a ON a.id = c.unit
                                    LEFT JOIN typeGenerator AS tg ON tg.id = c.type
                                    LEFT JOIN statusCanister AS sc ON sc.id = c.status 
                                    " . $condition . " 
                                ORDER BY 
                                    c.date")->getResultArray();
    }

    private function getSpecificGenerator($gUnit, $gType, $genId)
    {
        $condition = "";
        if ($gUnit) $condition =  " g.unit = '" . $gUnit . "' ";
        if ($gType) $condition = ($condition != "") ? $condition . " AND g.type = " . $gType : " g.type = " . $gType;
        if ($genId) $condition = ($condition != "") ? $condition . " AND g.id = " . $genId : " g.id = " . $genId;
        if ($condition) $condition = " WHERE " . $condition;

        return $this->db->query("SELECT 
                                    g.id, g.name, g.serialNum, a.addr, a.unit, g.coeff, t.type, g.state, g.unit AS genAreaId, g.type AS genTypeId,
                                    (
                                        CASE WHEN fu.fuel IS NULL THEN 0 ELSE ROUND(fu.fuel, 2)
                                            END
                                    ) AS fuel, 
                                    (
                                        CASE WHEN fu.canister IS NULL THEN 0 ELSE fu.canister
                                            END
                                    ) AS canister, 
                                    (
                                        CASE WHEN a.trade_point_id = 0 THEN '' ELSE a.trade_point_id 
                                            END
                                    ) AS trade_point_id
                                FROM 
                                    generator AS g
                                    LEFT JOIN area AS a ON a.id = g.unit
                                    LEFT JOIN typeGenerator AS t ON t.id = g.type 
                                    LEFT JOIN fuelArea AS fu ON fu.areaId = g.unit AND fu.type = g.type
                                    " . $condition . " 
                                ORDER BY 
                                    g.state DESC, a.unit")->getResultArray();
    }

    private function getDataReport($company, $json)
    {
        return $this->db->query("SELECT 
                                    gp.date, 
                                    gp.startTime, 
                                    gp.endTime, 
                                    gp.workingTime, 
                                    gp.consumed, 
                                    a.id,
                                    a.addr,
                                    a.unit
                                FROM 
                                    area AS a
                                    JOIN generator AS g ON a.id = g.unit 
                                    JOIN genaratorPokaz AS gp ON g.id = gp.genId 
                                    JOIN companiesAreas AS ca ON a.id = ca.id 
                                WHERE 
                                    a.state = 1 AND g.state = 1  
                                    AND company_1s_code = " . $company->companyId . "  
                                    AND gp.date BETWEEN '" . $json->reportStartDate . "' AND '" . $json->reportEndDate . "'      
                                ORDER BY 
                                    unit ASC, gp.date DESC")->getResultArray();
    }

    private function createValidJSONForReportMonth($json,  $company, $dataReport)
    {

        $report = [
            "typePharmacy" => $company->companyName,
            "tradePoint" => [
                "name" => '',
                "addr" => ''
            ],
            "startDate" => $json->reportStartDate,
            "endDate" => $json->reportEndDate,
            "headers" => [
                "generator" => [
                    [
                        "key" => 1,
                        "name" => "Початок",
                        "keyName" => "startTime"
                    ],
                    [
                        "key" => 2,
                        "name" => "Кінець",
                        "keyName" => "endTime"
                    ],
                    [
                        "key" => 3,
                        "name" => "Всього",
                        "keyName" => "consumed"
                    ]
                ]
            ]
        ];

        $data = [];
        foreach ($dataReport as $row) {
            if (array_key_exists($row["year"], $data)) {
                if (array_key_exists($row["areaId"], $data[$row["year"]])) {
                    if (array_key_exists($row["counterId"], $data[$row["year"]][$row["areaId"]]["counters"])) {
                        array_push($data[$row["year"]][$row["areaId"]]["counters"][$row["counterId"]], [
                            "month" => intval($row["month"]),
                            "consumed" =>  intval($row["consumed"]),
                            "curr_index" =>  intval($row["curr_index"]),
                            "prev_index" => $row["curr_index"] - $row["consumed"]
                        ]);
                    } else {
                        $data[$row["year"]][$row["areaId"]]["counters"][$row["counterId"]] = [
                            [
                                "month" =>  intval($row["month"]),
                                "consumed" =>  intval($row["consumed"]),
                                "curr_index" =>  intval($row["curr_index"]),
                                "prev_index" => $row["curr_index"] - $row["consumed"]
                            ]
                        ];
                    }
                } else {
                    $data[$row["year"]][$row["areaId"]] = [
                        "unit" => $row["unit"],
                        "addr" => $row["addr"],
                        "counters" => [
                            $row["counterId"] => [
                                [
                                    "month" =>  intval($row["month"]),
                                    "consumed" =>  intval($row["consumed"]),
                                    "curr_index" =>  intval($row["curr_index"]),
                                    "prev_index" => $row["curr_index"] - $row["consumed"]
                                ]
                            ]
                        ]
                    ];
                }
            } else {
                $data[$row["year"]] = [
                    $row["areaId"] => [
                        "unit" => $row["unit"],
                        "addr" => $row["addr"],
                        "counters" => [
                            $row["counterId"] => [
                                [
                                    "month" =>  intval($row["month"]),
                                    "consumed" =>  intval($row["consumed"]),
                                    "curr_index" =>  intval($row["curr_index"]),
                                    "prev_index" => $row["curr_index"] - $row["consumed"]
                                ]
                            ]
                        ]
                    ]
                ];
            }
        }

        $report["data"] = $data;
        return $report;
    }
}
