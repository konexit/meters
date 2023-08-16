const ajaxURL = `${bUrl}/ajax`
var usId;
var usRights;
var usArea;
var usAreaName;
var ecId = 0;
var pMonth = 0;
var pYear = 0;

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

function getStatistic(mode) {
    showSet([$("#countStat"), $("#counterList"), $('#cYear')]);
    unit = getDataFromDatalist('#cUnit', '#inputCUnit')
    if (unit != undefined) {
        $.post(ajaxURL, {
            action: 'getCountersStat',
            cYear: $('#hcYear :selected').val(),
            mod: mode,
            cUnit: unit,
            cType: $('#hcType :selected').val()
        }, function (result) {
            rez = result
            $("#cStat").html(rez['table'])
            $('#chart').html('')
            if (unit) drawChart(rez['diagram'], rez['arrayCount'])
        }, 'json')
    }
}

function getCounters() {
    if ($("#countStat").is(":visible")) {
        getStatistic($('.radioBox[checked]').val());
        return;
    } else {
        unit = getDataFromDatalist('#cUnit', '#inputCUnit')
        if (unit != undefined) {
            $.post(ajaxURL, {
                action: "getCounters",
                cUnit: unit,
                cType: $('#hcType :selected')[0].value,
                state: $("#setCounters").is(":visible") ? 0 : 1
            }, function (result) {
                ($("#setCounters").is(":visible")) ? $("#allCount").html(result) : $("#counter").html(result);
            });
        }
    }
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

function showSet(mShow) {
    masObj = [
        $('#pokazEdit'),
        $('#cYear'),
        $("#countStat"),
        $("#inputPokaz"),
        $("#counterList"),
        $("#setCounters"),
        $("#addCounters"),
        $("#counter"),
        $("#log"),
        $("#allUnit"),
        $("#cPass"),
        $("#allUser"),
        $("#addUnit"),
        $("#adminPanel"),
        $("#addUser"),
        $(".table"),
        $("#reportCounter")
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
        $("#report")
    ];
    $.each(arrCss, function (ind, val) {
        val.css({ "color": "White", "text-shadow": "none", "background-position": "none" });
    })
    meIt.css({ "color": "#E88F00", "text-shadow": "1px 2px 3px #ddd", "background-position": "-99.99% 0" });
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
        mSh = [$("#addCounters")];
        showSet(mSh);
    }
    else {
        mSh = [$("#addCounters")];
        showSet(mSh);
    }
}

function enterPokaz(counterPK, counter) {
    if ($("#counterPokaz").val()) {
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
            mCaunter("#Pokaz")
        })
    } else alert('Введіть будь ласка показник!')
}

function addCounter() {
    if ($('#cType :selected').val() && document.querySelector('#sPokaz').value != '') {
        if ($("#cNumer").val().length > 30 || $("#cName").val().length > 50 || $('#cnType').val().length > 30) {
            alert("Ви вписали занадто багато символів")
            return;
        }
        unit = getDataFromDatalist('#cArr', '#inputCArr')
        if (unit == undefined || unit == '') {
            if (unit == '') alert("Виберіть варіант із списка")
            return;
        } else {
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
                document.querySelector('#inputCArr').value = ''
                $('#cNumer').val("")
                $('#cName').val("")
                $('#cType').val("")
                $("#sPokaz").val("")
                $("#cnType").val("")
                document.querySelector('#cState').checked = false
                mCountAdd()
            });
            ecId = 0
        }
    } else alert("Обов'язкове поле не заповнене")
}

function statInfo() {
    getStatistic($('.radioBox[checked]').val());
}

function mCountAdd() {
    ecId = 0;
    document.querySelector('#inputCArr').value = ''
    $('#cNumer').val("");
    $('#cName').val("");
    $('#cType').val("");
    showSet([$("#setCounters"), $("#counterList")]);
    setBackground($("#aCounter"));
    getCounters();
}

function getAllUsers() {
    $.post(ajaxURL, {
        action: 'getAllUser'
    }, function (result) {
        $("#allUser").html(result);
    })
}

function editPokaz(cId, counter) {
    editPokazAjax(cId, counter)
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
                eval(result);
            }
            )
        }
    );
}
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
    if (!$("#usName").val() || !$("#usSurname").val() || !$("#usLogin").val() || !$("#usPass").val()) alert("Обов'язкові поля не заповнені");
    else {
        unit = getDataFromDatalist('#unitUs', '#inputUnitUs')
        if (unit == undefined || unit == '') alert("Виберіть варіант із списка")
        else
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
                document.querySelector('#usPass').disabled = false
                document.querySelector('#RightsUs').disabled = false
                $("#usName").val("");
                $("#usSurname").val("");
                $("#usLogin").val("");
                $("#usPass").val("");
                alert(res.success);
            })
    }
}

function maddUnit(edMode) {
    if (edMode) {
        $("#confirmUn").text("Редагувати");
        $("#confirmUn").attr("onClick", "confirmUnitEdit(" + edMode + ")");
    } else {
        $("#confirmUn").text("Додати підрозділ");
        $("#confirmUn").attr("onClick", "unADD()");
        $("#addUnit input").val("")
        setBackground($("#aArea"));
    }
    showSet([$("#adminPanel"), $("#addUnit")]);
}

