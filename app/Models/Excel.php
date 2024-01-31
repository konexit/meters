<?php

namespace App\Models;

use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use DateTime;
use ZipArchive;
use Exception;
use IntlDateFormatter;

class Excel extends Model
{
    private $spreadsheet;

    private $zipFileName = "reports_counters.zip";

    private $startRowGeneDay = 10;

    private $startRowTotalGen = 3;
    private $startColumnTotalGen = 2;

    private $countColumsTable = 0;

    private $countMonth = 0;

    private $currRow = 0;

    private $coordinateColumns = [];

    private $mapSpreadsheets = [];

    private $sheet;

    private $indexSheet = 0;

    private $colorTableARGB = "bcbcbc";

    private $styleCell = [
        "allBordersTHIN" =>  [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ]
        ],
        "outlineBordersTHICK" => [
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THICK,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ],
        "alignment" =>  [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ],
        "alignmentLeft" =>  [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ],
        "alignmentRight" =>  [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ],
        "outlineBordersTHIN" => [
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ]
            ]
        ]
    ];

    private  $headerConfigTable = [
        "headerMonthRowStart" => 2,
        "headerTypeCounterRowStart" => 3,
        "headerTableRowStart" => 4,
    ];

    public function createReports($typeReport, $dataJson)
    {
        try {
            switch ($typeReport) {
                case "counters": {
                        $this->createZipFileReportsCounter(json_decode(json_encode($dataJson)));
                        break;
                    }
                case "generators": {
                        $this->createZipFileReportsGenerator(json_decode(json_encode($dataJson)));
                        break;
                    }
                case "totalGenerators": {
                        $this->createZipFileReportsTotalGenerator(json_decode(json_encode($dataJson)));
                        break;
                    }
            }
        } catch (Exception $e) {
            $data = [
                "error" => "Caught exception: " .  $e->getMessage(),
                "meessage" => "Try again or contact the IT department."
            ];
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data);
        }
    }

    private function createZipFileReportsTotalGenerator($dataJson)
    {
        $generator = new Generator();
        $mapTypeGen = [];
        foreach ($generator->getTypeGenerators() as $type) {
            $mapTypeGen[$type['id']] = $type['type'];
        }

        foreach ($dataJson->meters as $report) {
            foreach ($report->data as $type => $years) {
                if (!array_key_exists($type, $this->mapSpreadsheets)) {
                    $this->spreadsheet = new Spreadsheet();
                    $this->indexSheet = 0;
                    $this->mapSpreadsheets[$type] = [
                        "fileName" => "Загальний звіт по генераторам із типом палива: " . $mapTypeGen[$type] . " за період (" . $report->startDate . " - " . $report->endDate . ").xlsx"
                    ];
                } else {
                    $this->indexSheet = $this->mapSpreadsheets[$type]["indexSheet"] + 1;
                    $this->spreadsheet = $this->mapSpreadsheets[$type]["spreadsheet"];
                    $this->spreadsheet->createSheet();
                    $this->spreadsheet->setActiveSheetIndex($this->indexSheet);
                }
                $this->sheet = $this->spreadsheet->getActiveSheet()->setTitle(mb_strlen("test",  'UTF-8') < 31 ? $mapTypeGen[$type] : mb_substr($mapTypeGen[$type], 0, 28, 'UTF-8') . "...");
                $this->createReportTotalGenerator($this->sheet, $report, $years);
                $this->mapSpreadsheets[$type]["spreadsheet"] = $this->spreadsheet;
                $this->mapSpreadsheets[$type]["indexSheet"] = $this->indexSheet;
            }
        }

        $zip = new ZipArchive();
        $zip_name = $this->zipFileName;

        if ($zip->open($zip_name, ZipArchive::CREATE) !== true) {
            exit('not created');
        }

        $arrayFilenames  = [];
        if (count($this->mapSpreadsheets) == 0) {
            $emptyFileName = 'Відсутні дані генераторів.xlsx';
            $temp_file = tempnam(sys_get_temp_dir(), $emptyFileName);
            $spreadsheet = new Spreadsheet();
            $writer = new Xlsx($spreadsheet);
            $writer->save($temp_file);
            $zip->addFile($temp_file, $emptyFileName);
            array_push($arrayFilenames, $temp_file);
        } else {
            foreach ($this->mapSpreadsheets as $dataSpreadsheet) {
                $temp_file = tempnam(sys_get_temp_dir(), $dataSpreadsheet["fileName"]);
                $writer = new Xlsx($dataSpreadsheet["spreadsheet"]);
                $writer->save($temp_file);
                $zip->addFile($temp_file, $dataSpreadsheet["fileName"]);
                array_push($arrayFilenames, $temp_file);
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_name . '"');
        header('Content-Length: ' . filesize($zip_name));

        readfile($zip_name);

        foreach ($arrayFilenames as $filename) {
            unlink($filename);
        }

        unlink($zip_name);
    }

    private function createZipFileReportsGenerator($dataJson)
    {
        foreach ($dataJson->meters as $report) {
            foreach ($report->data as $key => $pharmacy) {
                if (!array_key_exists($report->company_1s_code, $this->mapSpreadsheets)) {
                    $this->spreadsheet = new Spreadsheet();
                    $this->indexSheet = 0;
                    $this->mapSpreadsheets[$report->company_1s_code] = [
                        "fileName" => "Звіт по генераторам по " . ($dataJson->groupBy == 'day' ? "дням" : "місяцям") . " компанії: '" . $report->companyName . "' за період (" . $report->startDate . " - " . $report->endDate . ").xlsx"
                    ];
                } else {
                    $this->indexSheet = $this->mapSpreadsheets[$report->company_1s_code]["indexSheet"] + 1;
                    $this->spreadsheet = $this->mapSpreadsheets[$report->company_1s_code]["spreadsheet"];
                    $this->spreadsheet->createSheet();
                    $this->spreadsheet->setActiveSheetIndex($this->indexSheet);
                }
                $this->sheet = $this->spreadsheet->getActiveSheet()->setTitle(mb_strlen($pharmacy->name,  'UTF-8') < 31 ? $pharmacy->name : mb_substr($pharmacy->name, 0, 28, 'UTF-8') . "...");
                $this->createReportGenerator($this->sheet, $report, $pharmacy, $dataJson->groupBy);
                $this->mapSpreadsheets[$report->company_1s_code]["spreadsheet"] = $this->spreadsheet;
                $this->mapSpreadsheets[$report->company_1s_code]["indexSheet"] = $this->indexSheet;
            }
        }

        $zip = new ZipArchive();
        $zip_name = $this->zipFileName;

        if ($zip->open($zip_name, ZipArchive::CREATE) !== true) {
            exit('not created');
        }

        $arrayFilenames  = [];
        if (count($this->mapSpreadsheets) == 0) {
            $emptyFileName = 'Відсутні дані генераторів.xlsx';
            $temp_file = tempnam(sys_get_temp_dir(), $emptyFileName);
            $spreadsheet = new Spreadsheet();
            $writer = new Xlsx($spreadsheet);
            $writer->save($temp_file);
            $zip->addFile($temp_file, $emptyFileName);
            array_push($arrayFilenames, $temp_file);
        } else {
            foreach ($this->mapSpreadsheets as $dataSpreadsheet) {
                $temp_file = tempnam(sys_get_temp_dir(), $dataSpreadsheet["fileName"]);
                $writer = new Xlsx($dataSpreadsheet["spreadsheet"]);
                $writer->save($temp_file);
                $zip->addFile($temp_file, $dataSpreadsheet["fileName"]);
                array_push($arrayFilenames, $temp_file);
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_name . '"');
        header('Content-Length: ' . filesize($zip_name));

        readfile($zip_name);

        foreach ($arrayFilenames as $filename) {
            unlink($filename);
        }

        unlink($zip_name);
    }

    private function createZipFileReportsCounter($dataJson)
    {
        foreach ($dataJson->meters as $report) {
            if (!array_key_exists($report->typeCounter, $this->mapSpreadsheets)) {
                $this->spreadsheet = new Spreadsheet();
                $this->indexSheet = 0;
                $this->mapSpreadsheets[$report->typeCounter] = [
                    "fileName" => "Розхід '" . $report->typeCounter . "' по витратним Договорам за період (" . $report->startDate . " - " . $report->endDate . ").xlsx"
                ];
            } else {
                $this->indexSheet = $this->mapSpreadsheets[$report->typeCounter]["indexSheet"] + 1;
                $this->spreadsheet = $this->mapSpreadsheets[$report->typeCounter]["spreadsheet"];
                $this->spreadsheet->createSheet();
                $this->spreadsheet->setActiveSheetIndex($this->indexSheet);
            }
            $this->sheet = $this->spreadsheet->getActiveSheet()->setTitle(mb_strlen($report->typePharmacy,  'UTF-8') < 31 ? $report->typePharmacy : mb_substr($report->typePharmacy, 0, 28, 'UTF-8') . "...");
            $this->createReport($this->sheet, $report);
            $this->mapSpreadsheets[$report->typeCounter]["spreadsheet"] = $this->spreadsheet;
            $this->mapSpreadsheets[$report->typeCounter]["indexSheet"] = $this->indexSheet;
        }
        $zip = new ZipArchive();
        $zip_name = $this->zipFileName;

        if ($zip->open($zip_name, ZipArchive::CREATE) !== true) {
            exit('not created');
        }

        $arrayFilenames  = [];
        foreach ($this->mapSpreadsheets as $dataSpreadsheet) {
            $temp_file = tempnam(sys_get_temp_dir(), $dataSpreadsheet["fileName"]);
            $writer = new Xlsx($dataSpreadsheet["spreadsheet"]);
            $writer->save($temp_file);
            $zip->addFile($temp_file, $dataSpreadsheet["fileName"]);
            array_push($arrayFilenames, $temp_file);
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_name . '"');
        header('Content-Length: ' . filesize($zip_name));

        readfile($zip_name);

        foreach ($arrayFilenames as $filename) {
            unlink($filename);
        }

        unlink($zip_name);
    }

    private function createReport($sheet, $dataJson)
    {
        $this->setCountColumsTable($dataJson);
        $this->setTitleTable($sheet, $dataJson);
        $this->setTitleTradePointsColumn($sheet, $dataJson);
        $this->setMonthAndCounter($sheet, $dataJson);
        $this->setDataByRow($sheet, $dataJson);
        $this->setStyle($sheet, $dataJson);
    }

    private function createReportTotalGenerator($sheet, $dataJson, $years)
    {
        $this->setTitleTotalTableGen($sheet, $dataJson);
        $this->setTotalDateAndPokazGen($sheet, $dataJson, $years);
    }

    private function setTitleTotalTableGen($sheet, $dataJson)
    {
        $sheet->setCellValue([$this->startColumnTotalGen + 2, $this->startRowTotalGen - 2], "Отримано")->mergeCells([
            $this->startColumnTotalGen + 2, $this->startRowTotalGen - 2,
            $this->startColumnTotalGen + 3, $this->startRowTotalGen - 2,
        ]);
        $sheet->setCellValue([$this->startColumnTotalGen + 4, $this->startRowTotalGen - 2], "Використано");
        $sheet->setCellValue([$this->startColumnTotalGen + 5, $this->startRowTotalGen - 2], "Повернено");
        $sheet->setCellValue([$this->startColumnTotalGen + 6, $this->startRowTotalGen - 2], "Залишок")->mergeCells([
            $this->startColumnTotalGen + 6, $this->startRowTotalGen - 2,
            $this->startColumnTotalGen + 7, $this->startRowTotalGen - 2
        ]);


        $sheet->getStyle([$this->startColumnTotalGen + 2, $this->startRowTotalGen - 2, $this->startColumnTotalGen + 7, $this->startRowTotalGen - 2])
            ->applyFromArray($this->styleCell["alignment"]);


        foreach ($dataJson->headers->generator as $keyField => $optionsField) {
            $sheet->setCellValue([$this->startColumnTotalGen + $optionsField->key, $this->startRowTotalGen - 1], $optionsField->name);
        }

        $sheet->getStyle([$this->startColumnTotalGen, $this->startRowTotalGen - 1, $this->startColumnTotalGen + 7, $this->startRowTotalGen - 1])
            ->applyFromArray($this->styleCell["allBordersTHIN"])->applyFromArray($this->styleCell["alignment"])->getFont()->setBold(true);
    }

    private function setTotalDateAndPokazGen($sheet, $dataJson, $years)
    {
        $dateFormatter = new IntlDateFormatter('uk_UA', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'LLLL');
        $indexFieldGenerator = $dataJson->headers->generator;

        $currentRowIndex = $this->startRowTotalGen;
        $countRowsTrdPnt = 0;

        foreach ($years as $year => $months) {
            $sheet->setCellValue([$this->startColumnTotalGen, $currentRowIndex], $year)->mergeCells([$this->startColumnTotalGen, $currentRowIndex, $this->startColumnTotalGen + 7, $currentRowIndex])
                ->getStyle([$this->startColumnTotalGen, $currentRowIndex])
                ->applyFromArray($this->styleCell["alignment"])->getFont()->setBold(true);
            $currentRowIndex++;

            foreach ($months as $month => $areaIds) {
                $sheet->setCellValue([$this->startColumnTotalGen, $currentRowIndex], $this->uppercaseFirstLetter($dateFormatter->format(mktime(0, 0, 0, $month, 1))))
                    ->mergeCells([$this->startColumnTotalGen, $currentRowIndex, $this->startColumnTotalGen + 7, $currentRowIndex])
                    ->getStyle([$this->startColumnTotalGen, $currentRowIndex])
                    ->applyFromArray($this->styleCell["alignment"])->getFont()->setBold(true);
                $currentRowIndex++;

                foreach ($areaIds as $areaId => $data) {

                    foreach ($dataJson->headers->generator as $keyField => $optionsField) {
                        $sheet->setCellValue([$this->startColumnTotalGen + $optionsField->key, $currentRowIndex], $data->$keyField)
                        ->getStyle([$this->startColumnTotalGen + $optionsField->key, $currentRowIndex])
                        ->applyFromArray($this->styleCell["alignmentLeft"]);
                    }
                    $sheet->setCellValue([$this->startColumnTotalGen - 1, $currentRowIndex], ++$countRowsTrdPnt);
                    $currentRowIndex++;
                }
            }
        }

        $sheet->getStyle([$this->startColumnTotalGen - 1, $this->startRowTotalGen, $this->startColumnTotalGen - 1, $currentRowIndex - 1])
            ->applyFromArray($this->styleCell["alignmentRight"]);
        $sheet->getStyle([$this->startColumnTotalGen + 2, $this->startRowTotalGen, $this->startColumnTotalGen + 7, $currentRowIndex - 1])
            ->applyFromArray($this->styleCell["alignment"])->getFont()->setBold(true);
        $sheet->getStyle([$this->startColumnTotalGen, $this->startRowTotalGen, $this->startColumnTotalGen + 7, $currentRowIndex - 1])
            ->applyFromArray($this->styleCell["allBordersTHIN"]);
    }

    private function createReportGenerator($sheet, $dataJson, $pharmacy, $type)
    {
        $this->setInfoTableData($sheet, $dataJson, $pharmacy);
        $this->setTitleTableGen($sheet, $dataJson, $type);
        $this->setDateAndPokazGen($sheet, $dataJson, $pharmacy, $type);
    }

    private function setTitleTable($sheet, $dataJson)
    {
        $mergedCellCoordinate = [1, 1, $this->countColumsTable, 1];
        $sheet->setCellValue([1, 1], "ДОГОВОРА ОРЕНДИ ТОВ '" . $dataJson->typePharmacy . "'")->mergeCells($mergedCellCoordinate)
            ->getStyle($mergedCellCoordinate)->applyFromArray($this->styleCell["outlineBordersTHIN"])->getFont()->setSize(18);
    }

    private function setTitleTableGen($sheet, $dataJson, $type)
    {
        $mainTitle = ($type == "day") ? "Дата роботи" : "Місяць роботи";
        $sheet->setCellValue([1, $this->startRowGeneDay], $mainTitle)->mergeCells([1, $this->startRowGeneDay, 1, $this->startRowGeneDay + 1]);
        $sheet->setCellValue([2, $this->startRowGeneDay], "Час роботи, години")->mergeCells([2, $this->startRowGeneDay, count(get_object_vars($dataJson->headers->generator)) + 1, $this->startRowGeneDay]);
        foreach ($dataJson->headers->generator as $keyField => $optionsField) {
            $sheet->setCellValue([$optionsField->key + 1, $this->startRowGeneDay + 1], $optionsField->name);
        }
    }

    private function setTitleTradePointsColumn($sheet, $dataJson)
    {
        $countTradePoints = count($dataJson->headers->tradePoint);
        $sheet->mergeCells([1, $this->headerConfigTable["headerMonthRowStart"], $countTradePoints, $this->headerConfigTable["headerTypeCounterRowStart"]]);

        foreach ($dataJson->headers->tradePoint as $columnTitle) {
            $this->coordinateColumns['tradePoint'][0][$columnTitle->keyName] = $columnTitle->key;
            $sheet->setCellValue([$columnTitle->key, $this->headerConfigTable["headerTableRowStart"]], $columnTitle->name);
        }

        $sheet->getStyle([1, 1, $this->countColumsTable, $this->headerConfigTable["headerTypeCounterRowStart"] + 1])
            ->applyFromArray($this->styleCell["alignment"])->getFont()->setBold(true);

        if (isset($dataJson->colorARGB->table)) {
            if ($dataJson->colorARGB->table[0] == "#") $this->colorTableARGB = substr($dataJson->colorARGB->table, 1);
            else $this->colorTableARGB = $dataJson->colorARGB->table;
        }

        $sheet->getStyle([1, 1, $this->countColumsTable, $this->headerConfigTable["headerTypeCounterRowStart"]])
            ->applyFromArray($this->collorCellStyle($this->colorTableARGB));

        $sheet->getStyle([$countTradePoints + 1, $this->headerConfigTable["headerMonthRowStart"], $this->countColumsTable, $this->headerConfigTable["headerTypeCounterRowStart"]])
            ->applyFromArray($this->styleCell["allBordersTHIN"])->applyFromArray($this->styleCell["alignment"])->getFont()->setSize(14);
    }

    private function setMonthAndCounter($sheet, $dataJson)
    {
        $dateFormatter = new IntlDateFormatter('uk_UA', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'LLLL');
        $startDate = DateTime::createFromFormat('Y-m-d', $dataJson->startDate);
        $currentColumnMonth = count($dataJson->headers->tradePoint) + 1;
        $monthNum = $this->countMonth == 12 ? 1 : intval($startDate->format('m'));

        for ($i = 0; $i < $this->countMonth; $i++) {
            $headerMonthRowStart = $this->headerConfigTable["headerMonthRowStart"];
            $sheet->setCellValue([$currentColumnMonth, $headerMonthRowStart], $this->uppercaseFirstLetter($dateFormatter->format(mktime(0, 0, 0, $monthNum, 1))));
            $countHeadersCounter = count($dataJson->headers->counter);

            $sheet->mergeCells([$currentColumnMonth, $headerMonthRowStart, $currentColumnMonth + $countHeadersCounter - 1, $headerMonthRowStart]);

            $headerTypeCounterRowStart = $this->headerConfigTable["headerTypeCounterRowStart"];
            $sheet->setCellValue([$currentColumnMonth, $headerTypeCounterRowStart], $dataJson->typeCounter);
            $sheet->mergeCells([$currentColumnMonth, $headerTypeCounterRowStart, $currentColumnMonth + $countHeadersCounter - 1, $headerTypeCounterRowStart]);

            $mapCounterCells = [];
            foreach ($dataJson->headers->counter as $counter) {
                $counterColumn = $currentColumnMonth - 1 + $counter->key;
                $sheet->setCellValue([$counterColumn, $this->headerConfigTable["headerTableRowStart"]], $counter->name);
                $mapCounterCells[$counter->keyName] = $counterColumn;
            }
            $this->coordinateColumns['counter'][$monthNum] = $mapCounterCells;
            $monthNum++;
            $currentColumnMonth = $currentColumnMonth + $countHeadersCounter;
        }
    }

    private function setDateAndPokazGen($sheet, $dataJson, $pharmacy, $type)
    {
        $indexFieldGenerator = $dataJson->headers->generator;
        $countFieldGen = count(get_object_vars($indexFieldGenerator));

        $currentRowIndex = $this->setMainGenData($sheet, $dataJson, $indexFieldGenerator, $countFieldGen, $pharmacy, $type);

        $sheet->getStyle([1,  10, $countFieldGen + 1, $currentRowIndex - 1])->applyFromArray($this->styleCell["alignment"]);
        $totalData = [["Всього", $pharmacy->sum], ["Залишок каністр", $pharmacy->balanceCanister . " одиниць"], ["Залишок палива", $pharmacy->balanceFuel . " літрів"]];
        foreach ($totalData as $data) {
            $sheet->setCellValue([1, $currentRowIndex], $data[0])->mergeCells([1, $currentRowIndex, $countFieldGen, $currentRowIndex])
                ->getStyle([1, $currentRowIndex, $countFieldGen, $currentRowIndex])->applyFromArray($this->styleCell["alignmentRight"])->getFont()->setBold(true);
            $sheet->setCellValue([$countFieldGen + 1, $currentRowIndex], $data[1])->getStyle([$countFieldGen + 1, $currentRowIndex])->applyFromArray($this->styleCell["alignment"]);
            $currentRowIndex++;
        }
        $sheet->getStyle([1, $this->startRowGeneDay, $countFieldGen + 1, $currentRowIndex - 1])->applyFromArray($this->styleCell["allBordersTHIN"]);
    }

    private function setMainGenData($sheet, $dataJson, $indexFieldGenerator, $countFieldGen, $pharmacy, $type)
    {
        $dateFormatter = new IntlDateFormatter('uk_UA', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'LLLL');
        $startDate = DateTime::createFromFormat('Y-m-d', $dataJson->startDate);
        $endDate = DateTime::createFromFormat('Y-m-d', $dataJson->endDate);
        $currentRowIndex = 12;

        foreach ($pharmacy->data as $year => $monthData) {
            if (intval($startDate->format('Y')) != intval($endDate->format('Y'))) {
                $sheet->setCellValue([1, $currentRowIndex], $year)->mergeCells([1, $currentRowIndex, $countFieldGen + 1, $currentRowIndex]);
                $currentRowIndex++;
            }

            switch ($type) {
                case "day": {
                        foreach ($monthData as $month => $dayData) {
                            if (intval($startDate->format('Y')) != intval($endDate->format('Y')) || intval($startDate->format('m')) != intval($endDate->format('m'))) {
                                $sheet->setCellValue([1, $currentRowIndex], $this->uppercaseFirstLetter($dateFormatter->format(mktime(0, 0, 0, $month, 1))))->mergeCells([1, $currentRowIndex, $countFieldGen + 1, $currentRowIndex]);
                                $currentRowIndex++;
                            }
                            foreach ($dayData as $day => $data) {
                                $sheet->setCellValue([1, $currentRowIndex], $day);
                                if (count($data) > 1) {
                                    $sheet->mergeCells([1, $currentRowIndex, 1, $currentRowIndex + count($data) - 1]);
                                }
                                foreach ($data as $pokazData) {
                                    foreach ($pokazData as $keyField => $value) {
                                        $sheet->setCellValue([$indexFieldGenerator->$keyField->key + 1, $currentRowIndex], $value);
                                    }
                                    $currentRowIndex++;
                                }
                            }
                        }
                        break;
                    }
                case "month": {
                        foreach ($monthData as $month => $data) {
                            $sheet->setCellValue([1, $currentRowIndex], $this->uppercaseFirstLetter($dateFormatter->format(mktime(0, 0, 0, $month, 1))));
                            foreach ($data as $pokazData) {
                                foreach ($pokazData as $keyField => $value) {
                                    $sheet->setCellValue([$indexFieldGenerator->$keyField->key + 1, $currentRowIndex], $value);
                                }
                            }
                            $currentRowIndex++;
                        }
                        break;
                    }
            }
        }
        return $currentRowIndex;
    }

    private function setDataByRow($sheet, $dataJson)
    {
        $this->currRow = $this->headerConfigTable["headerTableRowStart"] + 1;

        $years = array_keys((array)$dataJson->data);
        rsort($years);
        foreach ($years as $year) {
            $this->setYearData($sheet, $year, $this->countColumsTable);
            foreach ($dataJson->data->$year as $tradePointId => $tradePoint) {
                $this->setDataTradePoint($sheet, $tradePoint);
                $this->setDataCounter($sheet, $tradePoint->counters);
            }
        }
    }

    private function setYearData($sheet, $year, $tableSizeAllrows)
    {
        $sheet->setCellValue([1, $this->currRow], $year);
        $yearCell = [1, $this->currRow, $tableSizeAllrows, $this->currRow];
        $sheet->getStyle([1, 1, $this->countColumsTable, $this->headerConfigTable["headerTypeCounterRowStart"]])
            ->getFont()->setBold(true);
        $sheet->mergeCells($yearCell)->getStyle($yearCell)
            ->applyFromArray($this->styleCell["allBordersTHIN"])->applyFromArray($this->styleCell["alignment"])->applyFromArray($this->collorCellStyle($this->colorTableARGB));
        $this->currRow++;
    }

    private function setDataTradePoint($sheet, $tradePoint)
    {
        $rowTradePointCoordinate = $this->coordinateColumns['tradePoint'][0];
        foreach ($tradePoint as $titleName => $value) {
            if (array_key_exists($titleName, $rowTradePointCoordinate)) {
                $columnTradePoint = $rowTradePointCoordinate[$titleName];
                $sheet->setCellValue([$columnTradePoint, $this->currRow], $value);

                $countCounters = count(array_keys((array)$tradePoint->counters));
                if ($countCounters > 1) $sheet->mergeCells([$columnTradePoint, $this->currRow, $columnTradePoint, $this->currRow + $countCounters - 1]);
            }
        }
    }

    private function setDataCounter($sheet, $counters)
    {
        foreach ($counters as $counterId => $counter) {
            foreach ($counter as $dataCounter) {
                $rowCounterCoordinate = $this->coordinateColumns['counter'][$dataCounter->month];
                foreach ($dataCounter as $titleName => $value) {
                    if (array_key_exists($titleName, $rowCounterCoordinate)) {
                        $sheet->setCellValue([$rowCounterCoordinate[$titleName], $this->currRow], $value);
                    }
                }
            }
            $this->currRow++;
        }
    }

    private function setCountColumsTable($dataJson)
    {
        $startDate = DateTime::createFromFormat('Y-m-d', $dataJson->startDate);
        $endDate = DateTime::createFromFormat('Y-m-d', $dataJson->endDate);
        $this->countMonth = $startDate->format('Y') != $endDate->format('Y') ? 12 : $endDate->format('m') - $startDate->format('m') + 1;
        $this->countColumsTable = (count($dataJson->headers->counter) * $this->countMonth) + count($dataJson->headers->tradePoint);
    }

    private function setStyle($sheet, $dataJson)
    {
        $sheet->getTabColor()->setRGB($this->colorTableARGB);

        $countHeaderCounter = count($dataJson->headers->counter);
        $countHeaderTradePoint = count($dataJson->headers->tradePoint);

        for ($i = 0; $i < $this->countMonth; $i++) {
            $startColumn = ($countHeaderCounter * $i) + 1 + $countHeaderTradePoint;
            $sheet->getStyle([$startColumn,  $this->headerConfigTable["headerTableRowStart"], $startColumn + $countHeaderCounter - 1, $this->currRow - 1])
                ->applyFromArray($this->styleCell["outlineBordersTHIN"])->applyFromArray($this->styleCell["alignment"]);


            $sheet->getStyle([$startColumn + $countHeaderCounter - 1,  $this->headerConfigTable["headerTableRowStart"], $startColumn + $countHeaderCounter - 1, $this->currRow - 1])
                ->applyFromArray($this->collorCellStyle($this->makeColorDarker($this->colorTableARGB)))
                ->applyFromArray($this->styleCell["allBordersTHIN"])
                ->getFont()->setSize(13)->setBold(true);
        }
    }

    private function collorCellStyle($color)
    {
        return ['fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'argb' => $color,
            ]
        ]];
    }

    private function makeColorDarker($color)
    {
        $red = hexdec(substr($color, 0, 2));
        $green = hexdec(substr($color, 2, 2));
        $blue = hexdec(substr($color, 4, 2));

        $darkerValue = 50;
        $red = max(0, $red - $darkerValue);
        $green = max(0, $green - $darkerValue);
        $blue = max(0, $blue - $darkerValue);

        $darkerColor = sprintf('%02X%02X%02X', $red, $green, $blue);

        return $darkerColor;
    }

    private function uppercaseFirstLetter($text)
    {
        return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($text, 1, mb_strlen($text), 'UTF-8');
    }

    private function setInfoTableData($sheet, $dataJson, $pharmacy)
    {
        $additionalDataHeaders = [
            "Назва Аптеки" => $pharmacy->name,
            "Адреса" => $pharmacy->addr,
            "Компанія" => $dataJson->companyName,
            "1s код компанії" => $dataJson->company_1s_code,
            "Генератор" => $pharmacy->generatorName,
            "Серійний номер" => $pharmacy->generatorSerial,
            "Тип" => $pharmacy->generatorType
        ];

        $sheet->setCellValueByColumnAndRow(1, 1, "Додаткові дані");

        $rowIndex = 2;
        foreach ($additionalDataHeaders as $header => $value) {
            $sheet->setCellValueByColumnAndRow(1, $rowIndex, $header);
            $sheet->setCellValueByColumnAndRow(2, $rowIndex, $value);
            $rowIndex++;
        }

        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray($this->styleCell["alignment"])->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2:A8')->getFont()->setBold(true);
        $sheet->getStyle('A2:B8')->applyFromArray($this->styleCell["alignmentLeft"]);
        $sheet->getStyle('A1:B8')->applyFromArray($this->styleCell["allBordersTHIN"]);
    }
}
