<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Media\ChunkedCloudUploader;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Cache::flush();
});

test('chunked cloud uploader only uses multipart on object storage for video and pdf', function () {
    $local = new ChunkedCloudUploader(Cache::store(), disk: 'local');
    expect($local->shouldUseMultipart('clip.mp4'))->toBeFalse();
    expect($local->shouldUseMultipart('photo.png'))->toBeFalse();

    config(['filesystems.disks.r2.driver' => 's3']);
    $r2 = new ChunkedCloudUploader(Cache::store(), disk: 'r2');
    expect($r2->shouldUseMultipart('clip.mp4'))->toBeTrue();
    expect($r2->shouldUseMultipart('deck.pdf'))->toBeTrue();
    expect($r2->shouldUseMultipart('photo.png'))->toBeFalse();
});

test('chunked cloud uploader uploads parts and completes multipart', function () {
    $client = Mockery::mock(S3Client::class);

    $client->shouldReceive('createMultipartUpload')
        ->once()
        ->andReturn(new Result(['UploadId' => 'upload-1']));

    $client->shouldReceive('uploadPart')
        ->twice()
        ->andReturn(new Result(['ETag' => '"etag-a"']), new Result(['ETag' => '"etag-b"']));

    $client->shouldReceive('completeMultipartUpload')
        ->once()
        ->withArgs(function (array $args) {
            expect(data_get($args, 'UploadId'))->toBe('upload-1');
            expect(data_get($args, 'MultipartUpload.Parts'))->toHaveCount(2);

            return true;
        })
        ->andReturn(new Result([]));

    $uploader = new ChunkedCloudUploader(Cache::store(), $client, 'test-bucket', 'r2');
    $chunk1 = str_repeat('a', 100);
    $chunk2 = str_repeat('b', 50);
    $total = strlen($chunk1) + strlen($chunk2);

    $mid = $uploader->receiveChunk('id-1', 'video.mp4', $chunk1, 0, strlen($chunk1) - 1, $total);
    expect($mid)->toMatchArray(['done' => false]);

    $done = $uploader->receiveChunk(
        'id-1',
        'video.mp4',
        $chunk2,
        strlen($chunk1),
        $total - 1,
        $total,
    );

    expect($done['done'])->toBeTrue();
    expect($done['size'])->toBe($total);
    expect($done['path'])->toStartWith('medias/');
    expect($done['path'])->toEndWith('.mp4');
    expect(Cache::get('chunked-cloud-upload:id-1'))->toBeNull();
});

test('chunked asset upload uses cloud multipart for videos when disk is s3', function () {
    $this->account = Account::factory()->create();
    $this->user = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->user->id]);
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $fake = Mockery::mock(ChunkedCloudUploader::class);
    $fake->shouldReceive('shouldUseMultipart')->with('clip.mp4')->andReturn(true);
    $fake->shouldReceive('receiveChunk')
        ->once()
        ->andReturn([
            'done' => true,
            'progress' => 100,
            'path' => 'medias/cloud-clip.mp4',
            'size' => 12,
            'mime_type' => 'video/mp4',
        ]);
    $this->app->instance(ChunkedCloudUploader::class, $fake);

    $content = 'fake-video!!';
    $size = strlen($content);

    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.assets.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-'.($size - 1).'/'.$size,
            'HTTP_X_FILE_NAME' => rawurlencode('clip.mp4'),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $content,
    );

    $response->assertSuccessful();
    $response->assertJson([
        'done' => true,
        'path' => 'medias/cloud-clip.mp4',
        'type' => 'video',
    ]);
    expect($this->workspace->getMedia('assets')->first()->path)->toBe('medias/cloud-clip.mp4');
});
