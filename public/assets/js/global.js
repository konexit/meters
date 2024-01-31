const ajaxURL = `${bUrl}/ajax`
var usId;
var usRights;
var usArea;
var usAreaName;
var ecId = 0;
var gId = 0;
var pMonth = 0;
var pYear = 0;


//// ------------------------ GENERAL --------------------------------------- 
function ressetFields(elem) {
    elem.forEach(id => {
        const type = document.querySelector('#' + id).type
        switch (type) {
            case 'checkbox': {
                document.querySelector('#' + id).checked = false
                break
            }
            case 'select-one': {
                document.querySelector('#' + id).disabled = false
                break
            }
            default: {
                document.querySelector('#' + id).value = ''
            }
        }
    })
}

function getDataFromDatalist(datalist, input) {
    unitInput = document.querySelector(input)
    dataValue = getDataValue(datalist, input)
    unit;
    if (!unitInput.value || dataValue == 0) unit = '';
    else if (dataValue == undefined) {
        alert("Виберіть варіант із списка")
        return;
    } else unit = dataValue;
    return unit;
}

function setBackground(meIt) {
    arrCss = [
        $("#aCounter"),
        $("#Pokaz"),
        $("#Statistic"),
        $("#][]["),
        $("#aLog"),
        $("#changePass"),
        $("#allUs"),
        $("#allArea"),
        $("#aUser"),
        $("#aArea"),
        $("#report"),
        $("#mGeneratorManage"),
        $("#mGeneratorRemnant"),
        $("#mAreaGenerator"),
        $("#mCanisterTracking"),
        $("#mGeneratorReport"),
    ];
    $.each(arrCss, function (ind, val) {
        val.css({ "color": "White", "text-shadow": "none", "background-position": "none" });
    })
    meIt.css({ "color": "#E88F00", "text-shadow": "1px 2px 3px #ddd", "background-position": "-99.99% 0" });
}

function showSet(mShow) {
    masObj = [
        $('#pokazEdit'),
        $('#cYear'),
        $("#countStat"),
        $("#inputPokaz"),
        $("#counterList"),
        $("#getCanister"),
        $("#countCanister"),
        $("#setCounters"),
        $("#addCounters"),
        $("#counter"),
        $("#generator"),
        $("#refillGeneratorElement"),
        $("#canister"),
        $("#generatorRemnant"),
        $("#generatorAreaRemnantList"),
        $("#log"),
        $("#allUnit"),
        $("#cPass"),
        $("#allUser"),
        $("#addUnit"),
        $("#adminPanel"),
        $("#addUser"),
        $(".table"),
        $("#reportCounter"),
        $("#setGenerator"),
        $("#setCanister"),
        $("#addGenerator"),
        $("#addCanister"),
        $("#reportGenerator"),
        $("#generatorList"),
        $("#canisterList"),
        $("#areaGenerator"),
    ];
    $.each(masObj, function (ind, val) {
        val.hide();
    })
    if (mShow) {
        $.each(mShow, function (ind, val) {
            val.show();
        })
    }
}

function setValidHeightElement(elem, existTable = false) {
    const docHeight = $(document).height();
    const currElem = document.querySelector((existTable) ? elem + " table" : elem).offsetHeight
    document.querySelector(elem + " table").style.height = window.innerHeight - (docHeight - currElem) - 50 + "px"
}

function getDataValue(datalistSelector, selector) {
    return document.querySelector(`${datalistSelector} option[data-text="${document.querySelector(selector).value.trim()}"]`)?.dataset?.value
}
//// ------------------------ ||| GENERAL ||| ---------------------------------------




//// ------------------------ RENDER TABLE --------------------------------------- 
function createTableFromJSON(jsonData) {
    const data = JSON.parse(jsonData)

    const table = document.createElement("table")
    table.setAttribute("border", "2")
    table.setAttribute("cellpadding", "4")
    table.setAttribute("cellspacing", "0")
    table.setAttribute("align", "center")
    table.setAttribute("style", "width: 1000px; display: block; overflow-y: auto;")
    table.setAttribute("align", "left");
    table.setAttribute("scope", "col");

    // Create table header
    const tableHeader = document.createElement("thead")
    tableHeader.setAttribute("style", "position: sticky; top: 0;")

    const headerRow = document.createElement("tr")
    headerRow.setAttribute("style", "color:White;background-color:#006698;font-size:14pt;font-weight:bold;")

    const colgroup = document.createElement("colgroup");
    data.columns.forEach(column => {
        const th = document.createElement("th")
        th.textContent = column
        headerRow.appendChild(th)

        const col = document.createElement("col");
        const specialColumn = column == 'ID' || column == 'Добавити' || column == 'Стан'
        col.setAttribute("style", `width: ${specialColumn ? "auto" : "12%"};`);
        colgroup.appendChild(col);
    });

    table.appendChild(colgroup);
    tableHeader.appendChild(headerRow);
    table.appendChild(tableHeader);

    // Create table body
    const tableBody = document.createElement("tbody");

    data.counters.forEach(counter => {
        const row = document.createElement("tr")
        row.setAttribute("style", "border-color:#EFF3FB;border-width:2px;border-style:None;font-size:12pt;")

        let cell = document.createElement("td")

        cell.setAttribute("align", "left")
        cell.textContent = counter['ctype']
        row.appendChild(cell)

        cell = document.createElement("td")
        cell.textContent = counter['ci']
        row.appendChild(cell)

        cell = document.createElement("td")
        cell.textContent = counter['cn']
        row.appendChild(cell)

        cell = document.createElement("td")
        cell.textContent = counter['unit']
        row.appendChild(cell)

        cell = document.createElement("td")
        cell.textContent = counter['addr']
        row.appendChild(cell)

        cell = document.createElement("td")
        cell.textContent = counter['Name']
        row.appendChild(cell)

        cell = document.createElement("td")
        cell.textContent = counter['trade_point_id']
        row.appendChild(cell)

        if (usRights != 3) {
            cell = document.createElement("td")
            const checkbox = document.createElement("input")
            checkbox.type = "checkbox"
            checkbox.checked = counter['state'] == 1
            checkbox.disabled = true;
            cell.appendChild(checkbox)
            row.appendChild(cell)
        }

        cell = document.createElement("td")
        const addButton = document.createElement("button")
        addButton.textContent = "Добавити"
        addButton.addEventListener("click", function () {
            setPokaz(counter['id'], counter['ci'])
        });
        cell.appendChild(addButton)
        row.appendChild(cell)

        row.style.cssText = "border-color:#EFF3FB;border-width:2px;border-style:None;font-size:12pt;"
        if (data.filledCointerIds.find(item => item.cId === counter.id)) row.style.backgroundColor = "#01f92842"
        else row.style.backgroundColor = "#ffff1c75"

        tableBody.appendChild(row)
    });

    table.appendChild(tableBody)

    return table
}

