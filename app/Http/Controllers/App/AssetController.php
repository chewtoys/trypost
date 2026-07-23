<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\Asset\StoreAssetFromUrlRequest;
use App\Http\Requests\App\Asset\StoreAssetRequest;
use App\Http\Requests\App\Asset\StoreChunkedAssetRequest;
use App\Http\Resources\App\MediaResource;
use App\Models\Media;
use App\Models\Workspace;
use App\Services\Brand\SafeHttpFetcher;
use App\Services\Media\ChunkedCloudUploader;
use App\Services\UnsplashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class AssetController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('createPost', $workspace);

        return Inertia::render('assets/Index');
    }

    public function search(Request $request): AnonymousResourceCollection
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $term = trim((string) $request->input('search', ''));
        $type = $request->input('type');

        $assets = $workspace->getMedia('assets')
            ->when($term !== '', fn ($query) => $query->where('original_filename', 'ilike', '%'.$term.'%'))
            ->when(in_array($type, ['image', 'video'], true), fn ($query) => $query->where('type', $type))
            ->latest()
            ->paginate(config('app.pagination.default'));

        return MediaResource::collection($assets);
    }

    public function store(StoreAssetRequest $request): MediaResource
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $clientMeta = (array) $request->input('meta', []);

        $media = $workspace->addMedia($request->file('media'), 'assets', $clientMeta);

        return new MediaResource($media);
    }

    public function storeChunked(StoreChunkedAssetRequest $request, ChunkedCloudUploader $cloudUploader): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $rangeStart = (int) $request->validated('range_start');
        $rangeEnd = (int) $request->validated('range_end');
        $totalSize = (int) $request->validated('total_size');
        $fileName = (string) $request->validated('file_name');
        $chunk = $request->getContent();
        $identifier = md5($request->user()->id.$fileName.$totalSize);

        if ($cloudUploader->shouldUseMultipart($fileName)) {
            return $this->storeChunkedViaMultipart(
                $cloudUploader,
                $workspace,
                $identifier,
                $fileName,
                $chunk,
                $rangeStart,
                $rangeEnd,
                $totalSize,
            );
        }

        return $this->storeChunkedViaLocalAssemble(
            $workspace,
            $identifier,
            $fileName,
            $chunk,
            $rangeStart,
            $rangeEnd,
            $totalSize,
        );
    }

    private function storeChunkedViaMultipart(
        ChunkedCloudUploader $cloudUploader,
        Workspace $workspace,
        string $identifier,
        string $fileName,
        string $chunk,
        int $rangeStart,
        int $rangeEnd,
        int $totalSize,
    ): JsonResponse {
        $result = $cloudUploader->receiveChunk(
            $identifier,
            $fileName,
            $chunk,
            $rangeStart,
            $rangeEnd,
            $totalSize,
        );

        if (! data_get($result, 'done')) {
            return response()->json([
                'done' => false,
                'progress' => data_get($result, 'progress'),
            ]);
        }

        $path = (string) data_get($result, 'path');

        try {
            $media = $workspace->addMediaFromStoredPath(
                $path,
                $fileName,
                (string) data_get($result, 'mime_type'),
                (int) data_get($result, 'size'),
                'assets',
            );
        } catch (Throwable $exception) {
            Storage::delete($path);

            throw $exception;
        }

        return $this->chunkedMediaResponse($media);
    }

    private function storeChunkedViaLocalAssemble(
        Workspace $workspace,
        string $identifier,
        string $fileName,
        string $chunk,
        int $rangeStart,
        int $rangeEnd,
        int $totalSize,
    ): JsonResponse {
        $tempFile = storage_path("app/private/chunks/{$identifier}");

        if (! is_dir(dirname($tempFile))) {
            mkdir(dirname($tempFile), 0755, true);
        }

        file_put_contents($tempFile, $chunk, $rangeStart === 0 ? 0 : FILE_APPEND);

        if (($rangeEnd + 1) < $totalSize) {
            return response()->json([
                'done' => false,
                'progress' => (int) round(($rangeEnd + 1) / $totalSize * 100),
            ]);
        }

        try {
            $media = $workspace->addMediaFromPath($tempFile, $fileName, 'assets');
        } finally {
            @unlink($tempFile);
        }

        return $this->chunkedMediaResponse($media);
    }

    private function chunkedMediaResponse(Media $media): JsonResponse
    {
        return response()->json([
            'done' => true,
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'type' => $media->type->value,
            'mime_type' => $media->mime_type,
            'original_filename' => $media->original_filename,
            'size' => $media->size,
            'meta' => $media->meta,
            'created_at' => $media->created_at->toISOString(),
        ]);
    }

    public function storeFromUrl(StoreAssetFromUrlRequest $request, UnsplashService $unsplash, SafeHttpFetcher $safeHttp): MediaResource
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $validated = $request->validated();

        // Trigger Unsplash download tracking (required by API guidelines)
        if ($downloadLocation = data_get($validated, 'download_location')) {
            $unsplash->trackDownload($downloadLocation);
        }

        $url = (string) data_get($validated, 'url');

        try {
            $response = $safeHttp->guardedRequest($url)->timeout(30)->get($url);
        } catch (RuntimeException) {
            abort(SymfonyResponse::HTTP_BAD_REQUEST, 'Failed to download image from URL');
        }

        if ($response->failed()) {
            abort(SymfonyResponse::HTTP_BAD_REQUEST, 'Failed to download image from URL');
        }

        $mimeType = $response->header('Content-Type', 'image/jpeg');
        $extension = match (true) {
            str_contains($mimeType, 'png') => 'png',
            str_contains($mimeType, 'gif') => 'gif',
            str_contains($mimeType, 'webp') => 'webp',
            default => 'jpg',
        };

        $filename = Str::uuid().'.'.$extension;
        $path = 'medias/'.$filename;

        Storage::put($path, $response->body());

        $meta = [];
        $tempFile = tempnam(sys_get_temp_dir(), 'unsplash');
        file_put_contents($tempFile, $response->body());
        $imageInfo = @getimagesize($tempFile);
        if ($imageInfo) {
            $meta['width'] = $imageInfo[0];
            $meta['height'] = $imageInfo[1];
        }
        @unlink($tempFile);

        $media = $workspace->media()->create([
            'group_id' => Str::uuid()->toString(),
            'collection' => 'assets',
            'type' => 'image',
            'path' => $path,
            'original_filename' => data_get($validated, 'filename'),
            'mime_type' => $mimeType,
            'size' => strlen($response->body()),
            'order' => 0,
            'meta' => $meta,
        ]);

        return new MediaResource($media);
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        if ($media->mediable_type !== $workspace->getMorphClass() || $media->mediable_id !== $workspace->id) {
            abort(SymfonyResponse::HTTP_FORBIDDEN);
        }

        $media->delete();

        return back();
    }
}
