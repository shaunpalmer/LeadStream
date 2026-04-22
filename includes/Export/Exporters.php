<?php
namespace LS\Export;

interface ExporterInterface {
    public function output(array $rows): void;
    public function contentType(): string;
    public function filename(): string;
}

class CsvExporter implements ExporterInterface {
    private string $name;
    public function __construct(string $name='leadstream-export.csv') { $this->name = $name; }
    public function contentType(): string { return 'text/csv; charset=utf-8'; }
    public function filename(): string { return $this->name; }
    public function output(array $rows): void {
        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $r) { fputcsv($out, $r); }
        } else {
            fputcsv($out, ['message']); fputcsv($out, ['No data']);
        }
        fclose($out);
    }
}

class JsonExporter implements ExporterInterface {
    private string $name;
    public function __construct(string $name='leadstream-export.json') { $this->name = $name; }
    public function contentType(): string { return 'application/json; charset=utf-8'; }
    public function filename(): string { return $this->name; }
    public function output(array $rows): void { echo json_encode($rows); }
}