function setPokaz(counterPK, cNom) {
    $("#cNum").html(cNom);
    $('#pMonch [value=' + pMonth + ']').attr("selected", "selected");
    $('#pYear [value=' + pYear + ']').attr("selected", "selected");
    $("#enterPokaz").attr('onClick', "enterPokaz(" + counterPK + ", '" + cNom + "')");
    $.post(ajaxURL, {
        action: "getLastPokaz",
        counterPK
    }, function (result) {
        $("#pokazEdit").html(result);
        inputState = usRights == 3
        for (let input of document.querySelectorAll('#pokazEdit input')) {
            input.disabled = inputState
        }
    })
    showSet([$("#inputPokaz"), $("#pokazEdit")]);
}

function createGeneratorFromJSON(jsonData, action, withoutActions = false) {
    const data = JSON.parse(jsonData);

    const table = document.createElement("table");
    table.setAttribute("border", "2");
    table.setAttribute("cellpadding", "4");
    table.setAttribute("cellspacing", "0");
    table.setAttribute("align", "center");
    table.setAttribute("style", "width: 1000px; display: block; overflow-y: auto;");
    table.setAttribute("align", "left");
    table.setAttribute("scope", "col");

    // Create table header
    const tableHeader = document.createElement("thead");
    tableHeader.setAttribute("style", "position: sticky; top: 0;");

    const headerRow = document.createElement("tr");
    headerRow.setAttribute("style", "color:White;background-color:#006698;font-size:14pt;font-weight:bold;");

    data.columns.forEach(column => {
        const th = document.createElement("th");
        th.textContent = column;
        headerRow.appendChild(th);
    });

    if (!withoutActions) {
        const th = document.createElement("th");
        th.textContent = 'Дія';
        headerRow.appendChild(th);
    }


    tableHeader.appendChild(headerRow);
    table.appendChild(tableHeader);

    // Create table body
    const tableBody = document.createElement("tbody");

    data.generators.forEach(generator => {
        const row = document.createElement("tr");
        row.setAttribute("style", "border-color:#EFF3FB;border-width:2px;border-style:None;font-size:12pt;");

        data.columns.forEach(column => {
            const cell = document.createElement("td")
            const key = data.ref[column]
            if (key == 'state') {
                const checkbox = document.createElement("input")
                checkbox.type = "checkbox"
                checkbox.checked = generator['state'] == 1
                checkbox.disabled = true;
                cell.appendChild(checkbox)
            } else cell.textContent = generator[key]
            row.appendChild(cell)
        })

        tableBody.appendChild(row);

        if (!withoutActions) {
            const rowAction = document.createElement("tr");
            rowAction.setAttribute("style", "border-color:#EFF3FB;border-width:2px;border-style:None;font-size:12pt;");
            const cell = document.createElement("td")
            const addButton = document.createElement("button")
            addButton.textContent = action
            addButton.addEventListener("click", function () {
                gId = generator['id']
                setGenerator(generator['id'], generator['unit'], generator['name'], generator['serialNum'], generator['coeff'], generator['type'], generator['state'], action)
            });
            cell.appendChild(addButton)
            row.appendChild(cell)
            tableBody.appendChild(row);
        }

    });

    table.appendChild(tableBody);

    return table;
}

function createCanisterFromJSON(jsonData) {
    const data = JSON.parse(jsonData);

    const table = document.createElement("table");
    table.setAttribute("border", "2");
    table.setAttribute("cellpadding", "4");
    table.setAttribute("cellspacing", "0");
    table.setAttribute("align", "center");
    table.setAttribute("style", "width: 1000px; display: block; overflow-y: auto;");
    table.setAttribute("align", "left");
    table.setAttribute("scope", "col");

    // Create table header
    const tableHeader = document.createElement("thead");
    tableHeader.setAttribute("style", "position: sticky; top: 0;");

    const headerRow = document.createElement("tr");
    headerRow.setAttribute("style", "color:White;background-color:#006698;font-size:14pt;font-weight:bold;");

    data.columns.forEach(column => {
        const th = document.createElement("th");
        th.textContent = column;
        headerRow.appendChild(th);
    });
    const th = document.createElement("th");
    th.textContent = 'Дії';
    headerRow.appendChild(th);

    tableHeader.appendChild(headerRow);
    table.appendChild(tableHeader);

    // Create table body
    const tableBody = document.createElement("tbody");

    data.canisters.forEach(canister => {
        const row = document.createElement("tr");
        row.setAttribute("style", "border-color:#EFF3FB;border-width:2px;border-style:None;font-size:12pt;");

        data.columns.forEach(column => {
            const cell = document.createElement("td")
            const key = data.ref[column]
            if (key == 'state') {
                const checkbox = document.createElement("input")
                checkbox.type = "checkbox"
                checkbox.checked = canister['state'] == 1
                checkbox.disabled = true;
                cell.appendChild(checkbox)
            } else cell.textContent = canister[key]
            row.appendChild(cell)
        })

        tableBody.appendChild(row);

        row.setAttribute("style", "border-color:#EFF3FB;border-width:2px;border-style:None;font-size:12pt;");

        const cell = document.createElement("td");

        const buttonContainer = document.createElement("div");
        buttonContainer.setAttribute("style", "text-align: center;");

        if (canister['statusId'] == 3) {
            const addButton = document.createElement("button");
            addButton.textContent = "Підтвердити поповнення";
            addButton.setAttribute("style", "margin: 5px;");
            addButton.addEventListener("click", function () {
                adminConfirmRefill(canister['id']);
            });
            buttonContainer.appendChild(addButton);
        }

        if (canister['statusId'] == 2) {
            const addButton = document.createElement("button");
            addButton.textContent = "Отримати";
            addButton.setAttribute("style", "margin: 5px;");
            addButton.addEventListener("click", function () {
                adminReturnOfCanisters(canister['id']);
            });
            buttonContainer.appendChild(addButton);
        }

        if (canister['statusId'] == 1) {
            const addButton2 = document.createElement("button");
            addButton2.textContent = "Відмінити";
            addButton2.addEventListener("click", function () {
                adminCancelSendingCanisters(canister['id']);
            });
            buttonContainer.appendChild(addButton2);
        }

        cell.appendChild(buttonContainer);

        row.appendChild(cell);
        tableBody.appendChild(row);
    });

    table.appendChild(tableBody);

    return table;
}

function editPokaz(cId, counter) {
    editPokazAjax(cId, counter)
}
//// ------------------------ ||| RENDER TABLE ||| ---------------------------------------




//// ------------------------ USERS --------------------------------------- 

