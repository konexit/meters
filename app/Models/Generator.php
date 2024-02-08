<?php

namespace App\Models;

use CodeIgniter\Model;

class Generator extends Model
{
    protected $DBGroup = 'meters';

    //// ------------------------ GENERAL ---------------------------------------
    function getCanisterStatus()
    {
        return $this->db->query("SELECT * FROM statusCanister ORDER BY id")->getResultArray();
    }

    function getGeneratorArea()
    {
        return $this->db->query("SELECT a.unit, a.id 
                                    FROM generator AS g 
                                    JOIN area AS a ON a.id = g.unit 
                                    GROUP BY a.unit, a.id 
                                    ORDER BY a.unit")->getResultArray();
    }

    function getCanisterArea()
    {
        return $this->db->query("SELECT a.unit, a.id 
                                    FROM trackingCanister AS c 
                                    JOIN area AS a ON a.id = c.unit 
                                    GROUP BY a.unit, a.id 
                                    ORDER BY a.unit")->getResultArray();
    }

    function getTrackingCanister($idCanister)
    {
        return $this->db->query("SELECT c.status, c.canister, c.unit, c.fuel, c.type
                                    FROM trackingCanister AS c 
                                    JOIN area AS a ON a.id = c.unit 
                                    WHERE c.id = " . $idCanister . "
                                    GROUP BY a.unit, a.id 
                                    ORDER BY a.unit")->getResultArray();
    }

    function getTypeGenerator($typeId = '')
    {
        $condition = '';
        if ($typeId != '') {
            $condition = ' WHERE id = ' . $typeId;
        }
        return $this->db->query("SELECT * FROM typeGenerator " . $condition . " ORDER BY id")->getResultArray();
    }


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
            if ($this->getFuelArea($request->getVar('gArr'), $request->getVar('gType'))[0]['sum'] == null) {
                $this->db->query('INSERT INTO fuelArea(fuel, canister, areaId, type) 
                                    VALUES (' . 0 . ', ' . 0  . ', ' . $request->getVar('gArr') . ', ' . $request->getVar('gType') . ')');
            }
            $this->db->table('generator')->insert($generator);
        }
    }

    function addCanister($request)
    {
        $telegram = new Telegram();
        $canister = [
            'date' => $request->getVar('sendCanisterDate'),
            'canister' => $request->getVar('countCanistr'),
            'fuel' => $request->getVar('fuelVolume'),
            'type' => $request->getVar('type'),
            'unit' => $request->getVar('unit'),
            'status' => 1
        ];
        $this->db->table('trackingCanister')->insert($canister);
        if ($this->getFuelArea($request->getVar('unit'), $request->getVar('type'))[0]['sum'] == null) {
            $this->db->query('INSERT INTO fuelArea(fuel, canister, areaId, type) 
                                VALUES (' . 0 . ', ' . 0  . ', ' . $request->getVar('unit') . ', ' . $request->getVar('type') . ')');
        }
        $tgUsersByTradePoint = $this->db->query("SELECT telegramChatId FROM user WHERE area = " . $request->getVar('unit') . " AND telegramChatId != ''")->getResultArray();
        $chatIds = [];
        foreach ($tgUsersByTradePoint as $tgUser) {
            array_push($chatIds, $tgUser["telegramChatId"]);
        }
        $telegram->sendMessage($chatIds, 'meters_konex_bot', "<b>Вам були відправлені каністри. Очікуйте їх прибуття.</b>\n" .
            "<i>Кількість: </i>" . $request->getVar('countCanistr') . "\n" .
            "<i>Палива: </i>" . $request->getVar('fuelVolume') . "\n" .
            "<i>Тип: </i>" . $request->getVar('typeFuel'));
    }
    //// ------------------------ ||| GENERAL ||| ---------------------------------------

    //// ------------------------ ROUT METHODS ---------------------------------------
    function getGenerators($request)
    {
        header("Content-Type: application/json");
        echo json_encode([
            "columns" => ["Назва", "Номер", "Підрозділ", "Адреса", "Коеф", "Паливо", "Каністр", "Тип", "Стан"],
            "generators" => $this->getSpecificGenerator($request->getVar('gUnit'), $request->getVar('gType')),
            "ref" => [
                "Назва" => "name", "Номер" => "serialNum", "Підрозділ" => "unit", "Адреса" => "addr", "Коеф" => "coeff",
                "Паливо" => "fuel", "Каністр" => "canister", "Тип" => "type", "Стан" => "state"
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    function getGeneratorsRemnant($request)
    {
        header("Content-Type: application/json");
        echo json_encode([
            "columns" => ["Підрозділ", "Адреса", "Назва генератора", "К-сть каністр", "Палива", "Тип палива"],
            "generators" => $this->findGeneratorsRemnant($request->getVar('gUnit'), $request->getVar('gType')),
            "ref" => [
                "Підрозділ" => "unit", "Адреса" => "addr", "Назва генератора" => "name",
                "К-сть каністр" => "canister", "Палива" => "fuel", "Тип палива" => "type"
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    function getCanisters($request)
    {
        header("Content-Type: application/json");
        echo json_encode([
            "columns" => ["Підрозділ", "Адреса", "Паливо", "Каністр", "Літраж", "Статус"],
            "canisters" => $this->getSpecificCanister($request->getVar('unit'), $request->getVar('type'), $request->getVar('status'), ''),
            "ref" => [
                "Підрозділ" => "unit", "Адреса" => "addr", "Паливо" => "type",
                "Літраж" => "fuel", "Каністр" => "canister", "Статус" => "status"
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    function actionsAdminCanister($request)
    {
        $canisterId = $request->getVar('idCanister');
        $trackCanister = $this->getTrackingCanister($canisterId);

        header("Content-Type: application/json");
        if (filter_var($request->getVar('isReturning'), FILTER_VALIDATE_BOOLEAN)) {
            $this->db->query("INSERT INTO fuelArea (canister) VALUES (canister + " . $trackCanister[0]["canister"] . ") WHERE areaId = " . $trackCanister[0]["unit"] . " LIMIT 1");
        } else {
            if (!$this->getTrackingCanister($canisterId)[0]) {
                echo json_encode(["response" => 500, "message" => "Перевірте введені дані та оновіть сторінку. Якщо проблема не вирішилась, зверніться в ІТ відділ"], JSON_UNESCAPED_UNICODE);
                return;
            }
            $this->saveActionGenerator(3, $trackCanister[0]);
        }
        $this->deleteTrackingCanisterById($canisterId);
        echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
    }

    function actionsConfRefillFuel($request)
    {
        $canisterId = $request->getVar('idCanister');
        $trackCanister = $this->getTrackingCanister($canisterId);
        if ($this->getFuelArea($trackCanister[0]['unit'], $trackCanister[0]['type'])[0]['sum'] == null) {
            $this->db->query('INSERT INTO fuelArea(fuel, canister, areaId, type) 
                                VALUES (' . 0 . ', ' . 0  . ', ' . $trackCanister[0]['unit'] . ', ' . $trackCanister[0]['type'] . ')');
        }
        $this->db->query('UPDATE fuelArea SET fuel = ROUND(fuel + ' . $trackCanister[0]['fuel'] . ', 2) WHERE areaId = ' . $trackCanister[0]['unit'] . ' AND type = ' . $trackCanister[0]['type'] . '  LIMIT 1');
        $this->deleteTrackingCanisterById($canisterId);
        header("Content-Type: application/json");
        echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
    }

    function addGeneratorPokaz($request)
    {
        $consumed = $request->getVar('consumed');
        $genId = $request->getVar('genId');
        $dataTargetGenerator = $this->getSpecificGenerator('', '', $genId);

        header("Content-Type: application/json");
        if (!$dataTargetGenerator || $dataTargetGenerator[0]['fuel'] < $consumed) {
            echo json_encode([
                "response" => 500,
                "message" => "Перевірте введені дані та оновіть сторінку. Якщо проблема не вирішилась, зверніться в ІТ відділ"
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        $this->saveGeneratorPokazANDLog([
            'date' => $request->getVar('date'),
            'year' => $request->getVar('year'),
            'month' => $request->getVar('month'),
            'day' => $request->getVar('day'),
            'startTime' => $request->getVar('startTime'),
            'endTime' => $request->getVar('endTime'),
            'workingTime' => $request->getVar('workingTime'),
            'consumed' => $consumed,
            'genId' => $genId
        ], [
            "login" => session("mLogin")
        ], $dataTargetGenerator[0]);
        $this->saveActionGenerator(
            2,
            [
                "consumed" => $consumed,
                "workingTime" => $request->getVar('workingTime'),
                "typeId" => $dataTargetGenerator[0]['genTypeId'],
                "areaId" => $dataTargetGenerator[0]['genAreaId'],
            ],
            [
                'date' => $request->getVar('date'),
                'year' => $request->getVar('year'),
                'month' => $request->getVar('month'),
                'day' => $request->getVar('day')
            ]
        );
        echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
    }

    function getGeneratorsAndCanisters()
    {
        header("Content-Type: application/json");
        echo json_encode([
            "generators" =>  $this->getSpecificGenerator(session()->get('usArea'), "", "", 1),
            "canisters" => $this->getSpecificCanister(session()->get('usArea'), '', 1, ''),
            "fuelArea" =>  $this->getFuelArea(session()->get('usArea'))[0]['sum'],
            "refill" => $this->getAreaById(session()->get('usArea'))[0]['refill']
        ], JSON_UNESCAPED_UNICODE);
    }

    function actionsUserCanister($request)
    {
        if (filter_var($request->getVar('isReturning'), FILTER_VALIDATE_BOOLEAN)) {
            $totalCountCanisters = $this->getFuelArea(session("usArea"))[0]['sum'];
            $metaCanistersCount = $request->getVar('countCanistBack');

            header("Content-Type: application/json");
            if ($totalCountCanisters == 0 || $totalCountCanisters < $metaCanistersCount) {
                echo json_encode(["response" => 500, "message" => "Перевірте введені дані та оновіть сторінку. Якщо проблема не вирішилась, зверніться в ІТ відділ"], JSON_UNESCAPED_UNICODE);
                return;
            }
            $this->sendCanisterByTrdPointANDLog([
                'date' => date('Y-m-d'),
                'canister' => $metaCanistersCount,
                'fuel' => 0,
                'type' => 0,
                'unit' => session("usArea"),
                'status' => 2
            ], ["login" => session("mLogin")]);
        } else {
            $dataTargetCanister = $this->getSpecificCanister('', '', 1, $request->getVar('idCanister'));

            if (!$dataTargetCanister) {
                echo json_encode(["response" => 500, "message" => "Перевірте введені дані та оновіть сторінку. Якщо проблема не вирішилась, зверніться в ІТ відділ"], JSON_UNESCAPED_UNICODE);
                return;
            }
            $this->saveCanisterByTrdPointANDLog($dataTargetCanister[0], ["login" => session("mLogin")], $request->getVar('idCanister'));
            $this->saveActionGenerator(1, $dataTargetCanister[0]);
        }
        echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
    }

    function refillFuel($request)
    {
        $this->saveRefillByTrdPointANDLog(
            ['login' => session("mLogin"), "areaId" => session("usArea")],
            ["fuelRefill" => $request->getVar('fuelRefill'), "refillType" => $request->getVar('refillType')]
        );
        header("Content-Type: application/json");
        echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
    }

    function getReportGenerator($request)
    {
        $json = json_decode($request->getBody());

        $reports = [];
        switch ($json->groupBy) {
            case "day": {
                    foreach ($json->companies as $company) {
                        array_push($reports, $this->createValidJSONForReportDay($json, $company, $this->getDataReport($company, $json), $this->getRemnants($company)));
                    }
                    break;
                }
            case "month": {
                    foreach ($json->companies as $company) {
                        array_push($reports, $this->createValidJSONForReportMonth($json, $company, $this->getDataReport($company, $json), $this->getRemnants($company)));
                    }
                    break;
                }
            case "total": {
                    array_push($reports, $this->createValidJSONForReportTotal($json, $json->companies));
                    return (new Excel)->createReports("totalGenerators", ["groupBy" => $json->groupBy, "meters" => $reports]);
                }
        }

        return (new Excel)->createReports("generators", ["groupBy" => $json->groupBy, "meters" => $reports]);
    }

    //// ------------------------ ||| ROUT METHODS ||| ---------------------------------------

    //// ------------------------ GENERAL ACTIONS ---------------------------------------
    function saveActionGenerator($type, $targetData, $date = [])
    {
        if (!$date) {
            $date = [
                "date" => date('Y-m-d'),
                "year" => date('Y'),
                "month" => date('m'),
                "day" => date('d')
            ];
        }
        switch ($type) {
            case 1: {
                    $this->db->query("INSERT INTO generatorHistory(canister, fuel, type, areaId, typeGenId,  date, year, month, day) VALUES (" . $targetData['canister'] . ", " . $targetData['fuel'] . ", " . $type . ", " . $targetData['areaId'] . ", " . $targetData['typeId'] . ", '" . $date['date'] . "', " . $date['year'] . ", " . $date['month'] . ", " . $date['day'] . ")");
                    break;
                }
            case 2: {
                    $this->db->query("INSERT INTO generatorHistory(consumed, workingTime, type, areaId,  typeGenId, date, year, month, day) VALUES (" . $targetData['consumed'] . ", " . $targetData['workingTime'] . ", " . $type . ", " . $targetData['areaId'] . ",  " . $targetData['typeId'] . ", '" . $date['date'] . "', " . $date['year'] . ", " . $date['month'] . ", " . $date['day'] . ")");
                    break;
                }
            case 3: {
                    $this->db->query("INSERT INTO generatorHistory(canister, type, areaId, date, year, month, day) VALUES (" . $targetData['canister'] . ", " . $type . ", " . $targetData['unit'] . ", '" . $date['date'] . "', " . $date['year'] . ", " . $date['month'] . ", " . $date['day'] . ")");
                    break;
                }
        }
    }

    function saveGeneratorPokazANDLog($pokazModel, $userModel, $genModel, $isTelegram = false)
    {
        $user = new User();
        $this->db->table('genaratorPokaz')->insert($pokazModel);
        $this->db->table('fuelArea')->update(
            ['fuel' => $genModel['fuel']  - $pokazModel['consumed']],
            ['areaId' => $genModel['genAreaId'], 'type' => $genModel['genTypeId']]
        );
        $user->addUserLog(
            $userModel['login'],
            ['login' => $userModel['login'], 'message' => "Подано час роботи = " . $pokazModel['workingTime'] . " годин, генератору = " . $genModel['serialNum'] . (($isTelegram) ? " (telegram)" : "")]
        );
    }

    function saveCanisterByTrdPointANDLog($canisterModel, $userModel, $trackingCanisterId, $isTelegram = false)
    {
        $user = new User();
        $this->deleteTrackingCanisterById($trackingCanisterId);
        $this->db->query('UPDATE fuelArea 
                                SET fuel = ROUND(fuel + ' . $canisterModel['fuel'] . ', 2), canister = canister + ' . $canisterModel['canister'] . ' 
                                WHERE type = ' . $canisterModel['typeId'] . ' AND areaId = ' . $canisterModel['areaId']);
        $user->addUserLog(
            $userModel["login"],
            ['login' => $userModel["login"], 'message' => "Отримано каністри кількість = " . $canisterModel['canister'] . ", палива = " . $canisterModel['fuel'] . (($isTelegram) ? " (telegram)" : "")]
        );
    }

    function saveRefillByTrdPointANDLog($userModel, $genModel, $isTelegram = false)
    {
        $user = new User();
        $this->db->table('trackingCanister')->insert([
            'date' => date('Y-m-d'),
            'canister' => 0,
            'fuel' => $genModel['fuelRefill'],
            'type' =>  $genModel['refillType'],
            'unit' => $userModel['areaId'],
            'status' => 3
        ]);
        $user->addUserLog($userModel["login"], ['login' => $userModel["login"], 'message' => "Відправленно запит на купівлю палива = " . $genModel['fuelRefill'] . (($isTelegram) ? " (telegram)" : "")]);
    }

    function sendCanisterByTrdPointANDLog($canisterModel,  $userModel, $isTelegram = false)
    {
        $user = new User();
        $this->db->table('trackingCanister')->insert($canisterModel);
        $this->db->query("CALL update_countCanister(?, ?)", array($canisterModel['canister'], $canisterModel['unit']));
        $user->addUserLog($userModel['login'], [
            'login' => $userModel['login'],
            'message' => "Відправлено каністри на повернення кількість = " . $canisterModel['canister'] . (($isTelegram) ? " (telegram)" : "")
        ]);
    }

    function getSpecificGenerator($gUnit, $gType = "", $genId = "", $state = "")
    {
        $condition = "";
        if ($gUnit) $condition =  " g.unit = '" . $gUnit . "' ";
        if ($gType) $condition = ($condition != "") ? $condition . " AND g.type = " . $gType : " g.type = " . $gType;
        if ($genId) $condition = ($condition != "") ? $condition . " AND g.id = " . $genId : " g.id = " . $genId;
        if ($state) $condition = ($condition != "") ? $condition . " AND g.state = " . $state : " g.state = " . $state;
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

    function findGeneratorsRemnant($gUnit = "", $gType = "")
    {
        $condition = "";
        if ($gType) $condition = $condition . " AND g.type = " . $gType;
        if ($gUnit) $condition =  $condition . " AND g.unit = " . $gUnit;
        return $this->db->query("SELECT 
                                    a.unit, a.addr, g.name, fa.canister, tg.type, fa.fuel 
                                FROM generator AS g
                                    JOIN fuelArea AS fa ON g.unit = fa.areaId AND g.type = fa.type 
                                    JOIN typeGenerator AS tg ON g.type = tg.id
                                    JOIN area AS a ON g.unit = a.id
                                WHERE g.state = 1 AND a.state = 1 
                                " . $condition . " 
                                ORDER BY a.unit")->getResultArray();
    }

    function getTypeGenerators()
    {
        return $this->db->query("SELECT * FROM typeGenerator")->getResultArray();
    }

    function getSpecificCanister($unit, $type = "", $status = "", $canisterId = "")
    {
        $condition = "";
        if ($unit) $condition =  " c.unit = " . $unit;
        if ($type) $condition = ($condition != "") ? $condition . " AND c.type = " . $type : " c.type = " . $type;
        if ($status) $condition = ($condition != "") ? $condition . " AND c.status = " . $status : " c.status = " . $status;
        if ($canisterId) $condition = ($condition != "") ? $condition . " AND c.id = " . $canisterId : " c.id = " . $canisterId;
        if ($condition) $condition = " WHERE " . $condition;
        return $this->db->query("SELECT 
                                    c.id, tg.type, a.addr, a.unit, sc.name AS status, c.date, a.id as areaId, c.type AS typeId, sc.id AS statusId,
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
                                    sc.id, c.date")->getResultArray();
    }

    function getFuelArea($unit = "", $typeId = "")
    {
        $condition = "";
        if ($unit) $condition =  " areaId = " . $unit;
        if ($typeId) $condition = ($condition != "") ? $condition . " AND type = " . $typeId : " type = " . $typeId;
        if ($condition) $condition = " WHERE " . $condition;
        return $this->db->query("SELECT SUM(canister) AS sum FROM fuelArea " . $condition)->getResultArray();
    }

    function getAreaById($id)
    {
        return $this->db->query('SELECT * FROM area WHERE id = ' . $id)->getResultArray();
    }

    private function deleteTrackingCanisterById($id)
    {
        $this->db->query('DELETE FROM trackingCanister WHERE id = ' . $id);
    }

    private function getRemnants($company)
    {
        return $this->db->query("SELECT 
                                    a.id AS areaID, g.id AS genID, g.type, fa.fuel, fa.canister 
                                FROM companies AS c
                                    JOIN companiesAreas AS cs ON c.company_1s_code = cs.company_1s_code
                                    JOIN area AS a ON cs.area_id = a.id
                                    JOIN generator AS g ON a.id = g.unit
                                    JOIN fuelArea as fa ON g.unit = fa.areaId AND g.type = fa.type
                                WHERE 
                                    c.company_1s_code = " . $company->companyId . "  
                                ORDER BY 
                                    a.id, g.id, g.type")->getResultArray();
    }

    private function getDataReport($company, $json)
    {
        return $this->db->query("SELECT 
                                    gp.date, 
                                    gp.year,
                                    gp.month,
                                    gp.day,
                                    gp.startTime, 
                                    gp.endTime, 
                                    gp.workingTime, 
                                    gp.consumed, 
                                    a.id AS areaID,
                                    g.id AS genID,
                                    g.name AS genName,
                                    g.serialNum,
                                    tg.type AS typeName,
                                    g.type,
                                    a.addr,
                                    a.unit
                                FROM 
                                    area AS a
                                    JOIN generator AS g ON a.id = g.unit 
                                    JOIN genaratorPokaz AS gp ON g.id = gp.genId 
                                    JOIN companiesAreas AS ca ON a.id = ca.area_id 
                                    JOIN typeGenerator AS tg ON g.type = tg.id
                                WHERE 
                                    ca.company_1s_code = " . $company->companyId . "  
                                    AND gp.date BETWEEN '" . $json->reportStartDate . "' AND '" . $json->reportEndDate . "'
                                ORDER BY 
                                    a.id, g.id, g.type, gp.date, gp.startTime")->getResultArray();
    }

    private function createValidJSONForReportDay($json,  $company, $dataReport, $remnants)
    {
        $report = [
            "companyName" => $company->companyName,
            "company_1s_code" => $company->companyId,
            "startDate" => $json->reportStartDate,
            "endDate" => $json->reportEndDate,
            "headers" => [
                "generator" => [
                    "startDate" => [
                        "key" => 1,
                        "name" => "Початок"
                    ],
                    "endDate" => [
                        "key" => 2,
                        "name" => "Кінець"
                    ],
                    "workingTime" => [
                        "key" => 3,
                        "name" => "Всього часу"
                    ]
                ]
            ]
        ];

        $targetRemnant = [];
        foreach ($remnants as $remnant) {
            $targetRemnant[$remnant["areaID"] . '_' . $remnant["genID"] . '_' . $remnant["type"]] = [
                "fuel" =>  $remnant["fuel"], "canister" =>  $remnant["canister"]
            ];
        }

        $data = [];
        $uniqueKey = '';
        $totalSum = 0;

        foreach ($dataReport as $row) {
            if ($uniqueKey != $row["areaID"] . "_" . $row["genID"] . "_" . $row["type"]) {
                $totalSum = 0;
                $uniqueKey = $row["areaID"] . "_" . $row["genID"] . "_" . $row["type"];
            }

            $totalSum = $totalSum + $row["workingTime"];

            if (array_key_exists($uniqueKey, $data)) {
                if (array_key_exists($row["year"], $data[$uniqueKey]["data"])) {
                    if (array_key_exists($row["month"], $data[$uniqueKey]["data"][$row["year"]])) {
                        if (array_key_exists($row["day"], $data[$uniqueKey]["data"][$row["year"]][$row["month"]])) {
                            $data[$uniqueKey]["data"][$row["year"]][$row["month"]][$row["day"]][] = [
                                "startDate" => $row["startTime"],
                                "endDate" => $row["endTime"],
                                "workingTime" => $row["workingTime"] . " годин",
                            ];
                        } else {
                            $data[$uniqueKey]["data"][$row["year"]][$row["month"]][$row["day"]] = [
                                [
                                    "startDate" => $row["startTime"],
                                    "endDate" => $row["endTime"],
                                    "workingTime" => $row["workingTime"] . " годин",
                                ],
                            ];
                        }
                    } else {
                        $data[$uniqueKey]["data"][$row["year"]][$row["month"]] = [
                            $row["day"] => [
                                [
                                    "startDate" => $row["startTime"],
                                    "endDate" => $row["endTime"],
                                    "workingTime" => $row["workingTime"] . " годин",
                                ],
                            ],
                        ];
                    }
                } else {
                    $data[$uniqueKey]["data"][$row["year"]] = [
                        $row["month"] => [
                            $row["day"] => [
                                [
                                    "startDate" => $row["startTime"],
                                    "endDate" => $row["endTime"],
                                    "workingTime" => $row["workingTime"] . " годин",
                                ],
                            ],
                        ],
                    ];
                }
            } else {
                $data[$uniqueKey] = [
                    "name" => $row["unit"],
                    "addr" => $row["addr"],
                    "generatorName" => $row["genName"],
                    "generatorSerial" => $row["serialNum"],
                    "generatorType" => $row["typeName"],
                    "balanceFuel" => $targetRemnant[$uniqueKey]["fuel"],
                    "balanceCanister" => $targetRemnant[$uniqueKey]["canister"],
                    "sum" => $totalSum,
                    "data" => [
                        $row["year"] => [
                            $row["month"] => [
                                $row["day"] => [
                                    [
                                        "startDate" => $row["startTime"],
                                        "endDate" => $row["endTime"],
                                        "workingTime" => $row["workingTime"] . " годин",
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            $data[$uniqueKey]["sum"] = $totalSum . " годин";
        }


        $report["data"] = $data;
        return $report;
    }

    private function createValidJSONForReportMonth($json,  $company, $dataReport, $remnants)
    {
        $report = [
            "companyName" => $company->companyName,
            "company_1s_code" => $company->companyId,
            "startDate" => $json->reportStartDate,
            "endDate" => $json->reportEndDate,
            "headers" => [
                "generator" => [
                    "workingTime" => [
                        "key" => 1,
                        "name" => "Всього часу"
                    ]
                ]
            ]
        ];

        $targetRemnant = [];
        foreach ($remnants as $remnant) {
            $targetRemnant[$remnant["areaID"] . '_' . $remnant["genID"] . '_' . $remnant["type"]] = [
                "fuel" =>  $remnant["fuel"], "canister" =>  $remnant["canister"]
            ];
        }

        $data = [];
        $uniqueKey = '';
        $totalSum = 0;
        $sumPerMonth = 0;
        $prevYear = '';
        $prevMonth = '';


        foreach ($dataReport as $row) {
            if ($uniqueKey != $row["areaID"] . "_" . $row["genID"] . "_" . $row["type"]) {
                $totalSum = 0;
                $sumPerMonth = 0;
                $uniqueKey = $row["areaID"] . "_" . $row["genID"] . "_" . $row["type"];
            }

            if ($prevYear == $row["year"] && $prevMonth == $row["month"]) {
                $sumPerMonth = $sumPerMonth + $row["workingTime"];
            } else {
                $sumPerMonth = $row["workingTime"];
                $prevYear = $row["year"];
                $prevMonth = $row["month"];
            }

            $totalSum = $totalSum + $row["workingTime"];

            if (array_key_exists($uniqueKey, $data)) {
                if (array_key_exists($row["year"], $data[$uniqueKey]["data"])) {
                    if (array_key_exists($row["month"], $data[$uniqueKey]["data"][$row["year"]])) {
                        $data[$uniqueKey]["data"][$row["year"]][$row["month"]][] = ["workingTime" => $sumPerMonth . " годин"];
                    } else {
                        $data[$uniqueKey]["data"][$row["year"]][$row["month"]] = [
                            ["workingTime" => $sumPerMonth . " годин"],
                        ];
                    }
                } else {
                    $data[$uniqueKey]["data"][$row["year"]] = [
                        $row["month"] => [
                            ["workingTime" => $sumPerMonth . " годин"],
                        ],
                    ];
                }
            } else {
                $data[$uniqueKey] = [
                    "name" => $row["unit"],
                    "addr" => $row["addr"],
                    "generatorName" => $row["genName"],
                    "generatorSerial" => $row["serialNum"],
                    "generatorType" => $row["typeName"],
                    "balanceFuel" => $targetRemnant[$uniqueKey]["fuel"],
                    "balanceCanister" => $targetRemnant[$uniqueKey]["canister"],
                    "sum" => $totalSum,
                    "data" => [
                        $row["year"] => [
                            $row["month"] => [
                                ["workingTime" => $sumPerMonth . " годин"],
                            ],
                        ],
                    ],
                ];
            }

            $data[$uniqueKey]["sum"] = $totalSum . " годин";
        }


        $report["data"] = $data;
        return $report;
    }

    private function createValidJSONForReportTotal($json, $companies)
    {
        $companiesId = [];
        foreach ($companies as $company) {
            array_push($companiesId, $company->companyId);
        }

        $report = [
            "startDate" => $json->reportStartDate,
            "endDate" => $json->reportEndDate,
            "headers" => [
                "generator" => [
                    "company" => [
                        "key" => 0,
                        "name" => "юр. особи"
                    ],
                    "pharmacy" => [
                        "key" => 1,
                        "name" => "Торгова точка"
                    ],
                    "in_fuel" => [
                        "key" => 2,
                        "name" => "к-сть бензину"
                    ],
                    "in_canister" => [
                        "key" => 3,
                        "name" => "к-сть каністр"
                    ],
                    "consumed" => [
                        "key" => 4,
                        "name" => "к-сть бензину"
                    ],
                    "workingTime" => [
                        "key" => 5,
                        "name" => "к-сть годин"
                    ],
                    "out_canister" => [
                        "key" => 6,
                        "name" => "к-сть каністр"
                    ],
                    "restFuel" => [
                        "key" => 7,
                        "name" => "к-сть бензину"
                    ],
                    "restCanister" => [
                        "key" => 8,
                        "name" => "к-сть каністр"
                    ]
                ]
            ]
        ];

        $dataReport = $this->db->query("SELECT 
                                            a.unit, gh.canister, gh.type, gh.workingTime, gh.typeGenId, gh.fuel, gh.consumed, gh.year, gh.month, c.company_name, fa.fuel AS restFuel, fa.canister AS restCanister, a.id AS areaId
                                        FROM generatorHistory as gh
                                            JOIN area AS a ON gh.areaId = a.id
                                            JOIN companiesAreas AS ca ON a.id = ca.area_id
                                            JOIN fuelArea AS fa ON a.id = fa.areaId AND gh.typeGenId = fa.type
                                            JOIN companies AS c ON ca.company_1s_code = c.company_1s_code
                                        WHERE gh.date BETWEEN '" . $json->reportStartDate . "' AND '" . $json->reportEndDate . "'
                                            AND c.company_1s_code IN (" . implode(',', $companiesId) . ")
                                            AND gh.type != 3
                                        ORDER BY gh.date, a.unit")->getResultArray();

        $dataReportReturnedCanister = $this->db->query("SELECT 
                                        a.unit, gh.canister, gh.type, gh.workingTime, fa.type AS typeGenId, gh.fuel, gh.consumed, gh.year, gh.month, c.company_name, fa.fuel AS restFuel, fa.canister AS restCanister, a.id AS areaId
                                    FROM generatorHistory as gh
                                        JOIN area AS a ON gh.areaId = a.id
                                        JOIN companiesAreas AS ca ON a.id = ca.area_id
                                        JOIN fuelArea AS fa ON a.id = fa.areaId
                                        JOIN companies AS c ON ca.company_1s_code = c.company_1s_code
                                    WHERE gh.date BETWEEN '" . $json->reportStartDate . "' AND '" . $json->reportEndDate . "'
                                        AND c.company_1s_code IN (" . implode(',', $companiesId) . ")
                                        AND gh.type = 3
                                    ORDER BY gh.date, a.unit")->getResultArray();

        foreach ($dataReportReturnedCanister as $dataCanister) {
            array_push($dataReport, $dataCanister);
        }

        $resData = [];
        foreach ($dataReport as $data) {
            if (array_key_exists($data['typeGenId'], $resData)) {
                if (array_key_exists($data['year'], $resData[$data['typeGenId']])) {
                    if (array_key_exists($data['month'], $resData[$data['typeGenId']][$data['year']])) {
                        if (array_key_exists($data['areaId'], $resData[$data['typeGenId']][$data['year']][$data['month']])) {
                            $resData[$data['typeGenId']][$data['year']][$data['month']][$data['areaId']] = $this->createValidObjectTotalReportByType($data["type"], $data, $resData[$data['typeGenId']][$data['year']][$data['month']][$data['areaId']]);
                        } else {
                            $resData[$data['typeGenId']][$data['year']][$data['month']][$data['areaId']] = $this->createValidObjectTotalReportByType($data["type"], $data, []);
                        }
                    } else {
                        $resData[$data['typeGenId']][$data['year']][$data['month']] = [
                            $data['areaId'] => $this->createValidObjectTotalReportByType($data["type"], $data, []),
                        ];
                    }
                } else {
                    $resData[$data['typeGenId']][$data['year']] = [
                        $data['month'] => [
                            $data['areaId'] => $this->createValidObjectTotalReportByType($data["type"], $data, []),
                        ],
                    ];
                }
            } else {
                $resData[$data['typeGenId']] = [
                    $data['year'] => [
                        $data['month'] => [
                            $data['areaId'] => $this->createValidObjectTotalReportByType($data["type"], $data, []),
                        ],
                    ],
                ];
            }
        }
        
        $report["data"] = $resData;
        return $report;
    }

    private function createValidObjectTotalReportByType($type, $currentObj, $oldObj)
    {
        if (empty($oldObj)) {
            return [
                "company" => $currentObj["company_name"],
                "pharmacy" => $currentObj["unit"],
                "in_fuel" => $type == 1 ? $currentObj["fuel"] : 0,
                "in_canister" => $type == 1 ? $currentObj["canister"] : 0,
                "consumed" => $type == 2 ? $currentObj["consumed"] : 0,
                "workingTime" => $type == 2 ? $currentObj["workingTime"] : 0,
                "out_canister" => $type == 3 ? $currentObj["canister"] : 0,
                "restFuel" => $currentObj["restFuel"],
                "restCanister" => $currentObj["restCanister"]
            ];
        } else {
            return [
                "company" => $currentObj["company_name"],
                "pharmacy" => $currentObj["unit"],
                "in_fuel" =>  $type == 1 ? $currentObj["fuel"] + $oldObj["in_fuel"] : $oldObj["in_fuel"],
                "in_canister" => $type == 1 ? $currentObj["canister"] + $oldObj["in_canister"] :  $oldObj["in_canister"],
                "consumed" => $type == 2 ? $currentObj["consumed"] + $oldObj["consumed"] : $oldObj["consumed"],
                "workingTime" => $type == 2 ? $currentObj["workingTime"] + $oldObj["workingTime"] : $oldObj["workingTime"],
                "out_canister" => $type == 3 ? $currentObj["canister"] + $oldObj["out_canister"] : $oldObj["out_canister"],
                "restFuel" => $currentObj["restFuel"],
                "restCanister" => $currentObj["restCanister"]
            ];
        }
    }
    //// ------------------------ ||| GENERAL ACTIONS ||| ---------------------------------------
}
