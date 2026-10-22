<?php

namespace App\Http\Controllers;

use App\Models\Stream;
use App\Models\StudioAudioAsset;
use App\Services\GoogleDriveService;
use App\Services\StorageQuotaService;
use App\Support\HttpByteRange;
use App\Support\LibraryByteRangeResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioAudioLibraryController extends Controller
{
    public function index(Request $request, Stream $stream, StorageQuotaService $quota): JsonResponse
    {
        $this->authorizeStudio($request, $stream);

        $q = trim((string) $request->query('q', ''));
        $org = $stream->organization;

        $assets = $stream->studioAudioAssets()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('title', 'like', $like)
                        ->orWhere('original_filename', 'like', $like);
                });
            })
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (StudioAudioAsset $asset) => $asset->toLibraryPayload($stream));

        return response()->json([
            'assets' => $assets,
            'storage' => $org ? $quota->summary($org) : null,
            'drive' => [
                'connected' => $org?->driveConnection !== null,
                'email' => $org?->driveConnection?->google_email,
                'connect_url' => $org ? route('integrations.google-drive.redirect', $org) : null,
            ],
        ]);
    }

    public function store(
        Request $request,
        Stream $stream,
        StorageQuotaService $quota,
        GoogleDriveService $drive,
    ): JsonResponse {
        $this->authorizeStudio($request, $stream);
        $org = $stream->organization;
        abort_unless($org, 404);

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:51200'],
            'title' => ['nullable', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'destination' => ['nullable', 'in:local,platform,drive'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['audio'];
        $this->assertAudioFile($file);
        $original = $file->getClientOriginalName() ?: 'audio';
        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            $title = pathinfo($original, PATHINFO_FILENAME) ?: 'Untitled';
        }
        $title = Str::limit($title, 255, '');
        $destination = $validated['destination']
            ?? (config('object_storage.enabled') ? 'platform' : 'local');
        $size = (int) ($file->getSize() ?: 0);
        $mime = $file->getMimeType() ?: 'audio/mpeg';

        if ($destination === 'drive') {
            $connection = $org->driveConnection;
            if ($connection === null) {
                throw ValidationException::withMessages([
                    'destination' => 'Connect Google Drive first, or save to Server disk / Platform.',
                ]);
            }

            $uploaded = $drive->uploadAudio(
                $connection,
                $original,
                $file->get(),
                $mime,
            );

            $asset = StudioAudioAsset::query()->create([
                'organization_id' => $org->id,
                'stream_id' => $stream->id,
                'uploaded_by' => $request->user()?->id,
                'title' => $title,
                'original_filename' => Str::limit($original, 255, ''),
                'path' => 'drive:'.($uploaded['id'] ?? ''),
                'storage_provider' => StudioAudioAsset::PROVIDER_DRIVE,
                'external_id' => $uploaded['id'] ?? null,
                'mime_type' => $uploaded['mimeType'] ?? $mime,
                'size_bytes' => (int) ($uploaded['size'] ?? $size),
                'duration_seconds' => isset($validated['duration_seconds'])
                    ? (int) round((float) $validated['duration_seconds'])
                    : null,
            ]);

            return response()->json([
                'asset' => $asset->toLibraryPayload($stream),
                'stored_as' => StudioAudioAsset::PROVIDER_DRIVE,
            ], 201);
        }

        $quota->assertCanStore($org, $size);

        if ($destination === 'platform' && config('object_storage.enabled')) {
            $key = trim(config('object_storage.prefix').'/studio-audio/'.$stream->uuid.'/'.Str::uuid().'_'.Str::slug(pathinfo($original, PATHINFO_FILENAME)).'.'.($file->getClientOriginalExtension() ?: 'bin'), '/');
            Storage::disk((string) config('object_storage.disk', 's3'))->put($key, $file->get(), [
                'visibility' => 'private',
                'ContentType' => $mime,
            ]);
            $path = $key;
            $provider = StudioAudioAsset::PROVIDER_PLATFORM;
        } else {
            $path = $file->store('studio-audio/'.$stream->uuid, 'public');
            $provider = StudioAudioAsset::PROVIDER_LOCAL;
        }

        $asset = StudioAudioAsset::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'uploaded_by' => $request->user()?->id,
            'title' => $title,
            'original_filename' => Str::limit($original, 255, ''),
            'path' => $path,
            'storage_provider' => $provider,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'duration_seconds' => isset($validated['duration_seconds'])
                ? (int) round((float) $validated['duration_seconds'])
                : null,
        ]);

        $notice = null;
        if ($destination === 'platform' && $provider === StudioAudioAsset::PROVIDER_LOCAL) {
            $notice = 'Platform cloud storage is not enabled, so this file was saved on the server disk.';
        }

        return response()->json([
            'asset' => $asset->toLibraryPayload($stream),
            'stored_as' => $provider,
            'notice' => $notice,
        ], 201);
    }

    public function importDrive(
        Request $request,
        Stream $stream,
        GoogleDriveService $drive,
    ): JsonResponse {
        $this->authorizeStudio($request, $stream);
        $org = $stream->organization;
        abort_unless($org?->driveConnection, 422, 'Connect Google Drive first.');

        $validated = $request->validate([
            'file_id' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $meta = $drive->metadata($org->driveConnection, $validated['file_id']);
        $name = (string) ($meta['name'] ?? 'Drive audio');
        $title = trim((string) ($validated['title'] ?? '')) ?: pathinfo($name, PATHINFO_FILENAME) ?: $name;

        $asset = StudioAudioAsset::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'uploaded_by' => $request->user()?->id,
            'title' => Str::limit($title, 255, ''),
            'original_filename' => Str::limit($name, 255, ''),
            'path' => 'drive:'.$validated['file_id'],
            'storage_provider' => StudioAudioAsset::PROVIDER_DRIVE,
            'external_id' => $validated['file_id'],
            'mime_type' => $meta['mimeType'] ?? null,
            'size_bytes' => (int) ($meta['size'] ?? 0),
        ]);

        return response()->json(['asset' => $asset->toLibraryPayload($stream)], 201);
    }

    public function file(
        Request $request,
        Stream $stream,
        StudioAudioAsset $asset,
        GoogleDriveService $drive,
    ): StreamedResponse|Response {
        $this->authorizeStudio($request, $stream);
        abort_unless($asset->stream_id === $stream->id, 404);

        if ($asset->isDrive()) {
            return $this->driveFile($request, $stream, $asset, $drive);
        }

        if ($asset->storage_provider === StudioAudioAsset::PROVIDER_PLATFORM && config('object_storage.enabled')) {
            $disk = Storage::disk((string) config('object_storage.disk', 's3'));
            abort_unless($disk->exists($asset->path), 404);

            return $this->objectFile($request, $disk, $asset);
        }

        $disk = Storage::disk('public');
        abort_unless($disk->exists($asset->path), 404);
        $path = $disk->path($asset->path);
        $size = filesize($path);
        abort_unless($size !== false, 404);

        return LibraryByteRangeResponse::respond(
            $request,
            $size,
            $this->playbackHeaders($asset, 3600),
            static function (int $start, int $length) use ($path): void {
                LibraryByteRangeResponse::readLocal($path, $start, $length);
            },
            filemtime($path) ?: null,
        );
    }

    private function objectFile(Request $request, mixed $disk, StudioAudioAsset $asset): StreamedResponse|Response
    {
        $size = (int) $disk->size($asset->path);
        $mtime = $disk->lastModified($asset->path) ?: null;
        $headers = $this->playbackHeaders($asset, 3600);
        $asked = HttpByteRange::resolve($request->headers->get('Range'), $size);

        if (! method_exists($disk, 'getClient')) {
            if ($asked->outcome !== HttpByteRange::FULL) {
                return LibraryByteRangeResponse::unsatisfiable($size, $headers);
            }

            $resource = $disk->readStream($asset->path);
            abort_unless(is_resource($resource), 404);

            return LibraryByteRangeResponse::respond(
                $request,
                $size,
                $headers,
                static function (int $start, int $length) use ($resource): void {
                    LibraryByteRangeResponse::copyChunks($resource, $length, fread(...));
                    fclose($resource);
                },
                $mtime,
            );
        }

        $diskName = (string) config('object_storage.disk', 's3');
        $bucket = (string) config('filesystems.disks.'.$diskName.'.bucket');
        $key = $asset->path;

        return LibraryByteRangeResponse::respond(
            $request,
            $size,
            $headers,
            static function (int $start, int $length) use ($disk, $bucket, $key, $size): void {
                $params = [
                    'Bucket' => $bucket,
                    'Key' => $key,
                ];
                if ($length !== $size || $start !== 0) {
                    $params['Range'] = sprintf('bytes=%d-%d', $start, $start + max(0, $length) - 1);
                }
                $body = $disk->getClient()->getObject($params)['Body'];
                $left = $length;
                while ($left > 0 && ! $body->eof()) {
                    $chunk = $body->read(min(8192, $left));
                    if ($chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $left -= strlen($chunk);
                    if (connection_aborted()) {
                        break;
                    }
                }
                $body->close();
            },
            $mtime,
        );
    }

    private function driveFile(
        Request $request,
        Stream $stream,
        StudioAudioAsset $asset,
        GoogleDriveService $drive,
    ): StreamedResponse|Response {
        $connection = $stream->organization?->driveConnection;
        abort_unless($connection && $asset->external_id, 404);

        $size = (int) $asset->size_bytes;
        if ($size <= 0) {
            $size = (int) ($drive->metadata($connection, $asset->external_id)['size'] ?? 0);
        }

        $headers = $this->playbackHeaders($asset, 300);
        $mtime = $asset->updated_at?->getTimestamp();
        $asked = LibraryByteRangeResponse::resolveRequest($request, $size, $mtime);
        if ($asked->outcome === HttpByteRange::REJECT || $request->isMethod('HEAD')) {
            return LibraryByteRangeResponse::respond(
                $request,
                $size,
                $headers,
                static function (): void {},
                $mtime,
            );
        }

        $range = $asked->outcome === HttpByteRange::PARTIAL
            ? sprintf('bytes=%d-%d', $asked->start, $asked->end)
            : null;
        $remote = $drive->openMedia($connection, (string) $asset->external_id, $range);
        if ($range !== null && $remote->status() !== 206) {
            $remote->close();

            return LibraryByteRangeResponse::unsatisfiable($size, $headers);
        }
        if ($remote->status() !== 200 && $remote->status() !== 206) {
            $remote->close();
            abort(404);
        }

        return LibraryByteRangeResponse::respond(
            $request,
            $size,
            $headers,
            static function (int $start, int $length) use ($remote): void {
                $resource = $remote->resource();
                if (is_resource($resource)) {
                    LibraryByteRangeResponse::copyChunks($resource, $length, fread(...));
                }
                $remote->close();
            },
            $mtime,
        );
    }

    public function destroy(Request $request, Stream $stream, StudioAudioAsset $asset): JsonResponse
    {
        $this->authorizeStudio($request, $stream);
        abort_unless($asset->stream_id === $stream->id, 404);

        if ($asset->storage_provider === StudioAudioAsset::PROVIDER_PLATFORM && config('object_storage.enabled')) {
            try {
                Storage::disk((string) config('object_storage.disk', 's3'))->delete($asset->path);
            } catch (\Throwable) {
            }
        } elseif ($asset->storage_provider === StudioAudioAsset::PROVIDER_LOCAL) {
            Storage::disk('public')->delete($asset->path);
        }
        // Drive-linked assets: remove library row only; leave file in creator's Drive.

        $asset->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeStudio(Request $request, Stream $stream): void
    {
        $user = $request->user();
        $canManage = $user?->canManageOrganization($stream->organization)
            || $user?->canManageStream($stream);
        $signed = $request->hasValidSignature();

        abort_unless($canManage || $signed, 403);
    }

    /**
     * @param  \Illuminate\Http\UploadedFile  $file
     */
    private function assertAudioFile($file): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowed = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac', 'webm', 'mp4', 'mpeg', 'mpga'];
        $mime = (string) ($file->getMimeType() ?: '');
        $looksAudio = str_starts_with($mime, 'audio/')
            || in_array($extension, $allowed, true)
            || in_array($mime, ['video/mp4', 'video/webm', 'application/ogg'], true);

        if (! $looksAudio) {
            throw ValidationException::withMessages([
                'audio' => 'Use an audio file (mp3, wav, m4a, aac, ogg, or flac).',
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function playbackHeaders(StudioAudioAsset $asset, int $maxAge): array
    {
        $filename = str_replace(['"', "\r", "\n"], '', (string) $asset->original_filename);

        return [
            'Content-Type' => $asset->mime_type ?: 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age='.$maxAge,
            'Access-Control-Allow-Origin' => '*',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ];
    }
}