function mallUser() {
    showSet([$("#adminPanel"), $("#allUser")]);
    getAllUsers();
    setBackground($("#allUs"));
}

function getAllUsers() {
    $.post(ajaxURL, {
        action: 'getAllUser'
    }, function (result) {
        $("#allUser").html(result);
        setValidHeightElement("#allUser");
    })
}

function userEdit(uId, uNam, uSur, uPass, uLogin, uArea, uRights) {
    $("#usName").val(uNam);
    $("#usSurname").val(uSur);
    $("#usLogin").val(uLogin);
    $("#usPass").val(uPass);
    document.querySelector('#inputUnitUs').value = uArea.trim()
    $("#RightsUs [text='" + uRights + "']").attr("selected", "selected");
    const dataset = document.querySelector(`#unitUs option[data-text="${document.querySelector('#inputUnitUs').value}"]`)?.dataset
    document.querySelector('#RightsUs').disabled = dataset.tradepointid
    maddUser(uId);
}

function confirmUserEdit(id) {
    if (!$("#usName").val() || !$("#usSurname").val() || !$("#usLogin").val() || !$("#usPass").val()) {
        alert("Обов'язкові поля не заповнені")
        return
    }

    const dataset = document.querySelector(`#unitUs option[data-text="${document.querySelector('#inputUnitUs').value}"]`)?.dataset
    if ($("#usLogin").val() == dataset.tradepointid + '-') {
        alert("Вкажіть унікальний логін")
        return
    }

    unit = getDataFromDatalist('#unitUs', '#inputUnitUs')
    if (unit == undefined) return
    $.post(ajaxURL, {
        action: "userEDIT",
        userID: id,
        user: $("#usName").val().trim(),
        surname: $("#usSurname").val().trim(),
        login: $("#usLogin").val().trim(),
        pass: $("#usPass").val().trim(),
        unit: unit,
        rights: $("#RightsUs :selected").val()
    }, function (result) {
        res = JSON.parse(result);
        if (res.error) {
            alert(res.error);
            return
        }
        ressetFields(['usName', 'usSurname', 'usLogin', 'usPass', 'RightsUs'])
        alert(res.success);
        mallUser();
    })
}

function maddUser(edMode) {
    if (edMode) {
        $("#confirmUs").text("Редагувати");
        $("#confirmUs").attr("onClick", "confirmUserEdit(" + edMode + ")");
    } else {
        $("#confirmUs").text("Додати користувача");
        $("#confirmUs").attr("onClick", "usADD()");
        $("#addUser input").val("")
        setBackground($("#aUser"));
    }
    showSet([$("#adminPanel"), $("#addUser")]);
}

function usADD() {
    if (!$("#usName").val() || !$("#usSurname").val() || !$("#usLogin").val() || !$("#usPass").val()) {
        alert("Обов'язкові поля не заповнені")
        return
    }

    const dataset = document.querySelector(`#unitUs option[data-text="${document.querySelector('#inputUnitUs').value}"]`)?.dataset
    if ($("#usLogin").val() == dataset.tradepointid + '-') {
        alert("Вкажіть унікальний логін")
        return
    }

    unit = getDataFromDatalist('#unitUs', '#inputUnitUs')
    if (unit == undefined) return

    $.post(ajaxURL, {
        action: "usADD",
        user: $("#usName").val().trim(),
        surname: $("#usSurname").val().trim(),
        login: $("#usLogin").val().trim(),
        pass: $("#usPass").val().trim(),
        unit: unit,
        rights: $("#RightsUs :selected").val()
    }, function (result) {
        res = JSON.parse(result);
        if (res.error) {
            alert(res.error);
            return
        }
        ressetFields(['usName', 'usSurname', 'usLogin', 'usPass', 'RightsUs'])
        alert(res.success);
    })
}

function companyUserDefaulValues() {
    unitInput = document.querySelector('#inputUnitUs')
    dataValue = getDataValue('#unitUs', '#inputUnitUs')
    unit;

    if (unitInput.value && dataValue != 0 && dataValue != undefined) {
        const dataset = document.querySelector(`#unitUs option[data-text="${document.querySelector('#inputUnitUs').value}"]`)?.dataset
        const isTradePoint = dataset.tradepointid
        if (isTradePoint) {
            document.querySelector('#managerTradePointForm').style.display = 'block'
            return
        }
    }
    document.querySelector('#managerTradePointForm').style.display = 'none'
    const usLogin = document.querySelector('#usLogin')
    usLogin.removeEventListener("input", setStartLogin)
    ressetFields(['usLogin', 'RightsUs', 'isManagerTradePoint'])
}
//// ------------------------ ||| USERS ||| ---------------------------------------




//// ------------------------ AREAS --------------------------------------- 
function mallUnit() {
    showSet([$("#adminPanel"), $("#allUnit")]);
    getAllUnit();
    setBackground($("#allArea"));
}

function getAllUnit() {
    $.post(ajaxURL, {
        action: "getAllUnit"
    }, function (result) {
        $("#allUnit").html(result);
        setValidHeightElement("#allUnit");
    })
}

function maddUnit(edMode) {
    if (edMode) {
        $("#confirmUn").text("Редагувати");
        $("#confirmUn").attr("onClick", "confirmUnitEdit(" + edMode + ")");
        tradePointInput(true)
    } else {
        $("#confirmUn").text("Додати підрозділ");
        $("#confirmUn").attr("onClick", "unADD()");
        $("#addUnit input").val("")
        tradePointInput(false)
        setBackground($("#aArea"));
    }
    showSet([$("#adminPanel"), $("#addUnit")]);
}

function unADD() {
    const isTradePoint = document.querySelector('#isTradePoint').checked;
    const tradePointId = +document.querySelector('#tradePointId').value
    const companyId = +document.querySelector('#companyId').value

    if (isTradePoint) {
        if (tradePointId < 1) {
            alert("Введіть унікальний id торгової точки")
            return
        }

        if (companyId < 1) {
            alert("Виберіть обовязково компанію")
            return
        }
    }

    if (!($("#unADDR").val() || $("#tel").val() || $("#unit").val())) {
        alert("Oбов'язкові поля не заповнені");
        return;
    }

    $.post(ajaxURL, {
        action: "unADD",
        addr: $("#unADDR").val().trim(),
        tel: $("#tel").val().trim(),
        unit: $("#unit").val().trim(),
        areaState: document.querySelector('#areaState').checked,
        isTradePoint: isTradePoint,
        tradePointId: tradePointId,
        companyId: +document.querySelector('#companyId').value,
        refill: document.querySelector('#tradePointRefill').checked
    }, function (result) {
        res = JSON.parse(result);
        if (res.error) {
            alert(res.error);
            return
        }
        $(".unit").append('<option data-value="' + res.id + '"  data-text="' + res.unit.trim() + '">' + res.unit.trim() + '</option>');
        ressetFields(['unADDR', 'tel', 'unit', 'tradePointId'])
        alert("Успішно добавлено підрозділ");
        location.reload()
    });
}

