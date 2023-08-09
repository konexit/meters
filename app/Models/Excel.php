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

    public function createReports($dataJson)
    {
        try {
            $this->createZipFileReports(json_decode(json_encode($dataJson)));
        } catch (Exception $e) {
            $data = [
                "error" => "Caught exception: " .  $e->getMessage(),
                "meessage" => "Try again or contact the IT department."
            ];
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data);
        }
    }

    private function createZipFileReports($dataJson)
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

    private function setTitleTable($sheet, $dataJson)
    {
        $mergedCellCoordinate = [1, 1, $this->countColumsTable, 1];
        $sheet->setCellValue([1, 1], "ДОГОВОРА ОРЕНДИ ТОВ '" . $dataJson->typePharmacy . "'")->mergeCells($mergedCellCoordinate)
            ->getStyle($mergedCellCoordinate)->applyFromArray($this->styleCell["outlineBordersTHIN"])->getFont()->setSize(18);
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
}
