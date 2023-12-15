<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?= "<script> const bUrl='" . base_url() . "';</script>"; ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            listenerRemoveAllNonDigit(['#fuelVolume', '#countCanistr', '#countCanistrBack']);
        });
    </script>
    <?php
    foreach (array("style", "datepicker") as $filename) {
        echo '<link type="text/css" rel="stylesheet" href="' . base_url() . '/assets/css/' . $filename . '.css?ver=' . STATIC_FILES_VERSION . '"></link>';
    }
    foreach (array("jquery", "scrollTo-min",  "global", "datepicker", "google", "chart") as $filename) {
        echo '<script src="' . base_url() . '/assets/js/' . $filename . '.js?ver=' . STATIC_FILES_VERSION . '"></script>';
    }

    $departmentOptions = '';
    foreach ($department as $arr) {
        if (isset($companiesAreas[$arr['id']])) $departmentOptions .= '<option data-value="' . $arr['id'] . '"  data-text="' . trim($arr['unit']) . '" data-codeokpo=' . $companiesAreas[$arr['id']]['code_okpo'] . '  data-tradePointId=' . $companiesAreas[$arr['id']]['trade_point_id'] . '>' . trim($arr['unit']) . '</option>';
        else $departmentOptions .= '<option data-value="' . $arr['id'] . '"  data-text="' . trim($arr['unit']) . '">' . trim($arr['unit']) . '</option>';
    }

    $typeG = '';
    foreach ($typeGenerator as $type) {
        $typeG .= '<option data-value="' . $type['id'] . '"  data-text="' . trim($type['type']) . '">' . trim($type['type']) . '</option>';
    }

    $allArea = '';
    $allArea .=  '<option data-value="0" data-text="Усі">Усі</option>';
    foreach ($counterArea as $arr) {
        $allArea .=  '<option data-value="' . $arr['id'] . '" data-text="' . trim($arr['unit']) . '">' . trim($arr['unit']) . '</option>';
    }
    ?>
    <title>Cистема заявок "Конекс"</title>
</head>