function tradePointInput(state = false) {
    document.querySelector('#isTradePoint').disabled = state
    document.querySelector('#tradePointId').disabled = state
    document.querySelector('#companyId').disabled = state
}

function confirmUnitEdit(id) {
    const isTradePoint = document.querySelector('#isTradePoint').checked;
    const tradePointId = +document.querySelector('#tradePointId').value
    const companyId = +document.querySelector('#companyId').value

    if (isTradePoint) {
        if (tradePointId < 1) {
            alert("Введіть унікальний id торгової точки")
            return
        }

        if (companyId < 1) {
            alert("Виберіть обовязково компанію")
            return
        }
    }

    if (!($("#unADDR").val() && $("#tel").val() && $("#unit").val())) {
        alert("Oбов'язкові поля не заповнені");
        return;
    }

    $.post(ajaxURL, {
        action: "unitEDIT",
        unitID: id,
        addr: $("#unADDR").val().trim(),
        tel: $("#tel").val().trim(),
        unit: $("#unit").val().trim(),
        areaState: document.querySelector('#areaState').checked,
        refill: document.querySelector('#tradePointRefill').checked,
        isTradePoint: isTradePoint,
        tradePointId: tradePointId,
        companyId: +document.querySelector('#companyId').value
    }, function (result) {
        res = JSON.parse(result);
        if (res.error) {
            alert(res.error);
            return
        }
        ressetFields(['unADDR', 'tel', 'unit'])
        alert("Успішно змінено підрозділ");
        mallUnit();
    })
}

function managerTradePointFormStatus() {
    const usLogin = document.querySelector('#usLogin');
    if (!document.querySelector('#isManagerTradePoint').checked) {
        usLogin.removeEventListener("input", setStartLogin)
        ressetFields(['usLogin', 'RightsUs'])
        return
    }

    unitInput = document.querySelector('#inputUnitUs')
    dataValue = getDataValue('#unitUs', '#inputUnitUs')
    unit;

    if (unitInput.value && dataValue != 0 && dataValue != undefined) {
        const dataset = document.querySelector(`#unitUs option[data-text="${document.querySelector('#inputUnitUs').value}"]`)?.dataset
        const isTradePoint = dataset.tradepointid;
        if (isTradePoint) {
            const startLogin = dataset.tradepointid + '-'
            usLogin.value = startLogin
            usLogin.addEventListener("input", setStartLogin)
            document.querySelector('#RightsUs').value = 3
            document.querySelector('#RightsUs').disabled = true
        }
    }
}

function setStartLogin(e) {
    const dataset = document.querySelector(`#unitUs option[data-text="${document.querySelector('#inputUnitUs').value}"]`)?.dataset
    if (!e.target.value.startsWith(dataset.tradepointid + '-')) document.querySelector('#usLogin').value = dataset.tradepointid + '-'
}

function tradePointFormStatus(status) {
    if (status) document.querySelector('#tradePointForm').style.display = 'block';
    else document.querySelector('#tradePointForm').style.display = 'none';
};

function unitEdit(uId, unit, addr, tel, areaState = true, refill = false, isTradePoint = false, tradePointId = 0, companyId = 0) {
    $("#unit").val(unit);
    $("#unADDR").val(decodeURIComponent(addr));
    $("#tel").val(tel);
    document.querySelector('#isTradePoint').checked = isTradePoint
    document.querySelector('#areaState').checked = areaState
    document.querySelector('#tradePointRefill').checked = refill
    tradePointFormStatus(isTradePoint)
    document.querySelector('#tradePointId').value = tradePointId
    document.querySelector('#companyId').value = companyId
    maddUnit(uId);
}
//// ------------------------ ||| AREAS ||| ---------------------------------------




//// ------------------------ LOG --------------------------------------- 
function mLog() {
    showSet([$("#adminPanel"), $("#log")]);
    setBackground($("#aLog"));
    getConnectLog();
}

function getConnectLog() {
    $.post(ajaxURL, {
        action: "getLogConnection"
    }, function (result) {
        $("#log").html(result);
        setValidHeightElement("#log");
    })
}
//// ------------------------ ||| LOG ||| ---------------------------------------




//// ------------------------ COUNTERS --------------------------------------- 
function mCaunter(typeC) {
    setBackground($(typeC));
    if (typeC == '#Statistic') {
        $('#hcYear [value=' + new Date().getFullYear() + ']').attr("selected", "selected")
        getStatistic($('.radioBox[checked]').val())
        return;
    }
    mSh = (usRights == 3) ? [$("#counter"), $(typeC)] : [$("#counter"), $(typeC), $("#counterList")]
    option = document.querySelector(`#cUnit option[data-value="${usArea == 193 ? 0 : usArea}"]`)
    if (usRights == 3 && option) document.querySelector('#inputCUnit').value = option.value
    showSet(mSh);
    if (usRights == 3 && !option) alert("У вас немає лічильників")
    else getCounters()
}

function getCounters() {
    if ($("#countStat").is(":visible")) {
        getStatistic($('.radioBox[checked]').val());
        return;
    }
    unit = getDataFromDatalist('#cUnit', '#inputCUnit')
    if (unit == undefined) return
    const stateCounter = $("#setCounters").is(":visible") ? 0 : 1
    $.post(ajaxURL, {
        action: "getCounters",
        cUnit: unit,
        cType: $('#hcType :selected')[0].value,
        state: stateCounter
    }, function (result) {
        if (stateCounter) {
            const tableCounter = document.querySelector("#counter")
            if (tableCounter.children[0]) tableCounter.replaceChild(createTableFromJSON(result), tableCounter.children[0])
            else tableCounter.appendChild(createTableFromJSON(result))
            setValidHeightElement("#counter", true);
        } else {
            $("#allCount").html(result);
            setValidHeightElement("#allCount");
        }
    });
}

function getStatistic(mode) {
    showSet([$("#countStat"), $("#counterList"), $('#cYear')]);
    unit = getDataFromDatalist('#cUnit', '#inputCUnit')
    if (unit == undefined) return
    $.post(ajaxURL, {
        action: 'getCountersStat',
        cYear: $('#hcYear :selected').val(),
        mod: mode,
        cUnit: unit,
        cType: $('#hcType :selected').val()
    }, function (result) {
        rez = result
        $("#cStat").html(rez['table'])
        $("#chart").html('')
        if (unit) drawChart(rez['diagram'], rez['arrayCount'])
        setValidHeightElement("#cStat");
    }, 'json')

}

