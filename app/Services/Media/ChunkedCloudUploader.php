<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\Media\Type as MediaType;
use Aws\S3\S3Client;
use finfo;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Fast path for large uploads on object-storage disks (S3 / R2 / Spaces).
 * Local and public disks keep the normal assemble-then-store flow — both work.
 */
class ChunkedCloudUploader
{
    private const CACHE_PREFIX = 'chunked-cloud-upload:';

    private const CACHE_TTL_HOURS = 6;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ?S3Client $client = null,
        private readonly ?string $bucket = null,
        private readonly ?string $disk = null,
    ) {}

    /**
     * Use S3 multipart when the default disk is object storage and the file is
     * a video/PDF. Images still assemble locally (format normalization). Local
     * and public disks always return false and use the regular chunk path.
     */
    public function shouldUseMultipart(string $fileName, ?string $disk = null): bool
    {
        if (! $this->isObjectStorageDisk($disk)) {
            return false;
        }

        $type = MediaType::fromExtension(pathinfo($fileName, PATHINFO_EXTENSION));

        return in_array($type, [MediaType::Video, MediaType::Document], true);
    }

    public function isObjectStorageDisk(?string $disk = null): bool
    {
        $disk ??= $this->diskName();

        return config("filesystems.disks.{$disk}.driver") === 's3';
    }

    /**
     * @return array{done: bool, progress: int, path?: string, size?: int, mime_type?: string}
     */
    public function receiveChunk(
        string $identifier,
        string $fileName,
        string $chunk,
        int $rangeStart,
        int $rangeEnd,
        int $totalSize,
    ): array {
        $cacheKey = self::CACHE_PREFIX.$identifier;
        $client = $this->s3();
        $bucket = $this->bucket();

        if ($rangeStart === 0) {
            $this->abortIfPresent($cacheKey);
            $state = $this->startUpload($client, $bucket, $fileName, $chunk);
        } else {
            $state = $this->cache->get($cacheKey);

            if (! is_array($state)) {
                throw new RuntimeException('Chunked cloud upload session expired or missing.');
            }
        }

        $partNumber = count(data_get($state, 'parts', [])) + 1;

        $result = $client->uploadPart([
            'Bucket' => $bucket,
            'Key' => data_get($state, 'key'),
            'UploadId' => data_get($state, 'upload_id'),
            'PartNumber' => $partNumber,
            'Body' => $chunk,
        ]);

        $state['parts'][] = [
            'ETag' => data_get($result, 'ETag'),
            'PartNumber' => $partNumber,
        ];

        $isLastChunk = ($rangeEnd + 1) >= $totalSize;

        if (! $isLastChunk) {
            $this->cache->put($cacheKey, $state, now()->addHours(self::CACHE_TTL_HOURS));

            return [
                'done' => false,
                'progress' => (int) round(($rangeEnd + 1) / $totalSize * 100),
            ];
        }

        $client->completeMultipartUpload([
            'Bucket' => $bucket,
            'Key' => data_get($state, 'key'),
            'UploadId' => data_get($state, 'upload_id'),
            'MultipartUpload' => [
                'Parts' => data_get($state, 'parts', []),
            ],
        ]);

        $this->cache->forget($cacheKey);

        return [
            'done' => true,
            'progress' => 100,
            'path' => (string) data_get($state, 'key'),
            'size' => $totalSize,
            'mime_type' => (string) data_get($state, 'mime_type'),
        ];
    }

    /**
     * @return array{upload_id: string, key: string, mime_type: string, parts: array<int, array{ETag: string, PartNumber: int}>}
     */
    private function startUpload(S3Client $client, string $bucket, string $fileName, string $firstChunk): array
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $key = 'medias/'.Str::uuid().'.'.$extension;
        $mimeType = $this->detectMimeType($firstChunk, $extension);

        $created = $client->createMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $mimeType,
        ]);

        return [
            'upload_id' => (string) data_get($created, 'UploadId'),
            'key' => $key,
            'mime_type' => $mimeType,
            'parts' => [],
        ];
    }

    private function abortIfPresent(string $cacheKey): void
    {
        $existing = $this->cache->get($cacheKey);

        if (! is_array($existing)) {
            return;
        }

        try {
            $this->s3()->abortMultipartUpload([
                'Bucket' => $this->bucket(),
                'Key' => data_get($existing, 'key'),
                'UploadId' => data_get($existing, 'upload_id'),
            ]);
        } catch (Throwable) {
            // Best-effort cleanup of a previous incomplete upload.
        }

        $this->cache->forget($cacheKey);
    }

    private function detectMimeType(string $chunk, string $extension): string
    {
        $detected = (new finfo(FILEINFO_MIME_TYPE))->buffer($chunk) ?: null;

        if (is_string($detected) && $detected !== 'application/octet-stream') {
            return $detected;
        }

        return MediaType::fromExtension($extension)?->allowedMimeTypes()[0]
            ?? 'application/octet-stream';
    }

    private function s3(): S3Client
    {
        if ($this->client instanceof S3Client) {
            return $this->client;
        }

        $adapter = Storage::disk($this->diskName());

        if (! $adapter instanceof AwsS3V3Adapter) {
            throw new RuntimeException('Chunked cloud uploads require an S3-compatible disk.');
        }

        return $adapter->getClient();
    }

    private function bucket(): string
    {
        if (filled($this->bucket)) {
            return $this->bucket;
        }

        return (string) config("filesystems.disks.{$this->diskName()}.bucket");
    }

    private function diskName(): string
    {
        return $this->disk ?? (string) config('filesystems.default');
    }
}
