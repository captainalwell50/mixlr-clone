<?php

namespace App\Services;

use App\Models\OrganizationDriveConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleDriveService
{
    public const ROOT_FOLDER_NAME = 'Live Mix Audio';

    public function http(OrganizationDriveConnection $connection): PendingRequest
    {
        $this->ensureFreshToken($connection);

        return Http::withToken((string) $connection->access_token)
            ->acceptJson()
            ->timeout(60);
    }

    public function ensureFreshToken(OrganizationDriveConnection $connection): void
    {
        if (! $connection->tokenExpired()) {
            return;
        }

        if (! filled($connection->refresh_token)) {
            throw new RuntimeException('Google Drive session expired. Reconnect Drive.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Could not refresh Google Drive token. Reconnect Drive.');
        }

        $data = $response->json();
        $connection->forceFill([
            'access_token' => $data['access_token'] ?? $connection->access_token,
            'token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3500)),
        ])->save();
    }

    public function ensureRootFolder(OrganizationDriveConnection $connection): string
    {
        if (filled($connection->root_folder_id)) {
            return (string) $connection->root_folder_id;
        }

        $existing = $this->http($connection)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => sprintf(
                "name = '%s' and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                self::ROOT_FOLDER_NAME
            ),
            'spaces' => 'drive',
            'fields' => 'files(id,name)',
            'pageSize' => 1,
        ]);

        if ($existing->successful()) {
            $id = data_get($existing->json(), 'files.0.id');
            if (is_string($id) && $id !== '') {
                $connection->forceFill(['root_folder_id' => $id])->save();

                return $id;
            }
        }

        $created = $this->http($connection)->asJson()->post('https://www.googleapis.com/drive/v3/files', [
            'name' => self::ROOT_FOLDER_NAME,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if (! $created->successful()) {
            throw new RuntimeException('Could not create Live Mix folder in Google Drive.');
        }

        $id = (string) $created->json('id');
        $connection->forceFill(['root_folder_id' => $id])->save();

        return $id;
    }

    /**
     * @return list<array{id:string,name:string,mimeType:?string,size:int,modifiedTime:?string}>
     */
    public function listAudioFiles(OrganizationDriveConnection $connection, ?string $query = null): array
    {
        $folderId = $this->ensureRootFolder($connection);
        $safeQuery = trim((string) $query);
        $q = sprintf("'%s' in parents and trashed = false", $folderId);
        if ($safeQuery !== '') {
            $escaped = str_replace("'", "\\'", $safeQuery);
            $q .= " and name contains '{$escaped}'";
        }

        $response = $this->http($connection)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => $q,
            'spaces' => 'drive',
            'fields' => 'files(id,name,mimeType,size,modifiedTime)',
            'pageSize' => 100,
            'orderBy' => 'modifiedTime desc',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Could not list Google Drive files.');
        }

        $files = [];
        foreach ($response->json('files') ?? [] as $file) {
            $mime = (string) ($file['mimeType'] ?? '');
            if (str_starts_with($mime, 'audio/') || Str::endsWith(Str::lower($file['name'] ?? ''), ['.mp3', '.wav', '.m4a', '.aac', '.ogg', '.flac', '.webm', '.mp4'])) {
                $files[] = [
                    'id' => (string) $file['id'],
                    'name' => (string) ($file['name'] ?? 'Untitled'),
                    'mimeType' => $mime !== '' ? $mime : null,
                    'size' => (int) ($file['size'] ?? 0),
                    'modifiedTime' => $file['modifiedTime'] ?? null,
                ];
            }
        }

        return $files;
    }

    public function uploadAudio(
        OrganizationDriveConnection $connection,
        string $filename,
        string $contents,
        string $mimeType,
    ): array {
        $folderId = $this->ensureRootFolder($connection);
        $this->ensureFreshToken($connection);

        $metadata = json_encode([
            'name' => $filename,
            'parents' => [$folderId],
        ], JSON_THROW_ON_ERROR);

        $boundary = 'livemix_'.bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            ."{$metadata}\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: {$mimeType}\r\n\r\n"
            .$contents."\r\n"
            ."--{$boundary}--";

        $response = Http::withToken((string) $connection->access_token)
            ->withBody($body, 'multipart/related; boundary='.$boundary)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,mimeType,size');

        if (! $response->successful()) {
            throw new RuntimeException('Google Drive upload failed.');
        }

        return $response->json();
    }

    public function download(OrganizationDriveConnection $connection, string $fileId): string
    {
        $response = $this->http($connection)
            ->withOptions(['stream' => false])
            ->get('https://www.googleapis.com/drive/v3/files/'.$fileId, [
                'alt' => 'media',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Could not download file from Google Drive.');
        }

        return $response->body();
    }

    public function metadata(OrganizationDriveConnection $connection, string $fileId): array
    {
        $response = $this->http($connection)->get('https://www.googleapis.com/drive/v3/files/'.$fileId, [
            'fields' => 'id,name,mimeType,size',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Drive file not found.');
        }

        return $response->json();
    }
}