function enterPokaz(counterPK, counter) {
    if (!$("#counterPokaz").val()) {
        alert('Введіть будь ласка показник!')
        return
    }
    $.post(ajaxURL, {
        action: "addPokaz",
        counter,
        counterPK,
        pokaz: $("#counterPokaz").val(),
        month: $("#pMonch :selected").text() ? $("#pMonch :selected").text() : pMonth,
        year: $("#pYear :selected").text() ? $("#pYear :selected").text() : pYear
    }, function (result) {
        alert(result)
        $("#counterPokaz").val("")
        alert("Успішно добавлені показники");
        mCaunter("#Pokaz")
    })
}

function mCountAdd() {
    ecId = 0;
    ressetFields(['inputCArr', 'cNumer', 'cName', 'cType'])
    showSet([$("#setCounters"), $("#counterList")]);
    setBackground($("#aCounter"));
    getCounters();
}

function reportCounter() {
    showSet([$("#reportCounter")]);
    setBackground($("#report"));
}

function getReportCounter(event) {
    event.preventDefault();

    const startDate = document.querySelector('#reportStartDate').value;
    const endDate = document.querySelector('#reportEndDate').value;

    if (startDate > endDate) {
        alert("Перевірте введені діапазон звіта")
        return
    }

    const companies = getCompaniesAndColors('counters', '#formReportCounter .company-color');
    if (companies.length == 0) {
        alert("Виберіть компанії")
        return
    }

    // let countDownload = +localStorage.getItem(document.cookie)
    // if (countDownload > 25) {
    //     alert("Ви перевищили ліміт завантажень\n" + 
    // "Для продовження, будь ласка, зробіть грошовий внесок")
    //     return
    // } else localStorage.setItem(document.cookie, ++countDownload);


    fetch(ajaxURL, {
        headers: {
            "Content-Type": "application/json",
        },
        method: "POST",
        body: JSON.stringify({
            "action": 'getReportCounter',
            "reportStartDate": startDate,
            "reportEndDate": endDate,
            "countersType": getArrayOfSelectedOptions('#counterType'),
            "companies": companies
        })
    })
        .then(result => result.blob())
        .then(result => {
            const fileName = 'meters_report.zip'
            if (false || !!document.documentMode) {
                window.navigator.msSaveBlob(blob, fileName);
            } else {
                const url = window.URL || window.webkitURL;
                link = url.createObjectURL(result);
                const a = $("<a />");
                a.attr("download", fileName);
                a.attr("href", link);
                $("body").append(a);
                a[0].click();
                a.remove();
            }
        })
        .catch(console.error)
};

function getArrayOfSelectedOptions(element) {
    const typeSelect = document.querySelector(element);
    const selectedTypes = [];
    for (let i = 0; i < typeSelect.options.length; i++) {
        const option = typeSelect.options[i];
        if (option.selected) selectedTypes.push({
            "typeCounterId": +option.value,
            "typeCounterName": option.text
        }
        );
    }
    return selectedTypes
}

function getCompaniesAndColors(type, selector) {
    return Array.from(document.querySelectorAll(selector)).map(row => {
        if (!row.children[0].children[0].checked) return null
        switch (type) {
            case 'counters': {
                return {
                    "companyId": +row.dataset.companyid,
                    "companyName": row.children[0].children[1].textContent,
                    "color": row.children[1].children[0].value
                }
            }
            case 'generators': {
                return {
                    "companyId": +row.dataset.companyid,
                    "companyName": row.children[0].children[1].textContent
                }
            }
        }

    }).filter(row => row !== null)
}

function setCounter(cId, cUnit, cType, cName, cNum, cVid, csPokaz, cState) {
    if (cId) {
        $(`#cType [value="${cType}"]`).attr("selected", "selected");
        $("#cName").val(cName);
        $("#cNumer").val(cNum);
        $("#sPokaz").val(csPokaz);
        $("#cnType").val(cVid);
        document.querySelector('#cState').checked = cState;
        document.querySelector('#inputCArr').value = cUnit.trim()
        ecId = cId;
    }
    document.querySelector('#buttCounter').innerText = cId ? 'Редагувати' : 'Добавити'
    mSh = [$("#addCounters")];
    showSet(mSh);
}

function addCounter() {
    if (!$('#cType :selected').val() || document.querySelector('#sPokaz').value == '') {
        alert("Обов'язкове поле не заповнене")
        return
    }

    if ($("#cNumer").val().length > 30 || $("#cName").val().length > 50 || $('#cnType').val().length > 30) {
        alert("Ви вписали занадто багато символів")
        return;
    }

    unit = getDataFromDatalist('#cArr', '#inputCArr')
    if (unit == undefined) return;


    $.post(ajaxURL, {
        action: "addCounter",
        spokaz: $('#sPokaz').val().trim(),
        ctype: $('#cnType').val().trim(),
        cArr: unit,
        cNumer: $("#cNumer").val() ? $("#cNumer").val().trim() : 'н/д',
        cName: $("#cName").val().trim(),
        cType: $('#cType :selected').text().trim(),
        cState: document.querySelector('#cState').checked,
        edit: ecId
    }, function () {
        ressetFields(['inputCArr', 'cNumer', 'cName', 'cType', 'sPokaz', 'cnType', 'cState'])
        showSet([$("#setCounters"), $("#counterList")]);
        setBackground($("#aCounter"));
        alert("Успішно створений лічильник");
        getCounters();
    });
    ecId = 0
}

function isEnter(event, cId, counter) {
    if (event.keyCode == 13) editPokazAjax(cId, counter)
}

function editPokazAjax(cId, counter) {
    $.post(ajaxURL, {
        action: "editPokaz",
        counter,
        pid: cId,
        index: $("#editP" + cId).val()
    },
        function (result) {
            $.post(ajaxURL, {
                action: "getPokazByIdAndCounter",
                cid: result
            }, function (result) {
                alert("Успішно змінено показник лічильника");
                eval(result);
            }
            )
        }
    );
}
//// ------------------------ ||| COUNTERS ||| ---------------------------------------




//// ------------------------ CHANGE PASS --------------------------------------- 
function mchangePass() {
    ressetFields(['confPw', 'oldPw', 'newPw'])
    showSet([$("#cPass")]);
    setBackground($("#changePass"));
}

function cPass() {
    $.post(bUrl + '/password', {
        oldPw: $("#oldPw").val().trim(),
        newPw: $('#newPw').val().trim(),
        confPw: $("#confPw").val().trim()
    }, function (result) {
        if (result) {
            document.querySelector('#passError').innerHTML = `<div class="login-error">${JSON.parse(result).validation}</div>`
        } else {
            document.querySelector('#passError').innerHTML = ''
            ressetFields(['oldPw', 'newPw', 'confPw'])
            alert("Успішно змінено пароль");
        }
    });
}
//// ------------------------ ||| CHANGE PASS ||| ---------------------------------------




