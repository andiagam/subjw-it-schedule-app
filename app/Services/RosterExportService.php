<?php

namespace App\Services;

use App\Models\DayTask;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RosterExportService
{
    public function __construct(private RosterService $roster) {}

    public function download(CarbonImmutable $start): StreamedResponse
    {
        $spreadsheet = $this->make($start);
        $filename = 'roster-'.$start->format('Y-m').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function make(CarbonImmutable $start): Spreadsheet
    {
        $users = $this->roster->activeUsers();
        $days = $this->roster->days($start);
        $entries = $this->roster->entriesForMonth($start);
        $summaries = $this->roster->summaries($users, $entries);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($start->format('Y-m'));

        $headers = ['Employee', 'Job title'];

        foreach ($days as $day) {
            $headers[] = $day->format('j D');
        }

        $holidayDates = DayTask::query()
            ->where('kind', 'holiday')
            ->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->get()
            ->mapWithKeys(fn (DayTask $task) => [$task->date->toDateString() => true]);

        array_push($headers, 'ON', 'OFF', 'AL used', 'AL remaining', 'DP used', 'DP remaining', 'SL', 'PH');

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column.'1', $header);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach ($days as $index => $day) {
            if (! $day->isWeekend() && ! $holidayDates->has($day->toDateString())) {
                continue;
            }

            $column = Coordinate::stringFromColumnIndex($index + 3);
            $sheet->getStyle($column.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE4E6');
        }

        $statusColors = [
            'SHIFT' => 'FFA7F3D0',
            'OFF' => 'FFFEE2E2',
            'AL' => 'FFFCD34D',
            'DP' => 'FFC7D2FE',
            'SL' => 'FFFBCFE8',
            'PH' => 'FFFECDD3',
            'BLACK' => 'FF000000',
        ];

        foreach ($users as $rowIndex => $user) {
            $row = $rowIndex + 2;
            $summary = $summaries[$user->id];

            $sheet->setCellValue('A'.$row, $user->name);
            $sheet->setCellValue('B'.$row, $user->job_title);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);

            foreach ($days as $index => $day) {
                $column = Coordinate::stringFromColumnIndex($index + 3);
                $entry = $entries->get($user->id.'|'.$day->toDateString());
                $cell = $column.$row;

                if ($day->isWeekend() || $holidayDates->has($day->toDateString())) {
                    $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF1F2');
                }

                if (! $entry) {
                    continue;
                }

                $label = match ($entry->status) {
                    'SHIFT' => 'ON',
                    'BLACK' => '',
                    default => $entry->status,
                };
                $sheet->setCellValue($cell, $label);
                $sheet->getStyle($cell)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($statusColors[$entry->status] ?? 'FFFFFFFF');

                if ($entry->status === 'BLACK') {
                    $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
                }
            }

            $summaryStart = count($days) + 3;
            $values = [
                $summary['SHIFT'],
                $summary['OFF'],
                $summary['AL'],
                $summary['al_balance'],
                $summary['DP'],
                $summary['dp_balance'],
                $summary['SL'],
                $summary['PH'],
            ];

            foreach ($values as $offset => $value) {
                $column = Coordinate::stringFromColumnIndex($summaryStart + $offset);
                $sheet->setCellValue($column.$row, $value);
                $sheet->getStyle($column.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        $lastRow = max(1, $users->count() + 1);
        $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');
        $sheet->freezePane('C2');
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(18);

        for ($index = 3; $index <= count($headers); $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth($index <= count($days) + 2 ? 14 : 14);
        }

        return $spreadsheet;
    }
}
