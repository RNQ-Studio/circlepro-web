<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use ZipArchive;

class RegionsDownloadCommand extends Command
{
    protected $signature = 'regions:download {--force : Re-download files that already exist}';

    protected $description = 'Download region source data from dr5hn and emsifa';

    private const DR5HN_RAW_BASE = 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json';

    private const DR5HN_CITIES_GZ = 'https://github.com/dr5hn/countries-states-cities-database/releases/latest/download/json-cities.json.gz';

    private const EMSIFA_ZIP = 'https://github.com/emsifa/api-wilayah-indonesia/archive/refs/heads/master.zip';

    private const EMSIFA_PREFIX = 'api-wilayah-indonesia-master/static/api/';

    public function handle(): int
    {
        $dr5hnReady = $this->downloadDr5hn();
        $emsifaReady = $this->downloadEmsifa();

        if (! $dr5hnReady || ! $emsifaReady) {
            $this->error('Some region source files failed to download.');

            return self::FAILURE;
        }

        $this->info('All region data ready in storage/app/regions/.');

        return self::SUCCESS;
    }

    private function downloadDr5hn(): bool
    {
        $this->info('[dr5hn] Downloading countries / states / cities...');
        $ready = true;

        foreach (['countries.json', 'states.json'] as $file) {
            $dest = "regions/dr5hn/{$file}";

            if (! $this->option('force') && file_exists(storage_path('app/'.$dest))) {
                $this->line("  skip  {$dest}");

                continue;
            }

            $this->line("  fetch  {$dest}");
            $url = self::DR5HN_RAW_BASE.'/'.$file;
            $response = $this->getUrl($url, 120);

            if (! $response->successful()) {
                $this->error("  Failed (HTTP {$response->status()}): {$url}");
                $ready = false;

                continue;
            }

            $this->putRegionFile($dest, $response->body());
        }

        $citiesDest = 'regions/dr5hn/cities.json';

        if (! $this->option('force') && file_exists(storage_path('app/'.$citiesDest))) {
            $this->line("  skip  {$citiesDest}");

            return $ready;
        }

        $citiesGzPath = storage_path('app/regions/dr5hn/cities.json.gz');
        $citiesJsonPath = storage_path('app/'.$citiesDest);

        $this->line("  fetch  {$citiesDest} (gzip release)");
        $response = $this->downloadToFile(self::DR5HN_CITIES_GZ, $citiesGzPath, 300);

        if (! $response->successful()) {
            $this->error("  Failed (HTTP {$response->status()}): ".self::DR5HN_CITIES_GZ);

            return false;
        }

        if (! $this->decompressGzipFile($citiesGzPath, $citiesJsonPath)) {
            $this->error('  Failed to decompress dr5hn cities gzip.');

            return false;
        }

        return $ready;
    }

    private function downloadEmsifa(): bool
    {
        $this->info('[emsifa] Checking extracted files...');

        $allPresent = ! $this->option('force')
            && file_exists(storage_path('app/regions/emsifa/provinces.json'))
            && file_exists(storage_path('app/regions/emsifa/regencies.json'))
            && file_exists(storage_path('app/regions/emsifa/districts.json'))
            && file_exists(storage_path('app/regions/emsifa/villages.json'));

        if ($allPresent) {
            $this->line('  skip  (all emsifa files already present)');

            return true;
        }

        $zipPath = storage_path('app/regions/emsifa-archive.zip');

        if ($this->option('force') || ! file_exists($zipPath)) {
            $this->line('  Downloading emsifa archive (~60 MB), please wait...');
            File::ensureDirectoryExists(dirname($zipPath));
            $response = $this->downloadToFile(self::EMSIFA_ZIP, $zipPath, 300);

            if (! $response->successful()) {
                $this->error('  Failed to download emsifa archive (HTTP '.$response->status().')');

                return false;
            }
        } else {
            $this->line('  Using cached emsifa-archive.zip');
        }

        $this->line('  Extracting and merging JSON files from ZIP...');

        return $this->extractEmsifaZip($zipPath);
    }