//// ------------------------ GENERATOR --------------------------------------- 
function setGenerator(gId, gUnit, gName, gSerialNum, gCoeff, gType, gState, action = 'Добавити') {
    if (gId) {
        document.querySelector('#gName').value = gName
        document.querySelector('#gSerialNum').value = gSerialNum
        document.querySelector('#gCoeff').value = gCoeff
        document.querySelector('#inputGenType').value = gType
        document.querySelector('#gState').checked = gState == 1
        document.querySelector('#inputGArr').value = gUnit
    }
    document.querySelector('#buttGenerator').innerText = action
    showSet([$("#addGenerator")]);
}

function addGenerator() {
    const gSerialNum = document.querySelector('#gSerialNum').value
    const gName = document.querySelector('#gName').value
    const gCoeff = document.querySelector('#gCoeff').value
    const gState = document.querySelector('#gState').checked

    if (!gName.trim() || !gCoeff.trim()) {
        alert("Обов'язкові поля не заповнені")
        return
    }

    if (gSerialNum.length > 30 || gName.length > 50) {
        alert("Ви вписали занадто багато символів")
        return;
    }

    const unit = getDataFromDatalist('#gArr', '#inputGArr')
    const type = getDataFromDatalist('#gType', '#inputGenType')
    if (unit == undefined || type == undefined) return;
    if (type == '') {
        alert("Обов'язкові поля не заповнені")
        return
    }

    $.post(ajaxURL, {
        action: "modifGenerator",
        gArr: unit,
        gType: type,
        gSerialNum: gSerialNum ? gSerialNum : 'н/д',
        gCoeff: gCoeff,
        gName: gName.trim(),
        gState: gState,
        gId: gId
    }, function () {
        alert("Успішно створений генератор");
        mGeneratorManage()
    });
}

function mGeneratorManage() {
    ressetFields(['inputGArr', 'inputGenType', 'gSerialNum', 'gName', 'gCoeff', 'gState'])
    showSet([$("#setGenerator"), $("#generatorList"), $("#generator")]);
    setBackground($("#mGeneratorManage"));
    getGenerators();
}

function mGeneratorRemnant() {
    showSet([$("#generatorAreaRemnantList"), $("#generatorRemnant")]);
    setBackground($("#mGeneratorRemnant"));
    getGeneratorsRemnant();
}

function getGenerators() {
    const unit = getDataFromDatalist('#gUnit', '#inputGUnit')
    const type = getDataFromDatalist('#gType', '#inputGType')
    if (unit == undefined || type == undefined) return
    $.post(ajaxURL, {
        action: "getGenerators",
        gUnit: unit,
        gType: type
    }, function (result) {
        const tableGenerator = document.querySelector("#generator")
        const action = $("#setGenerator").is(":visible") ? 'Редагувати' : 'Добавити'
        if (tableGenerator.children[0]) tableGenerator.replaceChild(createGeneratorFromJSON(result, action), tableGenerator.children[0])
        else tableGenerator.appendChild(createGeneratorFromJSON(result, action))
        setValidHeightElement("#generator", true);
    });
}

function getGeneratorsRemnant() {
    const unit = getDataFromDatalist('#gUnitRemnant', '#inputGUnitRemnant')
    const type = getDataFromDatalist('#gTypeRemnant', '#inputGTypeRemnant')
    if (unit == undefined || type == undefined) return
    $.post(ajaxURL, {
        action: "getGeneratorsRemnant",
        gUnit: unit,
        gType: type
    }, function (result) {
        const tableGenerator = document.querySelector("#generatorRemnant")
        if (tableGenerator.children[0]) tableGenerator.replaceChild(createGeneratorFromJSON(result, "", true), tableGenerator.children[0])
        else tableGenerator.appendChild(createGeneratorFromJSON(result, "", true))
        setValidHeightElement("#generatorRemnant", true);
    });
}

function mCanisterTracking() {
    ressetFields(['inputCanisterUnit', 'inputCanisterType', 'inputCanisterStatus'])
    showSet([$("#setCanister"), $("#canisterList"), $("#canister")]);
    setBackground($("#mCanisterTracking"));
    getCanisters();
}

function getCanisters() {
    const unit = getDataFromDatalist('#canisterUnit', '#inputCanisterUnit')
    const type = getDataFromDatalist('#canisterType', '#inputCanisterType')
    const status = getDataFromDatalist('#canisterStatus', '#inputCanisterStatus')
    if (unit == undefined || type == undefined || status == undefined) return
    $.post(ajaxURL, {
        action: "getCanisters",
        unit: unit,
        type: type,
        status: status,
    }, function (result) {
        const tableCanister = document.querySelector("#canister")
        if (tableCanister.children[0]) tableCanister.replaceChild(createCanisterFromJSON(result), tableCanister.children[0])
        else tableCanister.appendChild(createCanisterFromJSON(result))
        setValidHeightElement("#canister", true);
    });

}

function listenerRemoveAllNonDigit(targetElem) {
    targetElem.forEach(elem => {
        document.querySelector(elem).addEventListener("input", function () {
            const inputElement = document.querySelector(elem);
            inputElement.value = inputElement.value.replace(/[^\d-]/g, "");
            if (inputElement.value.length > 5) {
                inputElement.value = inputElement.value.slice(0, 5);
            }
        });
    });
}

function addCanister() {
    const sendCanisterDate = document.querySelector('#sendCanisterDate').value
    const countCanistr = document.querySelector('#countCanistr').value
    const fuelVolume = document.querySelector('#fuelVolume').value

    if (!sendCanisterDate.trim() || !countCanistr.trim() || !fuelVolume.trim()) {
        alert("Обов'язкові поля не заповнені")
        return
    }

    const type = getDataFromDatalist('#addCanisterType', '#inputAddCanisterType')
    const unit = getDataFromDatalist('#addCanisterUnit', '#inputAddCUnit')
    if (unit == undefined || type == undefined) return;

    $.post(ajaxURL, {
        action: "addCanister",
        sendCanisterDate: sendCanisterDate,
        countCanistr: countCanistr,
        fuelVolume: fuelVolume,
        typeFuel: document.querySelector('#addCanisterType option[data-value="' + type + '"]').value,
        type: type,
        unit: unit
    }, function () {
        ressetFields(['sendCanisterDate', 'countCanistr', 'fuelVolume', 'inputAddCanisterType', 'inputAddCUnit'])
        alert("Успішно відправлено каністри");
        mCanisterTracking()
    });
}

