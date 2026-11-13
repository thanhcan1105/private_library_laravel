<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class MobiConversionService
{
    public function convert(string $sourcePath): ?string
    {
        $temporaryBase = tempnam(sys_get_temp_dir(), 'private_library_mobi_');
        if ($temporaryBase === false) {
            return null;
        }

        @unlink($temporaryBase);
        $inputPath = $temporaryBase.'.mobi';
        $outputPath = $temporaryBase.'.epub';
        $keepOutput = false;
        $disk = Storage::disk('local');
        $conversionDirectory = 'tmp/conversions/'.Str::uuid();

        try {
            $disk->makeDirectory($conversionDirectory);
            $workingDirectory = $disk->path($conversionDirectory);

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

            $disk->deleteDirectory($conversionDirectory);
        }
    }

    public function isValidEpub(string $path): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return false;
        }

        $mime = $zip->getFromName('mimetype');
        $zip->close();

        return $mime !== false && trim($mime) === 'application/epub+zip';
    }
}