    private function extractEmsifaZip(string $zipPath): bool
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('  ZipArchive extension is not available. Enable ext-zip in php.ini.');

            return false;
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            $this->error('  Cannot open ZIP: '.$zipPath);

            return false;
        }

        $numFiles = $zip->numFiles;
        $this->line("  ZIP contains {$numFiles} entries - extracting relevant files...");

        $provinces = [];
        $regencies = [];
        $districts = [];
        $villages = [];

        $bar = $this->output->createProgressBar($numFiles);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
        $bar->start();

        for ($i = 0; $i < $numFiles; $i++) {
            $bar->advance();

            $name = $zip->getNameIndex($i);

            if (! str_starts_with($name, self::EMSIFA_PREFIX) || str_ends_with($name, '/')) {
                continue;
            }

            $relative = substr($name, strlen(self::EMSIFA_PREFIX));
            $data = json_decode($zip->getFromIndex($i), true);

            if (! is_array($data)) {
                continue;
            }

            if ($relative === 'provinces.json') {
                $provinces = $data;
            } elseif (str_starts_with($relative, 'regencies/')) {
                array_push($regencies, ...$data);
            } elseif (str_starts_with($relative, 'districts/')) {
                array_push($districts, ...$data);
            } elseif (str_starts_with($relative, 'villages/')) {
                array_push($villages, ...$data);
            }
        }

        $bar->finish();
        $this->newLine();

        $zip->close();

        $this->line(sprintf(
            '  Extracted: %d provinces, %d regencies, %d districts, %d villages',
            count($provinces), count($regencies), count($districts), count($villages)
        ));

        $this->putRegionFile('regions/emsifa/provinces.json', json_encode($provinces));
        $this->putRegionFile('regions/emsifa/regencies.json', json_encode($regencies));
        $this->putRegionFile('regions/emsifa/districts.json', json_encode($districts));
        $this->putRegionFile('regions/emsifa/villages.json', json_encode($villages));

        $this->line('  Saved merged files to storage/app/regions/emsifa/');

        return true;
    }

    private function getUrl(string $url, int $timeout): Response
    {
        try {
            return $this->http($timeout)->get($url);
        } catch (ConnectionException $exception) {
            return $this->retryWithoutTlsVerificationIfLocalCaIsMissing($exception, $timeout)->get($url);
        }
    }

    private function downloadToFile(string $url, string $path, int $timeout): Response
    {
        try {
            return $this->http($timeout)->sink($path)->get($url);
        } catch (ConnectionException $exception) {
            return $this->retryWithoutTlsVerificationIfLocalCaIsMissing($exception, $timeout)->sink($path)->get($url);
        }
    }

    private function retryWithoutTlsVerificationIfLocalCaIsMissing(ConnectionException $exception, int $timeout): PendingRequest
    {
        if (! str_contains($exception->getMessage(), 'cURL error 60')) {
            throw $exception;
        }

        $this->warn('  PHP CA bundle is not configured; retrying this download without TLS verification.');

        return $this->http($timeout)->withoutVerifying();
    }

    private function http(int $timeout): PendingRequest
    {
        return Http::timeout($timeout);
    }

    private function putRegionFile(string $relativePath, string $contents): void
    {
        $path = storage_path('app/'.$relativePath);

        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, $contents);
    }

    private function decompressGzipFile(string $source, string $destination): bool
    {
        File::ensureDirectoryExists(dirname($destination));

        $input = gzopen($source, 'rb');
        $output = fopen($destination, 'wb');

        if ($input === false || $output === false) {
            if (is_resource($input)) {
                gzclose($input);
            }

            if (is_resource($output)) {
                fclose($output);
            }

            return false;
        }

        while (! gzeof($input)) {
            fwrite($output, gzread($input, 1024 * 1024));
        }

        gzclose($input);
        fclose($output);

        return true;
    }
}
