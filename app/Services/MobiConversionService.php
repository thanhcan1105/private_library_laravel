<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class MobiConversionService
{
    public function convert(string $sourcePath, int $uploadId): ?string
    {
        $disk = Storage::disk('local');
        $conversionDirectory = 'tmp/conversions/'.$uploadId;
        $workingDirectory = $disk->path($conversionDirectory.'/calibre');
        $inputPath = $disk->path($conversionDirectory.'/source.mobi');
        $outputPath = $disk->path($conversionDirectory.'/converted.epub');
        $keepOutput = false;

        try {
            $disk->deleteDirectory($conversionDirectory);
            $disk->makeDirectory($conversionDirectory.'/calibre');

            if (! copy($sourcePath, $inputPath)) {
                return null;
            }

            $converter = trim((string) config('services.calibre.ebook_convert', ''));
            $candidates = array_filter([
                $converter,
                PHP_OS_FAMILY === 'Windows' ? (getenv('ProgramFiles') ?: 'C:\\Program Files').'\\Calibre2\\ebook-convert.exe' : null,
                'ebook-convert',
            ]);
            $executable = null;
            $finder = new ExecutableFinder;

            foreach ($candidates as $candidate) {
                if (str_contains($candidate, DIRECTORY_SEPARATOR)) {
                    if (is_file($candidate)) {
                        $executable = $candidate;
                        break;
                    }

                    continue;
                }

                $executable = $finder->find($candidate);
                if ($executable !== null) {
                    break;
                }
            }

            if ($executable === null) {
                return null;
            }

            $process = new Process([$executable, $inputPath, $outputPath], null, [
                'TEMP' => $workingDirectory,
                'TMP' => $workingDirectory,
                'TMPDIR' => $workingDirectory,
            ]);
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful() || ! $this->isValidEpub($outputPath)) {
                return null;
            }

            $outputSize = filesize($outputPath);
            if (! is_int($outputSize) || $outputSize < 1 || $outputSize > 50 * 1024 * 1024) {
                return null;
            }

            $keepOutput = true;

            return $outputPath;
        } catch (Throwable) {
            return null;
        } finally {
            if (is_file($inputPath)) {
                @unlink($inputPath);
            }

            if (! $keepOutput && is_file($outputPath)) {
                @unlink($outputPath);
            }

            if (! $keepOutput) {
                $disk->deleteDirectory($conversionDirectory);
            }
        }
    }

    public function isValidEpub(string $path): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            return false;
        }

        try {
            if ($zip->numFiles < 3 || $zip->numFiles > 10000) {
                return false;
            }

            $firstEntry = $zip->statIndex(0);
            if (($firstEntry['name'] ?? null) !== 'mimetype'
                || ($firstEntry['comp_method'] ?? null) !== ZipArchive::CM_STORE
                || $zip->getFromIndex(0) !== 'application/epub+zip') {
                return false;
            }

            $expandedSize = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if ($entry === false || ! isset($entry['size']) || ! is_int($entry['size'])) {
                    return false;
                }

                $expandedSize += $entry['size'];
                if ($expandedSize > 250 * 1024 * 1024) {
                    return false;
                }
            }

            $containerXml = $zip->getFromName('META-INF/container.xml');
            if (! is_string($containerXml) || strlen($containerXml) > 1024 * 1024) {
                return false;
            }

            $container = $this->loadSafeXml($containerXml);
            if (! $container) {
                return false;
            }

            $rootfile = $container->getElementsByTagNameNS('*', 'rootfile')->item(0);
            $packagePath = $rootfile instanceof \DOMElement
                ? str_replace('\\', '/', trim($rootfile->getAttribute('full-path')))
                : '';
            if ($packagePath === '' || str_starts_with($packagePath, '/') || preg_match('~(^|/)\.\.?(/|$)~', $packagePath)) {
                return false;
            }

            $packageXml = $zip->getFromName($packagePath);
            if (! is_string($packageXml) || strlen($packageXml) > 5 * 1024 * 1024) {
                return false;
            }

            $package = $this->loadSafeXml($packageXml);

            return $package?->documentElement?->localName === 'package';
        } catch (Throwable) {
            return false;
        } finally {
            $zip->close();
        }
    }

    private function loadSafeXml(string $xml): ?\DOMDocument
    {
        $previousErrorMode = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $isValid = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);

        return $isValid ? $document : null;
    }
}