function adminReturnOfCanisters(canisterId) {
    if (!confirm("Ви впевнені, що отримали вказану кількість каністр?")) {
        return;
    }
    $.post(ajaxURL, {
        action: "actionsAdminCanister",
        isReturning: false,
        idCanister: canisterId
    }, function (result) {
        const data = JSON.parse(result)
        if (data['response'] != 200) {
            alert(data['message'])
            return;
        }
        alert("Успішно отримано каністри");
        mCanisterTracking()
    });
}

function adminConfirmRefill(canisterId) {
    if (!confirm("Ви впевнені, що аптека самостійно придбала саме вказану кількість палива?")) {
        return;
    }
    $.post(ajaxURL, {
        action: "actionsConfRefillFuel",
        idCanister: canisterId
    }, function (result) {
        const data = JSON.parse(result)
        if (data['response'] != 200) {
            alert(data['message'])
            return;
        }
        alert("Успішно підзаправлено аптеку");
        mCanisterTracking()
    });
}

function adminCancelSendingCanisters(canisterId) {
    if (!confirm("Ви впевнені, що бажаєте відмінити відправку каністри?")) {
        return;
    }
    $.post(ajaxURL, {
        action: "actionsAdminCanister",
        isReturning: true,
        idCanister: canisterId
    }, function (result) {
        const data = JSON.parse(result)
        if (data['response'] != 200) {
            alert(data['message'])
            return;
        }
        alert("Успішно відмінено відправку каністр");
        mCanisterTracking()
    });
}

function userCancelCanister(totalCanisters) {
    const countCanistBack = document.querySelector('#countCanistrBack').value
    if (countCanistBack <= 0 || totalCanisters < countCanistBack) {
        alert("Перевірте вказані дані")
        return;
    }
    if (!confirm("Ви впевненні, що бажаєте повернути саме: " + countCanistBack + " каністр?")) {
        return;
    }
    $.post(ajaxURL, {
        action: "actionsUserCanister",
        isReturning: true,
        countCanistBack: countCanistBack
    }, function (result) {
        const data = JSON.parse(result)
        if (data['response'] != 200) {
            alert(data['message'])
            return;
        }
        alert("Успішно відправленно каністри на повернення");
        mAreaGenerator()
    });
}

function userReceivingCanisters(canId) {
    if (!confirm("Підтвердіть, що ви дійсно отримали вказанну кількість палива та каністр.\n" +
        "Якщо виникають труднощі або запитання зверніться у тенічний відділ")) {
        return;
    }
    $.post(ajaxURL, {
        action: "actionsUserCanister",
        isReturning: false,
        idCanister: canId
    }, function (result) {
        const data = JSON.parse(result)
        if (data['response'] != 200) {
            alert(data['message'])
            return;
        }
        alert("Успішно отримано каністри");
        mAreaGenerator()
    });
}

function mAreaGenerator() {
    showSet([$("#areaGenerator"), $("#getCanister"), $("#countCanister"), $("#refillGeneratorElement")])
    setBackground($("#mAreaGenerator"));
    getGeneratorsAndCanisters()
}

function addGeneratorPokaz(gId) {
    const startGenerator = new Date(document.querySelector('#genId_' + gId + ' .start-generator').value);
    const endGenerator = new Date(document.querySelector('#genId_' + gId + ' .end-generator').value);
    const generatorCoeff = document.querySelector('#genId_' + gId + ' .generator-coeff').value;

    if (startGenerator >= endGenerator) {
        alert("Перевірьте вказані данні роботи генератора");
        return;
    }

    const workingTime = Math.round(((endGenerator - startGenerator) / (1000 * 60 * 60)) * 10) / 10
    if (!confirm("Генератор працював: " + workingTime + " годин?")) {
        return;
    }

    $.post(ajaxURL, {
        action: "addGeneratorPokaz",
        year: startGenerator.getFullYear(),
        month: ('0' + (startGenerator.getMonth() + 1)).slice(-2),
        day: ('0' + startGenerator.getDate()).slice(-2),
        workingTime: parseFloat(workingTime.toFixed(1)),
        consumed: Math.round(((workingTime) * generatorCoeff) * 100) / 100,
        genId: gId,
        date: startGenerator.getFullYear() + '-' + ('0' + (startGenerator.getMonth() + 1)).slice(-2) + '-' + ('0' + startGenerator.getDate()).slice(-2),
        startTime: ('0' + startGenerator.getHours()).slice(-2) + ':' + ('0' + startGenerator.getMinutes()).slice(-2),
        endTime: ('0' + endGenerator.getHours()).slice(-2) + ':' + ('0' + endGenerator.getMinutes()).slice(-2)
    }, function (result) {
        const data = JSON.parse(result)
        if (data['response'] != 200) {
            alert(data['message'])
            return;
        }
        alert("Успішно добавлено показники");
        getGeneratorsAndCanisters()
    });
}

