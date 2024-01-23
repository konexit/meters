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
        return $this->db->query("SELECT c.status, c.canister, c.unit 
                                    FROM trackingCanister AS c 
                                    JOIN area AS a ON a.id = c.unit 
                                    WHERE c.id = " . $idCanister . "
                                    GROUP BY a.unit, a.id 
                                    ORDER BY a.unit")->getResultArray();
    }

    function getTypeGenerator()
    {
        return $this->db->query("SELECT * FROM typeGenerator ORDER BY id")->getResultArray();
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
        if (filter_var($request->getVar('isReturning'), FILTER_VALIDATE_BOOLEAN)) {
            $canisterId = $request->getVar('idCanister');
            $trackCanister = $this->getTrackingCanister($canisterId);
            if ($trackCanister[0]["status"] == 4) {
                $this->db->query("INSERT INTO fuelArea (canister) VALUES (canister + " . $trackCanister[0]["canister"] . ") WHERE areaId = " . $trackCanister[0]["unit"] . " LIMIT 1");
            }
            $this->deleteTrackingCanisterById($canisterId);
            header("Content-Type: application/json");
            echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
        } else {
            $idCanister = $request->getVar('idCanister');

            header("Content-Type: application/json");
            if (!$this->getTrackingCanister($idCanister)[0]) {
                echo json_encode(["response" => 500, "message" => "Перевірте введені дані та оновіть сторінку. Якщо проблема не вирішилась, зверніться в ІТ відділ"], JSON_UNESCAPED_UNICODE);
            } else {
                $this->deleteTrackingCanisterById($idCanister);
                echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
            }
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
            echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
        }
    }

    function getGeneratorsAndCanisters()
    {
        header("Content-Type: application/json");
        echo json_encode([
            "generators" =>  $this->getSpecificGenerator(session()->get('usArea')),
            "canisters" => $this->getSpecificCanister(session()->get('usArea'), '', 1, ''),
            "fuelArea" =>  $this->getFuelArea(session()->get('usArea'))[0]['sum']
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
            } else {
                $this->sendCanisterByTrdPointANDLog([
                    'date' => date('Y-m-d'),
                    'canister' => $metaCanistersCount,
                    'fuel' => 0,
                    'type' => 0,
                    'unit' => session("usArea"),
                    'status' => 3
                ], ["login" => session("mLogin")]);
                echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
            }
        } else {
            $dataTargetCanister = $this->getSpecificCanister('', '', 1, $request->getVar('idCanister'));

            header("Content-Type: application/json");
            if (!$dataTargetCanister) {
                echo json_encode(["response" => 500, "message" => "Перевірте введені дані та оновіть сторінку. Якщо проблема не вирішилась, зверніться в ІТ відділ"], JSON_UNESCAPED_UNICODE);
            } else {
                $this->saveCanisterByTrdPointANDLog($dataTargetCanister[0], ["login" => session("mLogin")], $request->getVar('idCanister'));
                echo json_encode(["response" => 200], JSON_UNESCAPED_UNICODE);
            }
        }
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
        }

        return (new Excel)->createReports("generators", ["groupBy" => $json->groupBy, "meters" => $reports]);
    }

    //// ------------------------ ||| ROUT METHODS ||| ---------------------------------------

    //// ------------------------ GENERAL ACTIONS ---------------------------------------
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
        if ($this->getFuelArea($canisterModel['areaId'], $canisterModel['typeId'])[0]['sum']) {
            $this->db->query('UPDATE fuelArea SET fuel = ROUND(fuel + ' . $canisterModel['fuel'] . ', 2), canister = canister + ' . $canisterModel['canister'] . ' WHERE type = ' . $canisterModel['typeId'] . ' AND areaId = ' . $canisterModel['areaId']);
        } else {
            $this->db->query('INSERT INTO fuelArea(fuel, canister, areaId, type) VALUES (' . $canisterModel['fuel'] . ', ' . $canisterModel['canister']  . ', ' . $canisterModel['areaId'] . ', ' . $canisterModel['typeId'] . ')');
        }
        $user->addUserLog($userModel["login"], ['login' => $userModel["login"], 'message' => "Отримано каністри кількість = " . $canisterModel['canister'] . ", палива = " . $canisterModel['fuel'] . (($isTelegram) ? " (telegram)" : "")]);
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

    function findActiveGenerators($area = null, $generatorPK = null)
    {
        $conditionUnit = "";
        $conditionGenPK = "";
        if ($area != null) $conditionUnit = " AND g.unit = " . $area;
        if ($generatorPK != null) $conditionGenPK = " AND g.id = " . $generatorPK;
        return $this->db->query("SELECT 
                                    g.serialNum, tg.type, fa.fuel, g.id, g.name, g.coeff, fa.canister
                                FROM generator AS g
                                JOIN fuelArea AS fa ON g.unit = fa.areaId AND g.type = fa.type
                                JOIN typeGenerator AS tg ON g.type = tg.id
                                WHERE g.state = 1 " . $conditionUnit . $conditionGenPK)->getResultArray();
    }

    function getSpecificGenerator($gUnit, $gType = "", $genId = "")
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

    private function deleteTrackingCanisterById($id)
    {
        $this->db->query('DELETE FROM trackingCanister WHERE id = ' . $id);
    }

    private function getRemnants($company)
    {
        return $this->db->query("SELECT a.id AS areaID, g.id AS genID, g.type, fa.fuel, fa.canister FROM companies AS c
                                    JOIN companiesAreas AS cs ON c.company_1s_code = cs.company_1s_code
                                    JOIN area AS a ON cs.area_id = a.id
                                    JOIN generator AS g ON a.id = g.unit
                                    JOIN fuelArea as fa ON g.unit = fa.areaId AND g.type = fa.type
                                    WHERE c.company_1s_code = " . $company->companyId . "  
                                    ORDER BY a.id, g.id, g.type")->getResultArray();
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
                                    a.state = 1 AND g.state = 1  
                                    AND ca.company_1s_code = " . $company->companyId . "  
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
    //// ------------------------ ||| GENERAL ACTIONS ||| ---------------------------------------
}