function tradePointFormStatus(status) {
    if (status.checked) document.querySelector('#tradePointForm').style.display = 'block';
    else document.querySelector('#tradePointForm').style.display = 'none';
};


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

    if (!($("#unADDR").val() && $("#tel").val() && $("#unit").val())) {
        alert("Oбов'язкові поля не заповнені");
        return;
    }

    $.post(ajaxURL, {
        action: "unADD",
        addr: $("#unADDR").val().trim(),
        tel: $("#tel").val().trim(),
        unit: $("#unit").val().trim(),
        isTradePoint: isTradePoint,
        tradePointId: tradePointId,
        companyId: +document.querySelector('#companyId').value
    }, function (result) {
        res = JSON.parse(result);
        if (res.error) {
            alert(res.error);
            return
        }
        $(".unit").append('<option data-value="' + res.id + '"  data-text="' + res.unit.trim() + '">' + res.unit.trim() + '</option>');
        $("#unADDR").val("");
        $("#tel").val("");
        $("#unit").val("");
        alert("Підрозділ додано");
    });
}


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
    })
}

function unitEdit(uId, unit, addr, tel, isTradePoint = false, tradePointId = 0, companyId = 0) {
    $("#unit").val(unit);
    $("#unADDR").val(decodeURIComponent(addr));
    $("#tel").val(tel);
    document.querySelector('#isTradePoint').checked = isTradePoint
    if (isTradePoint) document.querySelector('#tradePointForm').style.display = 'block';
    else document.querySelector('#tradePointForm').style.display = 'none';
    document.querySelector('#tradePointId').value = tradePointId
    document.querySelector('#companyId').value = companyId
    maddUnit(uId);
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
        isTradePoint: isTradePoint,
        tradePointId: tradePointId,
        companyId: +document.querySelector('#companyId').value
    }, function (result) {
        res = JSON.parse(result);
        if (res.error) {
            alert(res.error);
            return
        }
        $("#unADDR").val("");
        $("#tel").val("");
        $("#unit").val("");
        alert("Дані підрозділа змінені");
        mallUnit();
    })
}

function mallUser() {
    showSet([$("#adminPanel"), $("#allUser")]);
    getAllUsers();
    setBackground($("#allUs"));
}

function userEdit(uId, uNam, uSur, uPass, uLogin, uArea, uRights) {
    $("#usName").val(uNam);
    $("#usSurname").val(uSur);
    $("#usLogin").val(uLogin);
    $("#usPass").val(uPass);
    document.querySelector('#inputUnitUs').value = uArea.trim()
    $("#RightsUs [text='" + uRights + "']").attr("selected", "selected");
    const dataset = document.querySelector(`#unitUs option[data-text="${document.querySelector('#inputUnitUs').value}"]`)?.dataset
    document.querySelector('#usPass').disabled = dataset.tradepointid
    document.querySelector('#RightsUs').disabled = dataset.tradepointid
    maddUser(uId);
}

function getDataValue(datalistSelector, selector) {
    return document.querySelector(`${datalistSelector} option[data-text="${document.querySelector(selector).value}"]`)?.dataset?.value
}

function confirmUserEdit(id) {
    if (!$("#usName").val() || !$("#usSurname").val() || !$("#usLogin").val() || !$("#usPass").val()) alert("Обов'язкові поля не заповнені");
    else {
        unit = getDataFromDatalist('#unitUs', '#inputUnitUs')
        if (unit == undefined || unit == '') alert("Виберіть варіант із списка")
        else
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
                document.querySelector('#usPass').disabled = false
                document.querySelector('#RightsUs').disabled = false
                $("#usName").val("");
                $("#usSurname").val("");
                $("#usLogin").val("");
                $("#usPass").val("");
                alert(res.success);
                mallUser();
            })
    }
}
function mchangePass() {
    showSet([$("#cPass")]);
    $("#confirmPass").val("");
    $("#oldPass").val("");
    $('#newPass').val("");
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
            $("#oldPw").val("");
            $("#newPw").val("");
            $('#confPw').val("");
            alert("Пароль успішно змінено")
        }
    });
}

function reportCounter() {
    showSet([$("#reportCounter")]);
    setBackground($("#report"));
}

function getReportCounter(event) {
    event.preventDefault();

    const startDate = document.getElementById('reportStartDate').value;
    const endDate = document.getElementById('reportEndDate').value;

    if (startDate > endDate) {
        alert("Перевірте введені діапазон звіта")
        return
    }

    const companies = getCompaniesAndColors('.company-color');
    if (companies.length == 0) {
        alert("Виберіть компанії")
        return
    }

    // let countDownload = +localStorage.getItem(document.cookie)
    // if (countDownload > 25) {
    //     alert("Ви перевищили ліміт завантажень\nДля продовження, будь ласка, зробіть грошовий внесок")
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

function getCompaniesAndColors(selector) {
    return Array.from(document.querySelectorAll(selector)).map(row => {
        if (!row.children[0].children[0].checked) return null
        return {
            "companyId": +row.dataset.companyid,
            "companyName": row.children[0].children[1].textContent,
            "color": row.children[1].children[0].value
        }
    }).filter(row => row !== null)
}

function companyUserDefaulValues() {
    unitInput = document.querySelector('#inputUnitUs')
    dataValue = getDataValue('#unitUs', '#inputUnitUs')
    unit;

    let disabledInputs = false;
    if (unitInput.value && dataValue != 0 && dataValue != undefined) {
        const dataset = document.querySelector(`#unitUs option[data-text="${document.querySelector('#inputUnitUs').value}"]`)?.dataset
        const isTradePoint = dataset.tradepointid;
        if (isTradePoint) {
            disabledInputs = true
            document.querySelector('#usLogin').value = dataset.tradepointid
            document.querySelector('#usPass').value = dataset.codeokpo
            document.querySelector('#RightsUs').value = 3
        }
    }
    document.querySelector('#usPass').disabled = disabledInputs
    document.querySelector('#RightsUs').disabled = disabledInputs
} 