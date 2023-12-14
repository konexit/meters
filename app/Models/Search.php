<?php

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use CodeIgniter\View\Table;

class Search extends Model
{
    protected $DBGroup = 'meters';

    public function getCompaniesAreas()
    {
        $result = [];
        $companiesAreas = $this->db->query("SELECT companiesAreas.area_id, companies.code_okpo, area.trade_point_id
                                            FROM companiesAreas 
                                            JOIN companies ON companiesAreas.company_1s_code = companies.company_1s_code
                                            JOIN area ON area_id = area.id")->getResultArray();
        foreach ($companiesAreas as $companyArea) {
            $result[$companyArea['area_id']] = [
                'trade_point_id' => $companyArea['trade_point_id'],
                'code_okpo' => $companyArea['code_okpo']
            ];
        }
        return $result;
    }

    public function getDepartmentByUserId($userId)
    {
        return  $this->db->query("SELECT area as id FROM user where id=" . $userId)->getFirstRow();
    }

    public function getAllRights()
    {
        return $this->db->query("SELECT rights, id FROM rights")->getResultArray();
    }

    public function getPokazYear()
    {
        return $this->db->query("SELECT year FROM pokaz GROUP BY year ORDER BY year DESC")->getResultArray();
    }

    public function getCounterType()
    {
        return $this->db->query("SELECT Name, id FROM  conterType ORDER BY id DESC")->getResultArray();
    }

    public function getCompanies()
    {
        return $this->db->query("SELECT company_1s_code, company_name FROM companies ORDER BY company_1s_code DESC")->getResultArray();
    }


    public function getCounterArea()
    {
        return $this->db->query("SELECT a.unit, a.id  FROM counter AS c JOIN area AS a ON a.id=c.unit GROUP BY a.unit, a.id ORDER BY a.unit")->getResultArray();
    }

    public function getUnitById($unitID)
    {
        return $this->db->query("SELECT unit FROM area WHERE id=" . $unitID)->getFirstRow();
    }

    public function getCounters($request)
    {
        $table = new Table();
        $query = $this->getCounter($request->getVar('cUnit'), $request->getVar('cType'));
        $table->setTemplate($this->tablShablone());
        $userRights = session("usRights");
        if ($request->getVar('state') == 1) {
            $columns = ["Тип", "Номер", "Назва", "Підрозділ", "Адреса", "Вид лічильника", "ID"];
            $additionalColumns = [];
            if ($userRights != 3) $additionalColumns[] = "Стан";
            $editColumn = ($request->getVar('state') == 1) ? "Добавити" : "Редагувати";
            $finalColumns = array_merge($columns, $additionalColumns, [$editColumn]);
            $ids = [];
            foreach ($query as $row) {
                array_push($ids, intval($row['id']));
            }
            $filledConters = $this->getFilledCounter($ids);
            header("Content-Type: application/json");
            echo json_encode(["columns" => $finalColumns, "counters" => $query, "filledCointerIds" => $filledConters], JSON_UNESCAPED_UNICODE);
            exit();
        }
        if ($userRights == 3) $table->setHeading("Тип", "Номер", "Назва", "Підрозділ", "Адреса", "Вид лічильника", "ID", ($request->getVar('state') == 1) ? "" : "Редагувати");
        else $table->setHeading("Тип", "Номер", "Назва", "Підрозділ", "Адреса", "Вид лічильника", "ID", "Стан", ($request->getVar('state') == 1) ? "" : "Редагувати");
        if (count($query)) {
            foreach ($query as $row) {
                if ($userRights == 3) {
                    $table->addRow(
                        $row['ctype'],
                        $row['ci'],
                        $row['cn'],
                        $row['unit'],
                        $row['addr'],
                        $row['Name'],
                        $row['trade_point_id'],
                        '<button onClick="setCounter(' . $row['id'] . ",'" . $row['unit'] . "'," . "'" . $row['Name'] . "',
                                                     " . "'" . $row['cn'] . "'," . "'" . $row['ci'] . "','" . $row['ctype'] . "',
                                                     '" . $row['spokaz'] . "'" . ', ' . $row['state'] . ')">Редагувати</button>'
                    );
                } else {
                    $table->addRow(
                        $row['ctype'],
                        $row['ci'],
                        $row['cn'],
                        $row['unit'],
                        $row['addr'],
                        $row['Name'],
                        $row['trade_point_id'],
                        $this->createInput($row['state']),
                        '<button onClick="setCounter(' . $row['id'] . ",'" . $row['unit'] . "'," . "'" . $row['Name'] . "',
                                                     " . "'" . $row['cn'] . "'," . "'" . $row['ci'] . "','" . $row['ctype'] . "',
                                                     '" . $row['spokaz'] . "'" . ', ' . $row['state'] . ')">Редагувати</button>'
                    );
                }
            }
        }
        echo "<p></p>";
        echo $table->generate();
    }

    public function getLastPokaz($request)
    {
        $table = new Table();
        $query = $this->getLastPokazDB($request->getVar('counterPK'));
        $table->setTemplate($this->tablShablone());
        $table->setHeading("Дата", "Показник", "Споживання");
        if (count($query)) {
            foreach ($query as $row) {
                $nDat = Time::parse($row['ts']);
                $table->addRow($row['ts'], '<input type="text" id="editP' . $row['id'] . '" onkeyup="isEnter(event,' . $row['id'] . ', \'' . $row["counterId"] . '\')" onkeypress="return event.charCode >= 48 && event.charCode <= 57" value="' . $row['index'] . '" onchange="editPokaz(' . $row['id'] . ', \'' . $row["counterId"] . '\')"/>', '<a id="p' . $row['id'] . '">' . $row['consumed'] . '</a>');
            }
        }
        echo $table->generate();
    }

    public function addPokaz($request)
    {
        $counterPK = $request->getVar('counterPK');
        $dPokazY = $request->getVar('year');
        $dPokazM = $request->getVar('month');
        $pokaz = $request->getVar('pokaz');
        if ($this->checkPokaz($counterPK, $dPokazY . "-" . $dPokazM . "-1", $pokaz)) {
            $savePokaz = $this->addPokazC($counterPK, $pokaz, $dPokazY, $dPokazM);
            if ($savePokaz) {
                $this->recalculation($counterPK);
                $user = new User();
                $user->addUserLog(session("mLogin"), ['login' => session("mLogin"), 'message' => "Додав показник = " . $pokaz . " лічильнику = " . $request->getVar('counter')]);
            }
        } else echo "Упс... Показник лічильника не додано";
    }

    public function getAllUnitDB()
    {
        return $this->db->query("SELECT * FROM area ORDER BY state DESC, unit, trade_point_id DESC")->getResultArray();
    }

    public function addCounter($request)
    {
        $counter = [
            'counterId' => $request->getVar('cNumer'),
            'spokaz' => $request->getVar('spokaz'),
            'ctype' => $request->getVar('ctype'),
            'counterName' => $request->getVar('cName'),
            'unit' => $request->getVar('cArr'),
            'typeC' => $this->getCounterTypeIdByType($request->getVar('cType')),
            'state' => $request->getVar('cState') === 'true' ? true : false
        ];
        if ($request->getVar('edit')) {
            $this->updateCountersById($request->getVar('edit'), $counter);
            $this->recalculation($request->getVar('edit'));
        } else {
            $this->addCounterDB($counter);
        }
    }

    public function getCountersStat($request)
    {
        $table = new Table();
        $table->setTemplate($this->tablShablone());
        $table->setHeading("Точка", "Лічильник", "Назва", "Рік", "1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12");

        $cType = $request->getVar('cType');
        $query = $this->getCountersStatDB($request->getVar('cUnit'), $cType, $request->getVar('cYear'));
        if (count($query)) {
            $tToch = "";
            $cPokaz = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
            $cNum = 0;
            $pYear = 0;
            foreach ($query as $row) {
                if ($tToch != $row['unit'] or $cId != $row['id'] or $pYear != $row['year']) {
                    if ($cNum) {
                        $TabRow = [$tToch, $cNum, $cType, $pYear];
                        for ($i = 0; $i < 12; $i++) {
                            $TabRow[] = $cPokaz[$i];
                            $cPokaz[$i] = 0;
                        }

                        $table->addRow($TabRow);
                        $chartArray[] = [
                            "point"  =>  $TabRow[0],
                            "count" => $TabRow[1],
                            "year" => $TabRow[3],
                            1 => $TabRow[4],
                            2 => $TabRow[5],
                            3 => $TabRow[6],
                            4 => $TabRow[7],
                            5 => $TabRow[8],
                            6 => $TabRow[9],
                            7 => $TabRow[10],
                            8 => $TabRow[11],
                            9 => $TabRow[12],
                            10 => $TabRow[13],
                            11 => $TabRow[14],
                            12 => $TabRow[15]
                        ];
                    }

                    $tToch = $row['unit'];
                    $cType = $row['cn'];
                    $cId = $row['id'];
                    $cNum = $row['ci'];
                    $pYear = $row['year'];
                }
                $cPokaz[$row['month'] - 1] = ($request->getVar('mod') == 1) ? $row['consumed'] : $row['index'];
            }
            if ($cNum) {
                $TabRow = [$tToch, $cNum, $cType, $pYear];
                for ($i = 0; $i < 12; $i++) {
                    $TabRow[] = $cPokaz[$i];
                    $cPokaz[$i] = 0;
                }

                $table->addRow($TabRow);
                $chartArray[] = [
                    "point" => $TabRow[0],
                    "count" => $TabRow[1],
                    "year" => $TabRow[3],
                    1 => $TabRow[4],
                    2 => $TabRow[5],
                    3 => $TabRow[6],
                    4 => $TabRow[7],
                    5 => $TabRow[8],
                    6 => $TabRow[9],
                    7 => $TabRow[10],
                    8 => $TabRow[11],
                    9 => $TabRow[12],
                    10 => $TabRow[13],
                    11 => $TabRow[14],
                    12 => $TabRow[15]
                ];
            }
            $mass = array('table' => "<p></p>" . $table->generate(), 'diagram'  =>  $chartArray, "arrayCount" => count($chartArray));
        } else $mass = array('table' => "<p></p>" . $table->generate(), 'diagram'  =>  [], "arrayCount" => count([]));
        echo json_encode($mass);
    }

    public function editPokaz($request)
    {
        $pokaz = $request->getVar('index');
        $cid = $this->editPokazDB($request->getVar('pid'), $pokaz);
        $this->recalculation($cid);
        $user = new User();
        $user->addUserLog(session("mLogin"), ['login' => session("mLogin"), 'message' => "Змінив показник = " . $pokaz . " лічильнику = " . $request->getVar('counter')]);
        echo $cid;
    }

    public function getPokazByIdAndCounter($request)
    {
        $this->getPokazByIdAndCounterDB($request->getVar('cid'));
    }

    public function getLogConnection()
    {
        $table = new Table();
        $table->setTemplate($this->tablShablone());
        echo "<p></p>";
        $table->setHeading("Дата", "Логин", "Дії");
        echo $table->generate($this->getLogConnectionDB());
    }

    public function usADD($request)
    {
        $user = [
            'name' => $request->getVar('user'),
            'surname' => $request->getVar('surname'),
            'login' => $request->getVar('login'),
            'pass' => $request->getVar('pass'),
            'area' => $request->getVar('unit'),
            'rights' => $request->getVar('rights')
        ];

        header('Content-Type: application/json; charset=utf-8');
        if (!$this->checkUserDB($user)) {
            echo json_encode(['error' => 'Користувач ' . $request->getVar('login') . ' уже існує. Перевірте дані та спробуйте ще раз'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $this->db->table('user')->insert($user);
        echo json_encode(['success' => 'Користувач ' . $request->getVar('login') . ' успішно доданий'], JSON_UNESCAPED_UNICODE);
    }

    public function unADD($request)
    {
        $this->db->transStart();
        $unit = $this->trimSpace($request->getVar('unit'));
        if (!$this->checkUnDB($unit)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Підрозділ ' . $unit . ' уже існує. Перевірте дані та спробуйте ще раз'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $tradePointId = $request->getVar('tradePointId');
        if (
            filter_var($request->getVar('isTradePoint'), FILTER_VALIDATE_BOOLEAN)
            && !$this->checkUnTradePointDB($tradePointId)
        ) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Торгова точка із номером №' . $tradePointId . ' уже існує. Перевірте дані та спробуйте ще раз'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $this->addUnit([
            'addr' => $request->getVar('addr'),
            'tel' => $request->getVar('tel'),
            'unit' => $unit,
            'trade_point_id' => $tradePointId,
            'state' => filter_var($request->getVar('areaState'), FILTER_VALIDATE_BOOLEAN)
        ]);

        if (filter_var($request->getVar('isTradePoint'), FILTER_VALIDATE_BOOLEAN)) {
            $area = $this->db->query("SELECT * FROM area WHERE trade_point_id = " . $tradePointId)->getFirstRow();
            $this->db->table('companiesAreas')->insert(['area_id' => $area->id, 'company_1s_code' => $request->getVar('companyId')]);

            $areaData = $this->db->query("SELECT area_id, code_okpo FROM area
                                                JOIN companiesAreas ON area.id = companiesAreas.area_id
                                                JOIN companies ON companiesAreas.company_1s_code = companies.company_1s_code
                                                WHERE area.trade_point_id = " . $tradePointId)->getFirstRow();

            $newUser = [
                'name' => $unit,
                'surname' => '',
                'login' => $tradePointId,
                'pass' =>  $areaData->code_okpo,
                'area' => $areaData->area_id,
                'rights' => 3
            ];

            if (!$this->checkUserDB($newUser)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Не вдається створити користувача ' . $tradePointId . '. Перевірте дані та спробуйте ще раз. Якщо проблема не вирішилась, зверніться в ІТ відділ'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $this->db->table('user')->insert($newUser);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            header('Content-Type: application/json; charset=utf-8');
            return json_encode(['error' => 'Не вдалось створити підрозділ. Перевірте дані та спробуйте ще раз. Якщо проблема не вирішилась, зверніться в ІТ відділ'], JSON_UNESCAPED_UNICODE);
        } else {
            return json_encode($this->getDepartmentByUnit($unit));
        }
    }

    public function getAllUnit()
    {
        $table = new Table();
        $table->setTemplate($this->tablShablone());
        echo "<p></p>";
        $table->setHeading("Підрозділ", "Адрес", "Телефон", "ID", "Стан", "Дії");
        $query = $this->getAllUnitDB();
        $allCompaniesAreas = $this->db->query("SELECT * FROM companiesAreas")->getResultArray();
        $companyTradePoint = [];
        foreach ($allCompaniesAreas as $tradePont) {
            $companyTradePoint[$tradePont['area_id']] = $tradePont['company_1s_code'];
        }

        if (count($query)) {
            foreach ($query as $row) {
                if (isset($companyTradePoint[$row['id']])) {
                    $table->addRow(
                        $row['unit'],
                        $row['addr'],
                        $row['tel'],
                        $row['trade_point_id'],
                        $row["state"] == 1 ? '<input type="checkbox" checked disabled="">' : '<input type="checkbox" disabled="">',
                        '<button onClick="unitEdit(' . $row['id'] . ',' . "'" . $row['unit'] . "'" . ',' . "'" . rawurlencode($row['addr']) . "'" . ',' . "'" . $row['tel'] . "'" . ', ' . $row['state']  . ' , true, ' . $row['trade_point_id'] . ', ' . $companyTradePoint[$row['id']] . ')">' . "Редагувати" . '</button>'
                    );
                } else
                    $table->addRow(
                        $row['unit'],
                        $row['addr'],
                        $row['tel'],
                        $row['trade_point_id'],
                        $row["state"] == 1 ? '<input type="checkbox" checked disabled="">' : '<input type="checkbox" disabled="">',
                        '<button onClick="unitEdit(' . $row['id'] . ',' . "'" . $row['unit'] . "'" . ',' . "'" . rawurlencode($row['addr']) . "'" . ',' . "'" . $row['tel'] . "'" . ', ' . $row['state']  . ' )">' . "Редагувати" . '</button>'
                    );
            }
            echo $table->generate();
        }
    }

    public function checkPokaz($counterPK, $ts, $pokaz)
    {
        if ($this->db->query("SELECT count(*) AS count FROM pokaz as p WHERE p.cId=" . $counterPK . " AND p.ts>'" . $ts . "' ORDER BY ts LIMIT 1")->getResultObject()[0]->count) {
            $rowBefore = $this->db->query("SELECT p.index FROM pokaz as p WHERE p.cId=" . $counterPK . " AND p.ts<'" . $ts . "' ORDER BY ts DESC LIMIT 1")->getFirstRow();
            $rowAfter = $this->db->query("SELECT p.index FROM pokaz as p WHERE p.cId=" . $counterPK . " AND p.ts>'" . $ts . "' ORDER BY ts LIMIT 1")->getFirstRow();
            if (!empty($rowAfter) && !empty($rowBefore)) {
                if (($rowBefore->index < $pokaz) and $rowAfter->index > $pokaz) return true;
            }
            return false;
        }
        return true;
    }

    public function addPokazC($counterPK, $pokaz, $dPokazY, $dPokazM, $telegram = false)
    {
        $resAr = $this->db->query("SELECT id, `index` FROM pokaz WHERE year = " . $dPokazY . " AND month = " . $dPokazM . " AND cId = " . $counterPK)->getFirstRow();
        if (!empty($resAr)) {
            if (!$telegram) echo "Показник лічильникa за " . $dPokazM . "." . $dPokazY . " був додані раніше (крайній показник лічильника - " . $resAr->index . ")";
            return false;
        } else {
            $this->db->table('pokaz')->insert([
                'cid' => $counterPK,
                'index' => $pokaz,
                'year' => $dPokazY,
                'month' => $dPokazM,
                'ts' => $dPokazY . '-' . $dPokazM . '-01',
                'consumed' => $pokaz - $this->getConsumed($counterPK)
            ]);
            if (!$telegram) echo "Показник лічильникa " . $pokaz . " за " . $dPokazM . "." . $dPokazY . " був успішно додані";
            return true;
        }
    }

    public function unitEDIT($request)
    {
        $this->db->transStart();
        $unitId = $request->getVar('unitID');
        $unit = $this->trimSpace($request->getVar('unit'));
        if (!$this->checkUnDB($unit, $unitId)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Підрозділ ' . $unit . ' уже існує. Перевірте дані та спробуйте ще раз'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $tradePointId = $request->getVar('tradePointId');
        $companyId = $request->getVar('companyId');
        if (!filter_var($request->getVar('isTradePoint'), FILTER_VALIDATE_BOOLEAN)) {
            $this->db->query("DELETE FROM companiesAreas WHERE area_id = " . $unitId);
            $tradePointId = 0;
        } else {
            $area = $this->db->query("SELECT * FROM area WHERE trade_point_id = " . $tradePointId)->getFirstRow();
            if (!isset($area->id)) {
                $this->db->query("DELETE FROM companiesAreas WHERE area_id = " . $unitId);
                $this->db->table('companiesAreas')->insert(['company_1s_code' => $companyId, 'area_id' => $unitId]);
            } else {
                if ($area->id != $unitId) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => 'Торгова точка із номером №' . $tradePointId . ' уже існує. Перевірте дані та спробуйте ще раз'], JSON_UNESCAPED_UNICODE);
                    return;
                } else $this->db->table('companiesAreas')->update(['company_1s_code' => $companyId], ['area_id' => $unitId]);
            }
        }
        echo $this->db->table('area')->update([
            'addr' => $request->getVar('addr'),
            'tel' => $request->getVar('tel'),
            'unit' => $this->trimSpace($request->getVar('unit')),
            'trade_point_id' => $tradePointId,
            'state' => filter_var($request->getVar('areaState'), FILTER_VALIDATE_BOOLEAN)
        ], ['id' => $unitId]);
        $this->db->transComplete();
    }

    public function getAllUser()
    {
        $table = new Table();
        $table->setTemplate($this->tablShablone());
        $table->setHeading("Прізвище", "Ім'я", "Логін", "Права", "Підрозділ", "Телеграм ID", "Дії");
        $query = $this->getAllUserDB();
        echo "<p></p>";
        if (count($query) > 0) {
            foreach ($query as $row) {
                $table->addRow($row['surname'], $row['name'], $row['login'], $row['rights'], $row['unit'], $row['telegramChatId'], '<button onClick="userEdit(' . $row['id'] . ',' . "'" . $row['name'] . "'" . ',' . "'" . $row['surname'] . "'" . ',' . "'" . $row['pass'] . "'" . ',' . "'" . $row['login'] . "'" . ',' . "'" . $row['unit'] . "'" . ',' . "'" . $row['rights'] . "'" . ')">Редагувати</button>');
            }
            echo $table->generate();
        }
    }

    public function userEdit($request)
    {
        $userID = intval($request->getVar('userID'));

        $userRow = $this->db->query("SELECT * FROM user WHERE login='" . $request->getVar('login') . "'")->getFirstRow();
        header('Content-Type: application/json; charset=utf-8');
        if ($userRow == null || $userRow->id == $userID) {
            $this->db->table('user')->update([
                'name' => $request->getVar('user'),
                'surname' => $request->getVar('surname'),
                'login' => $request->getVar('login'),
                'pass' => $request->getVar('pass'),
                'area' => $request->getVar('unit'),
                'rights' => $request->getVar('rights')
            ], ['id' => $userID]);
            echo json_encode(['success' => 'Дані користувач ' . $request->getVar('login') . ' успішно змінено '], JSON_UNESCAPED_UNICODE);
        } else echo json_encode(['error' => 'Користувач ' . $request->getVar('login') . ' уже існує. Перевірте дані та спробуйте ще раз'], JSON_UNESCAPED_UNICODE);
    }

    public function findCountersNotFilled($area = null, $type = null, $counterPK = null)
    {
        $user = new User();
        $last = $user->getCounterDate();
        $prevLast = $user->getPrevLastCounterDate();
        $conditionCounter = "";
        $conditionType = "";
        $conditionUnit = "";
        if ($counterPK != null) $conditionCounter = " AND counter.id = " . $counterPK;
        if ($type != null) $conditionType = " AND counter.typeC = " . $type;
        if ($area != null) $conditionUnit = " AND counter.unit = " . $area;
        $date_last = $last['year'] . "-" . $last['month'] . "-01";
        $prev_last = $prevLast['year'] . "-" . $prevLast['month'] . "-01";
        return $this->db->query("SELECT 
                                    counter.id, counter.counterId, counter.counterName, area.unit, area.addr 
                                FROM counter
                                JOIN area 
                                    ON counter.unit = area.id 
                                JOIN pokaz 
                                    ON counter.id = pokaz.cId
                                WHERE counter.id NOT IN (
                                                            SELECT 
                                                                counter.id 
                                                            FROM counter 
                                                            JOIN pokaz 
                                                                ON counter.id = cId 
                                                            WHERE ts = '" . $date_last . "' " . $conditionUnit . $conditionType . $conditionCounter . " 
                                                            AND counter.state = true 
                                                            GROUP BY counter.id
                                )
                                AND pokaz.ts =  '" . $prev_last . "' " . $conditionUnit . $conditionType . $conditionCounter . " 
                                AND counter.state = true AND area.state = true
                                GROUP BY counter.id")->getResultArray();
    }

    public function findUsersCountersNotFilled()
    {
        $user = new User();
        $last = $user->getCounterDate();
        $prevLast = $user->getPrevLastCounterDate();
        $date_last = $last['year'] . "-" . $last['month'] . "-01";
        $prev_last = $prevLast['year'] . "-" . $prevLast['month'] . "-01";
        return $this->db->query("SELECT 
                                        telegramChatId 
                                FROM user
                                JOIN area ON user.unit = area.id 
                                WHERE telegramChatId != '' 
                                AND telegramState != 'pass' 
                                AND rights = 3 
                                AND area IN (
                                                SELECT 
                                                        counter.unit 
                                                FROM counter 
                                                JOIN pokaz 
                                                ON counter.id = pokaz.cId 
                                                WHERE counter.id NOT IN  (
                                                                            SELECT 
                                                                                counter.id 
                                                                            FROM counter 
                                                                            JOIN pokaz 
                                                                            ON counter.id = cId 
                                                                            WHERE ts = '" . $date_last . "' 
                                                                            AND counter.state = true 
                                                                            GROUP BY counter.id
                                                                        )
                                                AND pokaz.ts = '" . $prev_last . "' AND counter.state = true AND area.state = true
                                                GROUP BY counter.unit)")->getResultArray();
    }

    public function findCountNotFilledCountersOfUser()
    {
        $user = new User();
        $last = $user->getCounterDate();
        $prevLast = $user->getPrevLastCounterDate();
        return $this->db->query("SELECT area.unit, area.addr, area.tel, group_concat(counter.counterId SEPARATOR ', ') as counters
                                        FROM counter 
                                        JOIN area ON counter.unit = area.id 
                                        JOIN pokaz ON counter.id = pokaz.cId
                                          WHERE counter.id NOT IN ( SELECT counter.id FROM counter JOIN pokaz ON counter.id = cId WHERE ts = '" . $last['year'] . "-" . $last['month'] . "-01' AND counter.state = true GROUP BY counter.id )
                                          AND pokaz.ts = '" . date('Y', $prevLast) . "-" . date('m', $prevLast) . "-01' AND counter.state = true AND area.state = true
                                          GROUP BY area.unit, area.addr, area.tel")->getResultArray();
    }

    public function getLastPokazByCounter($counterPK)
    {
        $user = new User();
        $last = $user->getCounterDate();
        return $this->db->query("SELECT pokaz.index FROM pokaz WHERE cId = " . $counterPK . " AND ts < '" . $last['year'] . "-" . $last['month'] . "-01' ORDER BY ts DESC LIMIT 1")->getFirstRow();
    }

    public function getMetadataByChatId($chatId)
    {
        return $this->db->query("SELECT telegramMetadata FROM user WHERE telegramChatId = " . $chatId)->getFirstRow();
    }

    public function getReportCounter($request)
    {
        $json = json_decode($request->getBody());

        $reports = [];
        foreach ($json->countersType as $counter) {
            foreach ($json->companies as $company) {
                array_push($reports, $this->createValidJSONForReport($counter, $json, $company, $this->getDataReport($counter, $company, $json)));
            }
        }

        return (new Excel)->createReports(["meters" => $reports]);
    }

    public function recalculation($counterPK)
    {
        $nPokaz = $this->db->query("SELECT spokaz FROM counter WHERE id=" . $counterPK)->getRowArray()['spokaz'];
        $fquery = $this->db->query("SELECT p.index, p.id FROM pokaz AS p WHERE cId=" . $counterPK . " ORDER BY ts")->getResultArray();
        foreach ($fquery as $row) {
            $date['consumed'] = $row['index'] - $nPokaz;
            $nPokaz = $row['index'];
            $this->db->table('pokaz')->update($date, ['id' => $row['id']]);
        }
    }

    public function getCounterByCounterPK($counterPK)
    {
        return $this->db->query("SELECT * FROM counter WHERE id = " . $counterPK . " ")->getFirstRow();
    }
    
    private function getDataReport($counter, $company, $json)
    {
        return $this->db->query("SELECT 
                                    year, 
                                    month, 
                                    area.unit, 
                                    area.id AS areaId, 
                                    addr, 
                                    counter.id AS counterId, 
                                    consumed, 
                                    pokaz.index AS curr_index 
                                FROM 
                                    pokaz 
                                    JOIN counter ON pokaz.cId = counter.id 
                                    JOIN area ON counter.unit = area.id 
                                    JOIN companiesAreas ON area.id = companiesAreas.area_id 
                                WHERE 
                                    typeC = " . $counter->typeCounterId . " 
                                    AND state = 1 
                                    AND company_1s_code = " . $company->companyId . "  
                                    AND ts BETWEEN '" . $json->reportStartDate . "' AND '" . $json->reportEndDate . "'      
                                ORDER BY 
                                    unit ASC, pokaz.ts DESC")->getResultArray();
    }

    private function createValidJSONForReport($counter, $json,  $company, $dataReport)
    {

        $report = [
            "typePharmacy" => $company->companyName,
            "typeCounter" => $counter->typeCounterName,
            "colorARGB" => [
                "table" => $company->color
            ],
            "startDate" => $json->reportStartDate,
            "endDate" => $json->reportEndDate,
            "headers" => [
                "counter" => [
                    [
                        "key" => 1,
                        "name" => "Попередні",
                        "keyName" => "prev_index"
                    ],
                    [
                        "key" => 2,
                        "name" => "Поточні",
                        "keyName" => "curr_index"
                    ],
                    [
                        "key" => 3,
                        "name" => "Різниця, кВт",
                        "keyName" => "consumed"
                    ]
                ],
                "tradePoint" => [
                    [
                        "key" => 1,
                        "name" => "Торг. точки",
                        "keyName" => "unit"
                    ],
                    [
                        "key" => 2,
                        "name" => "Адреса",
                        "keyName" => "addr"
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

    private function getAllUserDB()
    {
        return $this->db->query("SELECT us.id, us.name, us.surname, us.position, us.pass, us.login, rgt.rights, ar.unit, us.telegramChatId FROM user AS us
                JOIN rights rgt ON rgt.id = us.rights
                JOIN area ar ON ar.id = us.area
                ORDER BY us.surname")->getResultArray();
    }

    private function createInput($state)
    {
        $check = ($state == 1) ? 'checked="checked"' : '';
        return '<input type="checkbox" ' . $check . 'onclick="return false;">';
    }

    private function trimSpace($str)
    {
        return preg_replace('/[ ]{2,}/', ' ', $str);
    }

    private function checkUnDB($unit, $areaId = 0)
    {
        return count($this->db->query("SELECT * FROM area WHERE unit='" . $unit . "' AND id != " . $areaId)->getResultArray()) ? false : true;
    }

    private function checkUnTradePointDB($tradePoint)
    {
        return count($this->db->query("SELECT * FROM area WHERE trade_point_id = " . $tradePoint)->getResultArray()) ? false : true;
    }

    private function addUnit($area)
    {
        $this->db->table('area')->insert($area);
    }

    private function checkUserDB($user)
    {
        return count($this->db->query("SELECT login FROM user WHERE login='" . $user['login'] . "'")->getResultArray()) ? false : true;
    }

    private function getLogConnectionDB()
    {
        return $this->db->query("SELECT date, login, message FROM userlog ORDER BY date DESC")->getResultArray();
    }

    private function getPokazByIdAndCounterDB($cid)
    {
        $cId = $this->db->query("SELECT consumed, id FROM pokaz  WHERE cId=" . $cid . " ORDER BY ts")->getResultArray();
        foreach ($cId as $row) {
            echo "$('#p" . $row['id'] . "').text('" . $row['consumed'] . "');";
        }
    }

    private function editPokazDB($pId, $pokaz)
    {
        $this->db->table('pokaz')->update(['index' => $pokaz], ['id' => $pId]);
        return $this->db->query("SELECT cId FROM pokaz WHERE id=" . $pId)->getFirstRow()->cId;
    }

    private function getCountersStatDB($cUnit, $cType, $cYear)
    {
        $condition = "";
        if ($cType) $condition = "ct.id='" . $cType . "'";
        if ($cUnit) $condition = ($condition) ? $condition . " AND a.id='" . $cUnit . "'" : "a.id='" . $cUnit . "'";
        if ($cYear) $condition = ($condition) ? $condition . " AND p.year='" . $cYear . "'" : "p.year='" . $cYear . "'";
        if ($condition) $condition = " WHERE " . $condition;
        return $this->db->query("SELECT c.id, c.counterId AS ci, c.counterName AS cn, a.addr, a.unit, ct.Name, p.consumed, p.index, p.month, p.index, p.year 
                                      FROM counter AS c 
                                      JOIN area AS a ON a.id=c.unit 
                                      JOIN conterType AS ct ON ct.id=c.typeC 
                                      JOIN pokaz AS p ON p.cId=c.id 
                                      " . $condition . " 
                                      ORDER BY a.unit, p.year, c.id, p.month")->getResultArray();
    }

    private function getCounterTypeIdByType($unit)
    {
        $res = $this->db->query("SELECT id FROM conterType WHERE Name='" . $unit . "'")->getFirstRow();
        if (!empty($res)) return $res->id;
        else return 0;
    }

    private function updateCountersById($counterPK, $counter)
    {
        $this->db->table('counter')->update($counter, ['id' => $counterPK]);
    }

    private function addCounterDB($counter)
    {
        $this->db->table('counter')->insert($counter);
    }

    private function getDepartmentByUnit($unit)
    {
        return $this->db->query("SELECT unit, id FROM area WHERE unit = '" . $unit . "' ORDER BY id DESC")->getFirstRow();
    }

    private function getConsumed($counterPK)
    {
        $query = $this->db->query("SELECT ts, p.index FROM pokaz As p WHERE cid=" . $counterPK . " ORDER BY ts DESC");
        if ($query->getNumRows()) return $query->getFirstRow()->index;
        return $this->db->query("SELECT spokaz FROM counter WHERE id=" . $counterPK)->getFirstRow()->spokaz;
    }

    private function getLastPokazDB($counterPK)
    {
        return $this->db->query("SELECT p.ts, p.index, p.consumed, p.id, c.counterId 
                                 FROM pokaz AS p 
                                 JOIN counter AS c ON p.cId = c.id 
                                 WHERE cId=" . $counterPK . " ORDER BY ts DESC")->getResultArray();
    }

    private function getCounter($cUnit, $cType)
    {
        $condition = "";
        if ($cType) $condition = " ct.id = '" . $cType . "' ";
        if ($cUnit) $condition = ($condition != "") ? $condition . " AND a.id = '" . $cUnit . "' " : " a.id = '" . $cUnit . "' ";
        if (session("usRights") == 3) $condition = ($condition != "") ? $condition . " AND c.state = true " : " a.id = true ";
        if ($condition) $condition = " WHERE " . $condition;
        return $this->db->query("SELECT 
                                    c.id, c.counterId AS ci, c.counterName AS cn, a.addr, a.unit, ct.Name, c.ctype, c.spokaz, c.state, 
                                    (
                                        CASE WHEN a.trade_point_id = 0 THEN '' ELSE a.trade_point_id 
                                            END
                                    ) AS trade_point_id 
                                FROM 
                                    counter AS c 
                                    JOIN area AS a ON a.id = c.unit 
                                    JOIN conterType AS ct ON ct.id = c.typeC " . $condition . " 
                                ORDER BY 
                                    c.state DESC, a.unit")->getResultArray();
    }

    private function getFilledCounter($countersId)
    {
        $user = new User();
        $last = $user->getCounterDate();
        return $this->db->query("SELECT cId FROM pokaz WHERE ts = '" . $last['year'] . "-" . $last['month'] . "-01' AND cId IN (" . implode(", ", $countersId) . ")")->getResultArray();
    }

    private function tablShablone()
    {
        return array(
            'table_open'          => '<table border="2" cellpadding="4" cellspacing="0" align="Center">',

            'heading_row_start'   => '<tr style="color:White;background-color:#006698;font-size:14pt;font-weight:bold;">',
            'heading_row_end'     => '</tr>',
            'heading_cell_start'  => '<th align="left" scope="col">',
            'heading_cell_end'    => '</th>',

            'row_start'           => '<tr style="background-color:#EFF3FC;border-color:#EFF3FB;border-width:2px;border-style:None;font-size:12pt;">',
            'row_end'             => '</tr>',
            'cell_start'          => '<td align="left" >',
            'cell_end'            => '</td>',

            'row_alt_start'       => '<tr style="background-color:White;font-size:12pt;">',
            'row_alt_end'         => '</tr>',
            'cell_alt_start'      => '<td align="left">',
            'cell_alt_end'        => '</td>',

            'table_close'         => '</table>'
        );
    }
}
