<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PhotoStorageService
{
    private string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'local');
    }

    public function storeResultPhoto(
        UploadedFile $photo,
        int $electionId,
        string $stationCode,
        string $submissionUuid
    ): array {
        $photoHash = hash_file('sha256', $photo->getRealPath());
        $directory = "elections/{$electionId}/results/{$stationCode}";
        $extension = $photo->getClientOriginalExtension();
        $filename = "{$submissionUuid}_" . now()->timestamp . ".{$extension}";

        $path = Storage::disk($this->disk)->putFileAs(
            $directory,
            $photo,
            $filename,
            'private'
        );

        return [
            'path' => $path,
            'hash' => $photoHash,
            'size' => $photo->getSize(),
            'mime' => $photo->getMimeType(),
        ];
    }

    public function getPhotoUrl(string $path, int $expirationMinutes = 60): string
    {
        // For local disk, generate URL using storage route
        if ($this->disk === 'local') {
            return url('storage/' . $path);
        }

        // For S3/MinIO, use temporaryUrl
        return Storage::disk($this->disk)->temporaryUrl(
            $path,
            now()->addMinutes($expirationMinutes)
        );
    }

    public function verifyPhotoIntegrity(string $path, string $expectedHash): bool
    {
        if (!Storage::disk($this->disk)->exists($path)) {
            return false;
        }

        $filePath = Storage::disk($this->disk)->path($path);
        $actualHash = hash_file('sha256', $filePath);

        return hash_equals($expectedHash, $actualHash);
    }

    public function deletePhoto(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }
}
