google.load("visualization", "1", {packages:["corechart", 'line']});
google.setOnLoadCallback(drawChart);

success: function drawChart(arrayChart, ac) {
    $('#chart').html('');
    tableChart = new google.visualization.DataTable();
    tableChart.addColumn('string','Месяц');
    tableChart.addRows(12);
    NumberChart = 1;
    month = 0;
    if (arrayChart) {
        $.each(arrayChart, function (row, val) {
            if (month == 0) {
                tochka = val["point"];
                schetchik = val["count"];
                for (i = 0; i < 12; i++) {
                    valTable = i + 1;
                    tableChart.setValue(i, 0, '' + valTable);
                }
                month++;
            }
            if (schetchik != val["count"]) {
                $('#chart').append('<div id="chart' + NumberChart + '"  style="width: 800px; height: 400px; align:center;"></div>');
                var chart = new google.visualization.LineChart(document.getElementById('chart' + NumberChart));
                chart.draw(eval(tableChart), optionsChart);
                NumberChart++;

                tableChart = new google.visualization.DataTable();

                tableChart.addColumn('string', 'Месяц');
                tableChart.addRows(12);

                month = 0;

                tochka = val["point"];
                schetchik = val["count"];
                for (i = 0; i < 12; i++) {
                    valTable = i + 1;
                    tableChart.setValue(i, 0, '' + valTable);
                }
                month++;

            }


            tableChart.addColumn('number', val['year']);
            for (i = 0; i < 12; i++) {
                valTable = i + 1;
                tableChart.setValue(i, month, val[valTable]);

            }
            month++;

            switch ($('.radioBox[checked]').val()) {
                case '0':
                    vAxisTitle = 'Показники'
                    break
                case '1':
                    vAxisTitle = 'Споживання'
                    break
            }

            optionsChart = {
                title: 'Точка: ' + tochka + '.  Лічильник: № ' + schetchik + '.',
                hAxis: {title: 'Месяц'},
                vAxis: {title: vAxisTitle}
            };


            if (ac - 1 == row) {
                $('#chart').append('<div id="chart' + NumberChart + '"  style="width: 800px; height: 400px; align:center;"></div>');
                var chart = new google.visualization.LineChart(document.getElementById('chart' + NumberChart));
                chart.draw(eval(tableChart), optionsChart);
            }


        });
    }
}





