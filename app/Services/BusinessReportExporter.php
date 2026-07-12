<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Génère le classeur Excel du rapport financier consolidé, avec graphes
 * natifs (barres revenu/établissement, camembert méthodes de paiement).
 * Les montants sont convertis en unité monétaire (FCFA) pour la lecture.
 */
class BusinessReportExporter
{
    private const HEADER_FILL = 'FF4F46E5';   // indigo
    private const HEADER_FONT = 'FFFFFFFF';

    public function excel(array $report): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getProperties()
            ->setCreator('MEKA ERP')
            ->setTitle('Rapport financier')
            ->setSubject('Rapport financier consolidé');

        $this->summarySheet($ss->getActiveSheet(), $report);
        $this->establishmentsSheet($ss->createSheet(), $report);
        $this->paymentsSheet($ss->createSheet(), $report);
        $this->expensesSheet($ss->createSheet(), $report);

        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    private function money(int $cents): float
    {
        return round($cents / 100, 0);
    }

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    // ── Feuille 1 : Résumé ────────────────────────────────────────────────────

    private function summarySheet($sheet, array $r): void
    {
        $sheet->setTitle('Résumé');
        $t = $r['totals'];
        $cur = $r['currency'];

        $sheet->setCellValue('A1', 'RAPPORT FINANCIER CONSOLIDÉ');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->setCellValue('A2', 'Période : ' . $r['period'] . ' · Généré le ' . $r['generated_at'] . ' · ' . $r['count'] . ' établissement(s)');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setARGB('FF64748B');

        $rows = [
            ['Indicateur', 'Montant (' . $cur . ')'],
            ['Revenu total', $this->money($t['revenue']['total'])],
            ['  · Hôtel', $this->money($t['revenue']['hotel'])],
            ['  · Restaurant', $this->money($t['revenue']['restaurant'])],
            ['  · Boutique', $this->money($t['revenue']['shop'])],
            ['Encaissé', $this->money($t['collected'])],
            ['Facturé', $this->money($t['invoiced'])],
            ['Payé (factures)', $this->money($t['paid'])],
            ['Créances (dû)', $this->money($t['due'])],
            ['Dépenses', $this->money($t['expenses'])],
            ['Résultat net', $this->money($t['net'])],
            ['Écart de caisse', $this->money($t['cash_discrepancy'])],
        ];

        $row = 4;
        foreach ($rows as $line) {
            $sheet->setCellValue("A{$row}", $line[0]);
            $sheet->setCellValue("B{$row}", $line[1]);
            if ($row === 4) {
                $this->styleHeader($sheet, "A{$row}:B{$row}");
            }
            $row++;
        }
        $sheet->getStyle('B5:B' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->getStyle('A' . ($row - 2))->getFont()->setBold(true); // Résultat net
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(20);
    }

    // ── Feuille 2 : Par établissement (+ graphe barres) ───────────────────────

    private function establishmentsSheet($sheet, array $r): void
    {
        $sheet->setTitle('Par établissement');
        $headers = ['Établissement', 'Revenu', 'Encaissé', 'Facturé', 'Payé', 'Dû', 'Dépenses', 'Net', 'Écart caisse'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 'A1:I1');

        $row = 2;
        foreach ($r['establishments'] as $e) {
            $sheet->fromArray([
                $e['name'],
                $this->money($e['revenue']), $this->money($e['collected']),
                $this->money($e['invoiced']), $this->money($e['paid']), $this->money($e['due']),
                $this->money($e['expenses']), $this->money($e['net']), $this->money($e['cash_gap']),
            ], null, "A{$row}");
            $row++;
        }
        $last = $row - 1;

        if ($last >= 2) {
            $sheet->getStyle("B2:I{$last}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("A1:I{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            foreach (range('A', 'I') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

            // Graphe barres : revenu par établissement
            $cats = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Par établissement'!\$A\$2:\$A\${$last}", null, $last - 1)];
            $vals = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Par établissement'!\$B\$2:\$B\${$last}", null, $last - 1)];
            $labs = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Par établissement'!\$B\$1", null, 1)];

            $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_STANDARD, range(0, count($vals) - 1), $labs, $cats, $vals);
            $series->setPlotDirection(DataSeries::DIRECTION_BAR);
            $chart = new Chart('revenus', new Title('Revenu par établissement'), new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea(null, [$series]));
            $chart->setTopLeftPosition('K2');
            $chart->setBottomRightPosition('R18');
            $sheet->addChart($chart);
        }
    }

    // ── Feuille 3 : Méthodes de paiement (+ camembert) ────────────────────────

    private function paymentsSheet($sheet, array $r): void
    {
        $sheet->setTitle('Méthodes de paiement');
        $sheet->fromArray(['Méthode', 'Nombre', 'Total'], null, 'A1');
        $this->styleHeader($sheet, 'A1:C1');

        $row = 2;
        foreach ($r['payment_methods'] as $m) {
            $sheet->fromArray([$m['method'], $m['count'], $this->money($m['total'])], null, "A{$row}");
            $row++;
        }
        $last = $row - 1;

        if ($last >= 2) {
            $sheet->getStyle("C2:C{$last}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("A1:C{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            foreach (range('A', 'C') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

            $cats = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Méthodes de paiement'!\$A\$2:\$A\${$last}", null, $last - 1)];
            $vals = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Méthodes de paiement'!\$C\$2:\$C\${$last}", null, $last - 1)];
            $labs = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Méthodes de paiement'!\$C\$1", null, 1)];

            $series = new DataSeries(DataSeries::TYPE_PIECHART, null, range(0, count($vals) - 1), $labs, $cats, $vals);
            $chart = new Chart('paiements', new Title('Répartition des encaissements'), new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea(null, [$series]));
            $chart->setTopLeftPosition('E2');
            $chart->setBottomRightPosition('L16');
            $sheet->addChart($chart);
        }
    }

    // ── Feuille 4 : Dépenses ──────────────────────────────────────────────────

    private function expensesSheet($sheet, array $r): void
    {
        $sheet->setTitle('Dépenses');
        $sheet->fromArray(['Établissement', 'Montant', 'Motif', 'Par', 'Caisse', 'Date'], null, 'A1');
        $this->styleHeader($sheet, 'A1:F1');

        $row = 2;
        foreach ($r['expenses'] as $e) {
            $sheet->fromArray([
                $e['establishment'] ?? '', $this->money((int) ($e['amount'] ?? 0)),
                $e['reason'] ?? '', $e['user'] ?? '', $e['module'] ?? '', $e['at'] ?? '',
            ], null, "A{$row}");
            $row++;
        }
        $last = max(2, $row - 1);
        $sheet->getStyle("B2:B{$last}")->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A', 'F') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
        if ($row > 2) {
            $sheet->getStyle("A1:F" . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
    }
}