<body>
    <div class="head" id="topPan">
        <div class="headContent">
            <div id="afterLogin">
                <?
                if (session('usId') != false) {
                    echo "<script> usId = " . session('usId') . "; usArea = " . session('usArea') . "; usAreaName='" . $usAreaName->unit . "'; usRights = '" . session('usRights') . "'; </script>";
                    echo "<h2>Користувач - " . session('usName') . "</h2>";
                }
                ?>
                <div id="exit">
                    <form action="/logout" method="post">
                        <button type="submit">Вихід</button>
                    </form>
                </div>

                <div id="menu">
                    <ul class="menu">
                        <?
                        if (session('usRights') == 3) {
                            echo "<li><a  class='menItem' id='Pokaz' onclick='mCaunter(" . '"' . "#Pokaz" . '"' . ")'>Показники</a></li>
                                  <li><a class='menItem' id='mAreaGenerator' onclick='mAreaGenerator()'>Генератори</a></li>                                                              
                                <script>
                                $('#mCounter').show();
                                </script>";
                        }

                        if (session('usRights') == 1) {
                            echo "<li><a class='menItem' id='mAdmin' onclick=''>Адміністрування</a>                   
                                            <ul>                       
                                                <li><a  class='menItem' id='allUs' onclick='mallUser()'>Користувачі</a></li>
                                                <li><a  class='menItem' id='allArea' onclick='mallUnit()'>Підрозділи</a></li>
                                                <li><a  class='menItem' id='aUser' onclick='maddUser(0)'>Додати користувача</a></li>
                                                <li><a  class='menItem' id='aArea' onclick='maddUnit()'>Додати підрозділ</a></li>                                                                
                                                <li><a  class='menItem' id='aLog' onclick='mLog()'>Лог</a></li>                                
                                            </ul>
                                        </li>
                                <script>
                                $('#mAdmin').show();
                                </script>";
                        }

                        $Smenu = "";
                        if (session('usRights') != 3) {
                            $Smenu = "
                                    <li><a  class='menItem' id='aCounter' onclick='mCountAdd()'>Управління</a></li>
                                    <li><a  class='menItem' id='Statistic' onclick='mCaunter(" . '"' . "#Statistic" . '"' . ")'>Статистика</a></li>
                                    <li><a  class='menItem' id='report' onclick='reportCounter()'>Звіт</a></li>";
                        }
                        if (session('usRights') == 1 &&  session('usRights') != 5) {
                            echo "<li><a class='menItem' id='mCounter' onclick=''>Лічильники</a>
                                        <ul>                                            
                                            <li><a  class='menItem' id='Pokaz' onclick='mCaunter(" . '"' . "#Pokaz" . '"' . ")'>Показники</a></li>" . $Smenu . "                                
                                        </ul>
                                    </li>                                
                                <script>
                                $('#mCounter').show();
                                </script>";
                        }

                        if (session('usRights') == 2) {
                            echo "<li><a  class='menItem' id='Pokaz' onclick='mCaunter(" . '"' . "#Pokaz" . '"' . ")'>Показники</a></li>" . $Smenu . "                                
                              <script>
                               $('#mCounter').show();
                               </script>";
                        }

                        if (session('usRights') != 3) {
                            echo "<li><a class='menItem' id='mGenerator' onclick=''>Генератори</a>
                                            <ul>                
                                                <li><a  class='menItem' id='mGeneratorManage' onclick='mGeneratorManage()'>Управління</a></li>                          
                                                <li><a  class='menItem' id='mCanisterTracking' onclick='mCanisterTracking()'>Відстежування</a></li>                          
                                                <li><a  class='menItem' id='mGeneratorReport' onclick='mGeneratorReport()'>Звіт</a></li>                               
                                            </ul>
                                        </li>                                
                                    <script>
                                    $('#mGenerator').show();
                                    </script>";
                        }
                        if (session('usRights') == 1) {
                            echo '<li><a  class="menItem" id="changePass" onclick="mchangePass()">Змінити пароль</a></li>';
                        }
                        ?>
                    </ul>
                    <p></p>
                </div>
                <div id="adminPanel">
                    <div id="log">
                    </div>
                    <div id="allUser">
                    </div>
                    <div id="allUnit">
                    </div>
                    <div id="addUnit">
                        <h3>
                            <p>Назва: <input type="text" id="unit" title="Ліміт 30 символів" maxlength="30" /> </p>
                            <p>Адреса: <input type="text" id="unADDR" title="Ліміт 80 символів" maxlength="80" /> </p>
                            <p>Телефон: <input type="text" id="tel" title="Ліміт 100 символів" maxlength="100" /> </p>
                            <p style="margin-bottom: 25px;font-size: 20px;">Стан підрозділу: <input type="checkbox" name="areaState" id="areaState" checked="checked"></p>
                            <p style="margin-bottom: 25px;font-size: 20px;">Добавити аптеку: <input id="isTradePoint" type="checkbox" onclick="tradePointFormStatus(this.checked)" /></p>
                            <div id="tradePointForm" style="display: none">
                                <p>ID аптеки: <input id="tradePointId" type="number" /></p>
                                <p>Власник аптеки: <select id="companyId" style="width: 100%;">
                                        <?
                                        foreach ($companies as $company) {
                                            echo "<option value=" . $company['company_1s_code'] . ">" . $company['company_name'] . "</option>";
                                        }
                                        ?>
                                    </select>
                                </p>
                            </div>
                            <button style="font-size: 14pt;" id="confirmUn" onclick="">Додати</button>
                        </h3>
                    </div>
                    <div id="addUser">
                        <h3>
                            <p>Ім'я: <input type="text" id="usName" title="Ліміт 25 символів" maxlength="25" /> </p>
                            <p>Прізвище: <input type="text" id="usSurname" title="Ліміт 25 символів" maxlength="25" /> </p>
                            <p>Логін: <input type="text" id="usLogin" title="Ліміт 15 символів" maxlength="15" /> </p>
                            <p>Пароль: <input type="password" id="usPass" title="Ліміт 20 символів" maxlength="20" /> </p>
                            <p style="margin-bottom: 25px;font-size: 20px;">Підрозділ: <input id="inputUnitUs" list="unitUs" placeholder="Виберіть підрозділ" onChange="companyUserDefaulValues()">
                                <datalist class="unit" name="area" id="unitUs">
                                    <?
                                    echo $departmentOptions;
                                    ?>
                                </datalist>
                            </p>
                            <p>Права:
                                <select name="area" id="RightsUs">
                                    <?
                                    foreach ($allRights as $arr) {
                                        echo "<option value=" . $arr['id'] . ">" . $arr['rights'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </p>
                            <div id="managerTradePointForm" style="display: none">
                                <p style="margin-bottom: 25px;font-size: 20px;">Завідувач аптеки:
                                    <input id="isManagerTradePoint" type="checkbox" onclick="managerTradePointFormStatus()" />
                                </p>
                            </div>
                            <button style="font-size: 14pt;" id="confirmUs" onclick="">Підтвердити</button>
                        </h3>
                    </div>
                </div>
                <div class="table" id="table">
                </div>
                <div id="cPass">
                    <h3>
                        <p>Старий пароль: <input id="oldPw" name="oldPw" type="password" title="Ліміт 20 символів" maxlength="20" /> </p>
                        <p>Новий пароль: <input id="newPw" name="newPw" type="password" title="Ліміт 20 символів" maxlength="20" /> </p>
                        <p>Підтвердіть пароль: <input id="confPw" name="confPw" type="password" title="Ліміт 20 символів" maxlength="20" /> </p>
                        <button id="changePass" onclick="cPass()">Змінити</button>
                        <div id="passError"></div>
                    </h3>
                </div>
                <div id="counterList" align="center">
                    <a class="bcType">Місце розташування лічильника: <input id="inputCUnit" list="cUnit" onchange="getCounters()" placeholder="Усі">
                        <datalist name="cUnit" class="cUnit" id="cUnit">
                            <?
                            echo $allArea;
                            ?></datalist></a>
                    <a class="bcType">Вид лічильника: <select name="cType" class="cType" id="hcType" onchange="getCounters()">
                            <option value="">Усі</option>
                            <?
                            foreach ($counterType as $arr) {
                                echo "<option value='" . $arr['id'] . "'>" . $arr['Name'] . "</option>";
                            }
                            ?>
                        </select></a>
                    <a class="bcType" id="cYear">Рік: <select name="hcYear" class="hcYear" id="hcYear" onchange="getCounters()">
                            <option value="">Усі</option>
                            <?
                            foreach ($pokazYear as $arr) {
                                echo "<option value='" . $arr['year'] . "'>" . $arr['year'] . "</option>";
                            }
                            ?>
                        </select></a>
                </div>
                <div id="counter"></div>
                <div id="inputPokaz">
                    <p style="font-size: 14pt;"> <?
                                                    echo "<script> var pMonth='" . $month . "'; var pYear='" . $year . "';</script>";
                                                    ?>
                        Лічильник №<a id='cNum'></a>
                        <input id="counterPokaz" type="text" placeholder="Введіть показник тут" onkeypress="return event.charCode >= 48 && event.charCode <= 57" />
                        <?
                        if (session('usRights') == 3) {
                            echo " за " . $month . " місяць " . $year . " рік";
                        } else {
                            echo " за <select type='text' id='pMonch'>";
                            for ($i = 1; $i <= 12; $i++) {
                                echo "<option>" . $i . "</option>";
                            }
                            echo "</select> місяць <select type='text' id='pYear'>";
                            for ($i = $year; $i >= 2010; $i--) {
                                echo "<option>" . $i . "</option>";
                            }
                            echo "</select> рік ";
                        } ?>
                    </p>
                    <button id="enterPokaz">Додати</button>
                    <div id="pokazEdit"></div>
                </div>
                <div id="setCounters">
                    <p></p>
                    <button id="addCounterBtn" onclick="setCounter(0)">Додати лічильник</button>
                    <p></p>
                    <div id="allCount"></div>
                </div>
                <div id="addCounters">
                    <h3>Місце розташування лічильника:
                        <input id="inputCArr" list="cArr" placeholder="Виберіть підрозділ">
                        <datalist name="cArr" class="unit" id="cArr">
                            <?
                            echo $departmentOptions;
                            ?>
                        </datalist>
                    </h3>
                    <p>Номер лічильника: <input type="text" id="cNumer" placeholder="н/д" title="Ліміт 30 символів" maxlength="30"></p>
                    <p>Вид: <input name="cnType" class="cnType" id="cnType" title="Ліміт 30 символів" maxlength="30"></p>
                    <p>Назва лічильника: <input type="text" id="cName" title="Ліміт 50 символів" maxlength="50"></p>
                    <p>Тип лічильника:
                        <select name="cType" class="cType" id="cType">
                            <option value="">Усі</option>
                            <?
                            foreach ($counterType as $arr) {
                                echo "<option value='" . $arr['Name'] . "'>" . $arr['Name'] . "</option>";
                            }
                            ?>
                        </select>
                    </p>
                    <p>Початковий показник: <input type="number" name="sPokaz" class="sPokaz" id="sPokaz"></p>
                    <p>Стан лічильника:<input type="checkbox" name="cState" id="cState"></p>
                    <p></p>
                    <button id="buttCounter" onclick="addCounter()">Додати</button>
                </div>
                <div id="countStat" align="center">
                    <form style="align: center" id="IndexСonsumption" onclick="getStatistic($('.radioBox[checked]').val())">
                        <h3 style="color: brown">
                            <input name="checkBox" class="radioBox" type="radio" value='0' checked="true" />Показники
                            <input name="checkBox" class="radioBox" type="radio" value='1' />Споживання
                        </h3>
                    </form>
                    <div id="cStat"></div>
                    <!--Вывод диаграммы показатели и потребление-->
                    <div id="chart" style="width: 800px; height: 400px; align:center;"></div>
                </div>
                <div id="reportCounter">
                    <p></p>

                    <form id="formReportCounter" class="form-report-counter" onsubmit="getReportCounter(event)">
                        <label for="reportStartDate" class="label-report-counter">Початкова дата:</label>
                        <input type="date" id="reportStartDate" name="reportStartDate" class="input-report-counter" required>

                        <label for="reportEndDate" class="label-report-counter">Кінцева дата:</label>
                        <input type="date" id="reportEndDate" name="reportEndDate" class="input-report-counter" required>

                        <label for="counterType" class="label-report-counter">Вид лічильника:</label>
                        <select id="counterType" name="counterType" multiple="multiple" class="select-report-counter" required>
                            <?
                            foreach ($counterType as $arr) {
                                echo "<option value='" . $arr['id'] . "'>" . $arr['Name'] . "</option>";
                            }
                            ?>
                        </select>

                        <div id="pharmacyType">
                            <table class="company-table">
                                <tr>
                                    <th>Компанії</th>
                                    <th>Колір звіту</th>
                                </tr>
                                <?
                                foreach ($companies as $company) {
                                    echo "<tr class='company-color' data-companyid=" . $company['company_1s_code'] . ">
                                            <td>
                                                <input type='checkbox'>
                                                <label>" . $company['company_name'] . "</label>
                                            </td>
                                            <td>
                                                <input type='color' class='input-color'>
                                            </td>
                                        </tr>";
                                }
                                ?>
                            </table>

                        </div>

                        <div style="text-align: center;margin-top: 15px;">
                            <input id="btnReportCounter" name="btnReportCounter" type="submit" class="btn-report-counter" value="Отримати звіти" />
                        </div>
                    </form>
                </div>
                <div id="generatorList" align="center">
                    <a class="bcType">Місце розташування генератора: <input id="inputGUnit" list="gUnit" onchange="getGenerators()" placeholder="Усі">
                        <datalist name="gUnit" class="gUnit" id="gUnit">
                            <?
                            echo '<option data-value="0" data-text="Усі">Усі</option>';
                            foreach ($generatorArea as $arr) {
                                echo '<option data-value="' . $arr['id'] . '" data-text="' . trim($arr['unit']) . '">' . trim($arr['unit']) . '</option>';
                            }
                            ?></datalist></a>
                    <a class="bcType">Тип генератора: <input id="inputGType" list="gType" onchange="getGenerators()" placeholder="Усі">
                        <datalist name="gType" class="unit" id="gType">
                            <?
                            echo $typeG;
                            ?></datalist></a>
                </div>
                <div id="setGenerator">
                    <p></p>
                    <button id="addGenetarorBtn" onclick="setGenerator(0)">Додати генератор</button>
                    <p></p>
                </div>
                <div id="addGenerator">
                    <h3>Місце розташування:
                        <input id="inputGArr" list="gArr" placeholder="Виберіть підрозділ">
                        <datalist name="gArr" class="unit" id="gArr">
                            <?
                            echo $departmentOptions;
                            ?>
                        </datalist>
                    </h3>
                    <p>Серійний номер: <input type="text" id="gSerialNum" placeholder="н/д" title="Ліміт 30 символів" maxlength="30"></p>
                    <p>Назва: <input type="text" id="gName" title="Ліміт 50 символів" maxlength="50"></p>
                    <p>Кофіцієнт споживання (літр/годин): <input type="number" id="gCoeff"></p>
                    <h3>Тип палива:
                        <input id="inputGenType" list="gType" placeholder="Виберіть тип палива">
                        <datalist name="gType" class="unit" id="gType">
                            <?
                            echo $typeG;
                            ?>
                        </datalist>
                    </h3>
                    <p>Стан:<input type="checkbox" name="gState" id="gState"></p>
                    <p></p>
                    <button id="buttGenerator" onclick="addGenerator()">Додати</button>
                </div>
                <div id="generator">
                    <p></p>
                </div>
                <div id="canisterList" align="center">
                    <a class="bcType">Місце розташування генератора: <input id="inputCanisterUnit" list="canisterUnit" onchange="getCanisters()" placeholder="Усі">
                        <datalist name="canisterUnit" class="canisterUnit" id="canisterUnit">
                            <?
                            echo '<option data-value="0" data-text="Усі">Усі</option>';
                            foreach ($generatorArea as $arr) {
                                echo '<option data-value="' . $arr['id'] . '" data-text="' . trim($arr['unit']) . '">' . trim($arr['unit']) . '</option>';
                            }
                            ?></datalist></a>
                    <a class="bcType">Тип палива: <input id="inputCanisterType" list="canisterType" onchange="getCanisters()" placeholder="Усі">
                        <datalist name="canisterType" class="canisterType" id="canisterType">
                            <?
                            echo $typeG;
                            ?>
                        </datalist></a>
                    <p></p>
                    <a class="bcType">Статуc каністр: <input id="inputCanisterStatus" list="canisterStatus" onchange="getCanisters()" placeholder="Усі">
                        <datalist name="canisterStatus" class="canisterStatus" id="canisterStatus">
                            <?
                            echo '<option data-value="0" data-text="Усі">Усі</option>';
                            foreach ($canisterStatus as $status) {
                                echo '<option data-value="' . $status['id'] . '"  data-text="' . trim($status['name']) . '">' . trim($status['name']) . '</option>';
                            }
                            ?></datalist></a>
                </div>
                <div id="addCanister">
                    <h3>
                        <label for="sendCanisterDate" class="label-canister-send">Дата відправки:
                            <input type="date" id="sendCanisterDate" name="sendCanisterDate" class="input-canister-send" required></label>
                    </h3>
                    <h3>Тип палива: <input id="inputAddCanisterType" list="addCanisterType" placeholder="Виберіть тип палива">
                        <datalist name="addCanisterType" class="unit" id="addCanisterType">
                            <?
                            echo $typeG;
                            ?></datalist>
                    </h3>
                    <h3>Місце призначення: <input id="inputAddCUnit" list="addCanisterUnit" onchange="getCounters()" placeholder="Усі">
                        <datalist name="addCanisterUnit" class="addCanisterUnit" id="addCanisterUnit">
                            <?
                            echo $departmentOptions;
                            ?></datalist>
                    </h3>
                    <h3>Кількість каністр: <input type="number" class="positiveNumber" id="countCanistr"></h3>
                    <h3>Обєм палива: <input type="number" name="fuelVolume" class="fuelVolume" id="fuelVolume"></h3>
                    <h3><button id="buttCanister" onclick="addCanister()">Відправити</button></h3>
                </div>
                <div id="setCanister">
                    <p></p>
                    <button id="addCanisterBtn" onclick="setCanister()">Відправити каністри</button>
                    <p></p>
                </div>
                <div id="canister">
                    <p></p>
                </div>
                <div class="overlay" id="overlayWritingOff"></div>
                <div class="modal" id="modalWritingOff">
                    <div class="modal-content">
                        <input id="canisterIdWritingOff" style='display: none;'>
                        <h3>Підрозділ: <input class="positiveNumber" id="areaCanistrBack" style="pointer-events:none;"></h3>
                        <h3>Баланс каністр: <input class="positiveNumber" id="prevCanistrBack" style="pointer-events:none;"></h3>
                        <h3>Кількість повернутих каністр: <input type="number" class="positiveNumber" id="countCanistrBack"></h3>
                        <br>
                        <button class="cancel" onclick="closeModal()">Відмінити</button>
                        <button class="confirm" onclick="confirmAction()">Підтвердити</button>
                    </div>
                </div>
                <div id="getCanister">
                </div>
                <div id="areaGenerator">
                </div>
            </div>
        </div>
        <?
        if (session('usId') != false) {
            echo "<script>
                    $('#afterLogin').show();
                </script>";
        }
        ?>
    </div>
    </div>
    <script>
        mCaunter("#Pokaz")
        if (usRights == 4) showSet();
    </script>
</body>

</html>