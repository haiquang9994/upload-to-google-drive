<?php

namespace App\Lib\Google\Drive;

use Exception;
use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;

class DefaultClient
{
    /**
     * @var string
     */
    protected $credentials_file;

    protected $client;

    protected $service_drive;

    protected $root_name;

    protected $root_id;

    protected $root;

    protected $google_drive;

    protected $token_path;

    public function __construct($root_name = "BACKUP", string $credentials_file, string $token_path)
    {
        $this->credentials_file = $credentials_file;
        $this->token_path = $token_path;
        $this->root_name = $root_name;//"BACKUP_VIDEOS";
        $this->initRoot();
    }

    private function getToken()
    {
        if (is_readable($this->token_path)) {
            $content = @file_get_contents($this->token_path);
            $token = @json_decode($content, true);
            return is_array($token) ? $token : null;
        }
        return null;
    }

    private function saveToken($token)
    {
        if (is_array($token)) {
            $token = json_encode($token);
        }
        file_put_contents($this->token_path, $token);
    }

    protected function initRoot()
    {
        $rootFolder = $this->findFolder($this->root_name, 'root');
        if (!$rootFolder) {
            $rootFolder = $this->createFolder($this->root_name, 'root');
        }
        $this->root = $rootFolder;
        $this->root_id = $rootFolder->id;
    }

    public function getRootId()
    {
        return $this->root_id;
    }

    protected function getClient(): Client
    {
        if (!$this->client) {
            $client = new Client();
            $client->setApplicationName('Google Drive API Phuong Nam Education');
            $client->setScopes(Drive::DRIVE);
            $client->setAuthConfig($this->credentials_file);
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');

            $token = $this->getToken();
            if (is_array($token)) {
                $client->setAccessToken($token);
            }

            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    $this->saveToken($client->getAccessToken());
                } else {
                    throw new Exception('Please login Google Drive with command line!');
                }
            }
            $this->client = $client;
        }

        return $this->client;
    }

    protected function getServiceDrive(): Drive
    {
        if (!$this->service_drive) {
            $this->service_drive = new Drive($this->getClient());
        }
        return $this->service_drive;
    }

    /**
     * @return DriveFile[]
     */
    public function getFiles(string $parentId, int $pageSize = 10): array
    {
        $optParams = array(
            'pageSize' => $pageSize,
            'fields' => 'nextPageToken, files(id, name, mimeType, webViewLink, permissionIds, webContentLink)',
            'q' => "'$parentId' in parents and trashed = false"
        );
        $results = $this->getServiceDrive()->files->listFiles($optParams);
        return $results->files;
    }

    public function findFolder(string $folderName, string $parentId = null): ?DriveFile
    {
        if ($parentId === null) {
            $parentId = $this->getRootId();
        }
        $optParams = array(
            'pageSize' => 10,
            'fields' => 'nextPageToken, files',
            'q' => "'$parentId' in parents and name = '" . $folderName . "' and mimeType = 'application/vnd.google-apps.folder' and trashed = false"
        );
        $results = $this->getServiceDrive()->files->listFiles($optParams);
        return isset($results[0]) ? $results[0] : null;
    }

    public function createFolder(string $folderName, string $parentId = null): DriveFile
    {
        if ($parentId === null) {
            $parentId = $this->getRootId();
        }
        $file = new DriveFile();
        $file->setName($folderName);
        $file->setMimeType('application/vnd.google-apps.folder');
        $file->setParents([$parentId]);
        $folder = $this->getServiceDrive()->files->create($file);
        return $folder;
    }

    public function findOrCreateFolder(string $folderName, string $parentId = null): DriveFile
    {
        if ($parentId === null) {
            $parentId = $this->getRootId();
        }
        $folder = $this->findFolder($folderName, $parentId);
        if (!$folder instanceof DriveFile) {
            $folder = $this->createFolder($folderName, $parentId);
        }
        return $folder;
    }

    public function uploadFile(string $path, string $name, string $mimeType, string $parentId = null)
    {
        if ($parentId === null) {
            $parentId = $this->getRootId();
        }
        $newPermission = new Permission();
        $newPermission->setType('anyone');
        $newPermission->setRole('reader');
        $client = $this->getClient();
        $file = new DriveFile();
        $fileName = pathinfo($name, PATHINFO_FILENAME) . '.' . pathinfo($name, PATHINFO_EXTENSION);
        $file->setName($fileName);
        $file->setMimeType($mimeType);
        $file->setParents([$parentId]);
        $file->setViewersCanCopyContent(false);
        $file->setWritersCanShare(false);
        $client->setDefer(true);
        /**
         * @var \Psr\Http\Message\RequestInterface
         */
        $request = $this->getServiceDrive()->files->create($file, ['uploadType' => 'resumable']);
        $chunkSizeBytes = 1 * 1024 * 1024;
        $media = new MediaFileUpload($client, $request, $mimeType, null, true, $chunkSizeBytes);
        $media->setFileSize(filesize($path));

        $handle = fopen($path, "rb");
        $createdFile = false;
        while (!$createdFile && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $createdFile = $media->nextChunk($chunk);
        }
        $client->setDefer(false);
        if ($createdFile instanceof DriveFile) {
            $this->getServiceDrive()->permissions->create($createdFile->id, $newPermission);
        }
    }

    public function updateFile($fileId, array $data): bool
    {
        $needUpdate = false;
        $file = new DriveFile();
        if (array_key_exists('viewersCanCopyContent', $data)) {
            $file->setViewersCanCopyContent($data['viewersCanCopyContent']);
            $needUpdate = true;
        }
        if (array_key_exists('writersCanShare', $data)) {
            $file->setWritersCanShare($data['writersCanShare']);
            $needUpdate = true;
        }
        try {
            if ($needUpdate) {
                $file = $this->getServiceDrive()->files->update($fileId, $file);
                return true;
            }
        } catch (Exception $e) {
        }
        return false;
    }

    public function deleteFiles(array $ids)
    {
        foreach ($ids as $id) {
            $this->getServiceDrive()->files->delete($id);
        }
    }
}
