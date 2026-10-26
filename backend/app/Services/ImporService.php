<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\ApiRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImporService
{
    public function __construct(private KatalogService $catalog) {}

    public function import(string $resource, UploadedFile $file, User $actor): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (! $header) {
                throw ValidationException::withMessages(['file' => ['CSV kosong.']]);
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $class = KatalogService::MODELS[$resource];
            $allowed = (new $class)->getFillable();
            if (count($header) !== count(array_unique($header)) || array_diff($header, $allowed)) {
                throw ValidationException::withMessages(['file' => ['Header CSV duplikat atau tidak dikenal.']]);
            }
            $result = ['berhasil' => 0, 'gagal' => 0, 'masalah' => []];
            $line = 1;
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($row === [null]) {
                    continue;
                }
                if ($line > 10001) {
                    $result['masalah'][] = ['baris' => $line, 'pesan' => 'Batas 10000 baris tercapai.'];
                    $result['gagal']++;
                    break;
                }
                if (count($row) !== count($header)) {
                    $result['gagal']++;
                    $result['masalah'][] = ['baris' => $line, 'pesan' => 'Jumlah kolom tidak sesuai header.'];

                    continue;
                }
                $data = array_combine($header, $row);
                foreach ($data as $field => $value) {
                    if ($field === 'parent_id' && $value === '') {
                        $data[$field] = null;
                    }
                }
                $validator = Validator::make($data, $this->catalog->rules($resource), (new ApiRequest)->messages());
                if ($validator->fails()) {
                    $result['gagal']++;
                    $result['masalah'][] = ['baris' => $line, 'errors' => $validator->errors()->toArray()];

                    continue;
                }
                try {
                    $this->catalog->mutate($resource, null, $validator->validated(), 'create', $actor);
                    $result['berhasil']++;
                } catch (BusinessRuleException $e) {
                    $result['gagal']++;
                    $result['masalah'][] = ['baris' => $line, 'pesan' => json_decode($e->getResponse()->getContent(), true)['message']];
                }
            }

            return $result;
        } finally {
            fclose($handle);
        }
    }

    public function export(string $resource, array $input): StreamedResponse
    {
        $query = $this->catalog->query($resource, $input);
        $class = KatalogService::MODELS[$resource];
        $fields = (new $class)->getFillable();

        return response()->streamDownload(function () use ($query, $fields): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, $fields, ',', '"', '');
            foreach ($query->cursor() as $row) {
                $values = [];
                foreach ($fields as $field) {
                    $value = (string) ($row->$field ?? '');
                    if (preg_match('/^[=+@\-\t\r\n]/', $value)) {
                        $value = "'".$value;
                    } $values[] = $value;
                }
                fputcsv($stream, $values, ',', '"', '');
            }
            fclose($stream);
        }, $resource.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
