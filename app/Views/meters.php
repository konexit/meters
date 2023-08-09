<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?= "<script> const bUrl='" . base_url() . "';</script>"; ?>
    <?php
    foreach (array("style", "datepicker") as $filename) {
        echo '<link type="text/css" rel="stylesheet" href="' . base_url() . '/assets/css/' . $filename . '.css?ver=' . STATIC_FILES_VERSION . '"></link>';
    }
    foreach (array("jquery", "scrollTo-min",  "global", "datepicker", "google", "chart") as $filename) {
        echo '<script src="' . base_url() . '/assets/js/' . $filename . '.js?ver=' . STATIC_FILES_VERSION . '"></script>';
    }

    $departmentOptions = '';
    foreach ($department as $arr) {
        $departmentOptions .= '<option data-value="' . $arr['id'] . '"  data-text="' . trim($arr['unit']) . '">' . trim($arr['unit']) . '</option>';
    }
    ?>
    <style>
        /* body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        form {
            border: 1px solid #ccc;
            padding: 20px;
            max-width: 400px;
            margin: 0 auto;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        input[type="date"],
        select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        select[multiple] {
            height: 100px;
        }

        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #45a049;
        } */
    </style>
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
                        if (session('usRights') == 1 or session('usRights') == 5 or session('usRights') == 2) {
                            $Smenu = "
                                    <li><a  class='menItem' id='aCounter' onclick='mCountAdd()'>Управління лічильниками</a></li>
                                    <li><a  class='menItem' id='Statistic' onclick='mCaunter(" . '"' . "#Statistic" . '"' . ")'>Статистика</a></li>
                                    <li><a  class='menItem' id='report' onclick='reportCounter()'>Звіт</a></li>";
                        }
                        if (session('usRights') != 4 &&  session('usRights') != 2) {
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
                        if (session('usRights') == 1) {
                            echo '<li><a  class="menItem" id="changePass" onclick="mchangePass()">Змінити пароль</a></li>';
                        }
                        ?>
                    </ul>

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
                            <p>Підрозділ: <input type="text" id="unit" title="Ліміт 30 символів" /> </p>
                            <p>Адреса: <input type="text" id="unADDR" title="Ліміт 80 символів" /> </p>
                            <p>Телефон: <input type="text" id="tel" title="Ліміт 100 символів" /> </p>
                            <button style="font-size: 14pt;" id="confirmUn" onclick="">Додати</button>
                        </h3>
                    </div>
                    <div id="addUser">
                        <h3>
                            <p>Ім'я: <input type="text" id="usName" title="Ліміт 25 символів" /> </p>
                            <p>Прізвище: <input type="text" id="usSurname" title="Ліміт 25 символів" /> </p>
                            <p>Логін: <input type="text" id="usLogin" title="Ліміт 15 символів" /> </p>
                            <p>Пароль: <input type="password" id="usPass" title="Ліміт 20 символів" /> </p>
                            <p>Підрозділ: <input id="inputUnitUs" list="unitUs" placeholder="Виберіть підрозділ">
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
                            <button style="font-size: 14pt;" id="confirmUs" onclick="">Підтвердити</button>
                        </h3>
                    </div>
                </div>
                <div class="table" id="table">
                </div>
                <div id="cPass">
                    <p>Старий пароль:<input id="oldPw" name="oldPw" type="password" /> </p>
                    <p>Новий пароль:<input id="newPw" name="newPw" type="password" /> </p>
                    <p>Підтвердіть пароль:<input id="confPw" name="confPw" type="password" /> </p>
                    <button style="font-size: 14pt;" onclick="cPass()">Змінити</button>
                    <div id="passError"></div>
                </div>
                <div id="cPass">
                    <p>Старий пароль:<input type="password" id="oldPass" /> </p>
                    <p>Новий пароль:<input type="password" id="newPass" /> </p>
                    <p>Підтвердіть пароль:<input type="password" id="confirmPass" /> </p>
                    <button style="font-size: 14pt;" onclick="cPass()">Змінити</button>
                </div>
                <div id="counterList" align="center">
                    <p></p>
                    <h3>
                        <a class="bcType" id="cYear">Рік: <select name="hcYear" class="hcYear" id="hcYear" onchange="getCounters()">
                                <option value="">Усі</option>
                                <?
                                foreach ($pokazYear as $arr) {
                                    echo "<option value='" . $arr['year'] . "'>" . $arr['year'] . "</option>";
                                }
                                ?>
                            </select></a>
                        <a class="bcType">Вид лічильника: <select name="cType" class="cType" id="hcType" onchange="getCounters()">
                                <option value="">Усі</option>
                                <?
                                foreach ($counterType as $arr) {
                                    echo "<option value='" . $arr['id'] . "'>" . $arr['Name'] . "</option>";
                                }
                                ?>
                            </select></a>
                        <a class="bcType">Місце розташування лічильника: <input id="inputCUnit" list="cUnit" onchange="getCounters()" placeholder="Усі">
                            <datalist name="cUnit" class="cUnit" id="cUnit">
                                <?
                                echo '<option data-value="0" data-text="Усі">Усі</option>';
                                foreach ($counterArea as $arr) {
                                    echo '<option data-value="' . $arr['id'] . '" data-text="' . trim($arr['unit']) . '">' . trim($arr['unit']) . '</option>';
                                }
                                ?></datalist></a>
                    </h3>
                </div>
                <div id="counter">
                    <p></p>
                </div>
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
                    <button onclick="setCounter(0)">Додати лічильник</button>
                    <p></p>
                    <div id="allCount"></div>
                </div>
                <div id="addCounters">
                    <p></p>
                    <p>
                    <h3>Місце розташування лічильника: <input id="inputCArr" list="cArr" placeholder="Виберіть підрозділ">
                        <datalist name="cArr" class="unit" id="cArr">
                            <?
                            echo $departmentOptions;
                            ?>
                        </datalist>
                        <p>Номер лічильника: <input type="text" id="cNumer" placeholder="н/д" title="Ліміт 30 символів" /></p>
                        <p>Вид: <input name="cnType" class="cnType" id="cnType" title="Ліміт 30 символів" />
                        <p>Назва лічильника: <input type="text" id="cName" title="Ліміт 50 символів" /></p>
                        <p>Тип лічильника: <select name="cType" class="cType" id="cType">
                                <option value="">Усі</option>
                                <?
                                foreach ($counterType as $arr) {
                                    echo "<option value='" . $arr['Name'] . "'>" . $arr['Name'] . "</option>";
                                }
                                ?>
                            </select></p>
                        <p>Початковий показник: <input type="number" name="sPokaz" class="sPokaz" id="sPokaz" />
                        <p>Стан лічильника: <input type="checkbox" name="cState" id="cState" />
                        <p></p>
                        <button id="buttCounter" onclick="addCounter()">Додати</button>
                    </h3>
                    </p>
                </div>
                <div id="countStat" align="center">
                    <h4 style="align: center"><br /></h4>
                    <form style="align: center" id="IndexСonsumption" onclick="statInfo()">
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
            </div>
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
        // mAddBid();
        mCaunter("#Pokaz")
        if (usRights == 4) showSet();
    </script>
</body>

</html>