function getGeneratorsAndCanisters() {
    $.post(ajaxURL, {
        action: "getGeneratorsAndCanisters",
    }, function (result) {
        const data = JSON.parse(result)
        const getCanisterElement = document.querySelector("#getCanister");
        const countCanisterElement = document.querySelector("#countCanister");
        const areaGeneratorElement = document.querySelector("#areaGenerator");

        getCanisterElement.innerHTML = "";
        countCanisterElement.innerHTML = "";
        areaGeneratorElement.innerHTML = "";

        const countCanister = +data.fuelArea
        if (countCanister > 0) {
            var canisterCountElement = document.createElement("div");
            canisterCountElement.className = "flex-container";

            canisterCountElement.innerHTML = `
            <div class="canister-desc">
                <div class="desc-can" style="width: 35%;"><strong>Баланс каністр: </strong><span style="margin-left: 5px;">${countCanister} од.</span></div>
                <div class="desc-can" style="width: 65%;"><strong style="text-align: center;">Введіть кількість для повернення: </strong><input type="number" class="positiveNumber" id="countCanistrBack"></div>
            </div>
            <div class="confirm-canister">
                <button onclick="userCancelCanister(${countCanister})" style="font-size: 20px;">Повернути</button>
            </div>`;
            countCanisterElement.appendChild(canisterCountElement);

            listenerRemoveAllNonDigit(['#countCanistrBack']);
        }

        listenerRemoveAllNonDigit(['#fuelRefill']);
        if (data.refill == '0') {
            document.querySelector("#refillGeneratorElement").style.display = 'none'
        }

        data.canisters.forEach(function (areaCanister) {
            var canisterElement = document.createElement("div");
            canisterElement.className = "flex-container";

            canisterElement.innerHTML = "<div class='canister-desc'>" +
                "<div class='desc-can' style='width: 35%;'>" +
                "<strong>Відправка:</strong><span style='margin-left: 5px;'>" + areaCanister.date + "</span></div>" +
                "<div class='desc-can' style='width: 25%;'>" +
                "<strong>Тип:</strong><span style='margin-left: 5px;'>" + areaCanister.type + "</span></div>" +
                "<div class='desc-can' style='width: 20%;'>" +
                "<strong>Каністр:</strong><span style='margin-left: 5px;'>" + areaCanister.canister + "</span></div>" +
                "<div class='desc-can' style='width: 20%;'>" +
                "<strong>Палива:</strong><span style='margin-left: 5px;'>" + areaCanister.fuel + "</span></div></div>" +
                "<div class='confirm-canister'>" +
                "<button onclick='userReceivingCanisters(" + areaCanister.id + ")' style='font-size: 20px;'>Підтвердити</button></div></div>";

            getCanisterElement.appendChild(canisterElement);
        });

        const currentDateTime = (new Date(Date.now() - (new Date()).getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
        data.generators.forEach(function (areaGenerator) {
            if (areaGenerator.state === "1") {
                var generatorElement = document.createElement("div");
                generatorElement.className = "flex-container";
                const avWorkingTime = Math.round(areaGenerator.fuel / areaGenerator.coeff * 10) / 10

                generatorElement.innerHTML = "<div>" +
                    "<div class='generator-info'>" +
                    "<div class='desc-gen'><strong>Назва:</strong><span>" + areaGenerator.name + "</span></div>" +
                    "<div class='desc-gen'><strong>Тип:</strong><span>" + areaGenerator.type + "</span></div>" +
                    "<div class='desc-gen'><strong>Серійний номер:</strong><span>" + areaGenerator.serialNum + "</span></div>" +
                    "</div>" +
                    "<div class='generator-resources'>" +
                    "<div class='res-gen'><strong>Каністр:</strong><span>" + areaGenerator.canister + " шт.</span></div>" +
                    "<div class='res-gen'><strong>Палива:</strong><span>" + areaGenerator.fuel + " л.</span></div>" +
                    "<div class='res-gen'><strong>Прогнозований час роботи генератора: &#8776;</strong><span id='avWorkingTime_" + areaGenerator.id + "' style='color: #eb0b0b;'>" + avWorkingTime + " годин</span></div>" +
                    "</div>" +
                    "</div>" +
                    "<div class='adding-pokaz'>" +
                    "<h3>Час роботи</h3>" +
                    "<div id='genId_" + areaGenerator.id + "'>" +
                    "<input style='display: none;' class='generator-coeff' value='" + areaGenerator.coeff + "'>" +
                    "<span>Початок:<input type='datetime-local' class='start-generator' value='" + currentDateTime + "'/></span>" +
                    "<span>Кінець:<input type='datetime-local' class='end-generator' value='" + currentDateTime + "' /></span>" +
                    "</div>" +
                    "<button id='genBTNId_" + areaGenerator.id + "' onclick='addGeneratorPokaz(" + areaGenerator.id + ")' style='font-size: 20px;'>Подати</button>" +
                    "</div>" +
                    "</div>";

                areaGeneratorElement.appendChild(generatorElement);

                const datapicker = document.querySelectorAll('#genId_' + areaGenerator.id + ' input[type=datetime-local]')
                datapicker.forEach(function (elem) {
                    elem.addEventListener("change", function () {
                        const startGenerator = new Date(document.querySelector('#genId_' + areaGenerator.id + ' .start-generator').value);
                        const endGenerator = new Date(document.querySelector('#genId_' + areaGenerator.id + ' .end-generator').value);
                        const currentWorkingTime = Math.round(((endGenerator - startGenerator) / (1000 * 60 * 60)) * 10) / 10
                        let btn = document.querySelector('#genBTNId_' + areaGenerator.id);
                        if (isNaN(startGenerator) || isNaN(endGenerator) || startGenerator >= endGenerator || currentWorkingTime == 0 || currentWorkingTime > avWorkingTime) {
                            btn.innerHTML = 'Подати'
                            btn.style['background-color'] = "#517b53"
                        } else {
                            btn.innerHTML = 'Подати ≈ ' + Math.round(((endGenerator - startGenerator) / (1000 * 60 * 60)) * 10) / 10
                            btn.style['background-color'] = "#4CAF50"
                        }
                    });
                });
            }
        });
    });
}

function mGeneratorReport() {
    showSet([$("#reportGenerator")]);
    setBackground($("#mGeneratorReport"));
}

function getReportGenerator(event) {
    event.preventDefault();

    const startDate = document.querySelector('#reportGenStartDate').value;
    const endDate = document.querySelector('#reportGenEndDate').value;

    if (startDate > endDate) {
        alert("Перевірте введені діапазон звіта")
        return
    }

    const companies = getCompaniesAndColors('generators', '#formReportGenerator .company-color');
    if (companies.length == 0) {
        alert("Виберіть компанії")
        return
    }

    fetch(ajaxURL, {
        headers: {
            "Content-Type": "application/json",
        },
        method: "POST",
        body: JSON.stringify({
            "action": 'getReportGenerator',
            "reportStartDate": startDate,
            "reportEndDate": endDate,
            "groupBy": document.querySelector('#displayBy').value,
            "companies": companies
        })
    })
        .then(result => result.blob())
        .then(result => {
        // .then(result => result.json()) // Use result.json() to parse JSON response
        // .then(jsonResult => {
        //     debugger
        //     console.log(jsonResult);
        //     debugger
            const fileName = 'meters_report_generators.zip'
            if (false || !!document.documentMode) {
                window.navigator.msSaveBlob(blob, fileName);
            } else {
                const url = window.URL || window.webkitURL;
                link = url.createObjectURL(result);
                const a = $("<a />");
                a.attr("download", fileName);
                a.attr("href", link);
                $("body").append(a);
                a[0].click();
                a.remove();
            }
        })
        .catch(console.error)
};

function refillFuel() {
    const fuelRefill = document.querySelector('#fuelRefill').value
    const refillType = getDataFromDatalist('#fuelRefillData', '#inputFuelRefill')
    if (fuelRefill <= 0 || refillType == '' || refillType == undefined) {
        alert("Перевірте вказані дані")
        return;
    }
    $.post(ajaxURL, {
        action: "refillFuel",
        fuelRefill,
        refillType
    }, function (result) {
        const data = JSON.parse(result)
        if (data['response'] != 200) {
            alert(data['message'])
            return;
        }
        ressetFields(['fuelRefill', 'inputFuelRefill'])
        alert("Успішно відправлено запит на поповнення.\n" +
            "Після підтвердження менеджера, паливо буде нараховано на аптеку.");
        mAreaGenerator()
    });
}
//// ------------------------ ||| GENERATOR ||| --------------------------------------- 