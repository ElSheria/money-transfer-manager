<?php

namespace App\Services;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use ZipArchive;

class TransferExportService
{
    public function queryFor(User $user, Request $request): Builder
    {
        return Transfer::query()
            ->with(['sender', 'receiver', 'sourceAgency', 'destinationAgency', 'manager'])
            ->when($user->isManager(), function (Builder $query) use ($user) {
                $query->where(function (Builder $query) use ($user) {
                    $query->where('source_agency_id', $user->agency_id)
                        ->orWhere('destination_agency_id', $user->agency_id);
                });
            })
            ->when($user->isSupervisor(), function (Builder $query) use ($user) {
                $agencyIds = $user->supervisedAgencies()->pluck('agencies.id');

                $query->where(function (Builder $query) use ($agencyIds) {
                    $query->whereIn('source_agency_id', $agencyIds)
                        ->orWhereIn('destination_agency_id', $agencyIds);
                });
            })
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('agency_id'), function (Builder $query) use ($request) {
                $query->where(function (Builder $query) use ($request) {
                    $query->where('source_agency_id', $request->integer('agency_id'))
                        ->orWhere('destination_agency_id', $request->integer('agency_id'));
                });
            })
            ->when($request->filled('currency'), fn (Builder $query) => $query->where('currency', $request->string('currency')->upper()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->latest();
    }

    public function rows(User $user, Request $request): array
    {
        return $this->queryFor($user, $request)
            ->get()
            ->map(fn (Transfer $transfer) => [
                'date' => $transfer->created_at->format('Y-m-d H:i'),
                'code' => $transfer->code,
                'source_agency' => $transfer->sourceAgency->name,
                'destination_agency' => $transfer->destinationAgency->name,
                'sender' => $transfer->sender->full_name,
                'receiver' => $transfer->receiver->full_name,
                'amount' => number_format((float) $transfer->amount, 2, '.', ''),
                'currency' => $transfer->currency,
                'fee' => number_format((float) $transfer->fee, 2, '.', ''),
                'status' => $transfer->status,
                'manager' => $transfer->manager->name,
            ])
            ->all();
    }

    public function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $this->headings());

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        rewind($handle);

        return stream_get_contents($handle);
    }

    public function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'transfers-xlsx-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($rows));
        $zip->close();

        $content = file_get_contents($path);
        unlink($path);

        return $content;
    }

    public function pdf(array $rows): string
    {
        $lines = [
            'ABT-LACOLOMBE',
            'Historique des transferts',
            'Genere le '.now()->format('Y-m-d H:i'),
            '',
        ];

        foreach ($rows as $row) {
            $lines[] = "{$row['date']} | {$row['code']}";
            $lines[] = "{$row['source_agency']} -> {$row['destination_agency']}";
            $lines[] = "{$row['sender']} -> {$row['receiver']}";
            $lines[] = "{$row['amount']} {$row['currency']} | Frais {$row['fee']} | {$row['status']}";
            $lines[] = "Gerant: {$row['manager']}";
            $lines[] = '';
        }

        if ($rows === []) {
            $lines[] = 'Aucun transfert trouve.';
        }

        return $this->buildSimplePdf($lines);
    }

    public function receipt(Transfer $transfer): string
    {
        $transfer->loadMissing(['sender', 'receiver', 'sourceAgency', 'destinationAgency', 'manager', 'withdrawnBy']);

        $lines = [
            'ABT-LACOLOMBE',
            'Recu de transfert',
            '',
            'Code: '.$transfer->code,
            'Date: '.$transfer->created_at->format('Y-m-d H:i'),
            'Statut: '.$transfer->status,
            '',
            'Agence depart: '.$transfer->sourceAgency->name,
            'Agence destination: '.$transfer->destinationAgency->name,
            '',
            'Expediteur: '.$transfer->sender->full_name,
            'Telephone expediteur: '.$transfer->sender->phone,
            'Beneficiaire: '.$transfer->receiver->full_name,
            'Telephone beneficiaire: '.$transfer->receiver->phone,
            '',
            'Montant: '.number_format((float) $transfer->amount, 2, '.', ' ').' '.$transfer->currency,
            'Frais: '.number_format((float) $transfer->fee, 2, '.', ' ').' '.$transfer->currency,
            'Total paye: '.number_format(((float) $transfer->amount + (float) $transfer->fee), 2, '.', ' ').' '.$transfer->currency,
            '',
            'Gerant emission: '.$transfer->manager->name,
            'Gerant retrait: '.($transfer->withdrawnBy?->name ?? 'Non retire'),
            '',
            'Votre partenaire de confiance.',
        ];

        return $this->buildSimplePdf($lines);
    }

    private function headings(): array
    {
        return [
            'Date',
            'Code transfert',
            'Agence depart',
            'Agence destination',
            'Expediteur',
            'Beneficiaire',
            'Montant',
            'Devise',
            'Frais',
            'Statut',
            'Gerant',
        ];
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Historique" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }

    private function worksheetXml(array $rows): string
    {
        $sheetRows = [$this->headings(), ...array_map(fn (array $row) => array_values($row), $rows)];

        $xmlRows = collect($sheetRows)
            ->map(function (array $row, int $index) {
                $rowNumber = $index + 1;
                $cells = collect($row)
                    ->map(fn ($value, int $cellIndex) => $this->cellXml($cellIndex + 1, $rowNumber, (string) $value))
                    ->join('');

                return "<row r=\"{$rowNumber}\">{$cells}</row>";
            })
            ->join('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$xmlRows.'</sheetData>'
            .'</worksheet>';
    }

    private function cellXml(int $columnNumber, int $rowNumber, string $value): string
    {
        $reference = $this->columnName($columnNumber).$rowNumber;
        $escaped = htmlspecialchars($value, ENT_XML1);

        return "<c r=\"{$reference}\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
    }

    private function columnName(int $columnNumber): string
    {
        $name = '';

        while ($columnNumber > 0) {
            $columnNumber--;
            $name = chr(65 + ($columnNumber % 26)).$name;
            $columnNumber = intdiv($columnNumber, 26);
        }

        return $name;
    }

    private function buildSimplePdf(array $lines): string
    {
        $pages = array_chunk($lines, 42);
        $objects = [];

        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids ['.$this->pageKids(count($pages)).'] /Count '.count($pages).' >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        foreach ($pages as $index => $pageLines) {
            $pageNumber = 4 + ($index * 2);
            $contentNumber = $pageNumber + 1;
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentNumber} 0 R >>";
            $objects[] = '<< /Length '.$this->contentLength($pageLines).' >>'."\nstream\n".$this->pageContent($pageLines).'endstream';
        }

        return $this->compilePdf($objects);
    }

    private function pageKids(int $pageCount): string
    {
        return collect(range(0, $pageCount - 1))
            ->map(fn (int $index) => (4 + ($index * 2)).' 0 R')
            ->join(' ');
    }

    private function contentLength(array $lines): int
    {
        return strlen($this->pageContent($lines));
    }

    private function pageContent(array $lines): string
    {
        $content = "BT\n/F1 10 Tf\n50 800 Td\n14 TL\n";

        foreach ($lines as $line) {
            $content .= '('.$this->escapePdfText($line).") Tj\nT*\n";
        }

        return $content."ET\n";
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function compilePdf(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
