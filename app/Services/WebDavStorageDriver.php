<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Env;
use PDO;
use RuntimeException;
use SimpleXMLElement;

final class WebDavStorageDriver implements StorageDriver
{
    private const MASTER_DIRECTORY = 'ACSD-73h5ZXp1Q';
    private readonly ?string $url;
    private readonly ?string $username;
    private readonly ?string $password;
    /** @var list<string>|null */
    private readonly ?array $rootSegments;

    public function __construct()
    {
        $url = Env::get('APP_STORAGE_WEBDAV_URL');
        $username = Env::get('APP_STORAGE_WEBDAV_USERNAME');
        $password = Env::get('APP_STORAGE_WEBDAV_PASSWORD');
        $root = Env::get('APP_STORAGE_WEBDAV_ROOT', '');
        $parsedUrl = is_string($url) ? parse_url($url) : false;
        if (!is_array($parsedUrl)
            || ($parsedUrl['scheme'] ?? '') !== 'https'
            || !isset($parsedUrl['host'])
            || isset($parsedUrl['user'])
            || isset($parsedUrl['pass'])
            || isset($parsedUrl['query'])
            || isset($parsedUrl['fragment'])) {
            $url = null;
        }

        $this->url = is_string($url) ? rtrim($url, '/') : null;
        $this->username = is_string($username) && $username !== '' ? $username : null;
        $this->password = is_string($password) && $password !== '' ? $password : null;
        $this->rootSegments = $this->parseRoot($root);
    }

    public function createForUser(string $publicUserId): string
    {
        if (!preg_match('/\A[A-Za-z0-9]{9}\z/', $publicUserId)) {
            throw new RuntimeException('Invalid public user ID for storage provisioning.');
        }

        $directoryName = 'UCSD-' . $publicUserId . '-001';
        $userPath = [...$this->masterPath(), $directoryName];
        $this->ensurePath($this->masterPath());
        if ($this->collectionExists($userPath)) {
            throw new RuntimeException('The private user storage directory already exists.');
        }

        $this->createCollection($userPath);
        try {
            foreach (StorageProvisioner::DEFAULT_FOLDERS as $folder) {
                $this->createCollection([...$userPath, $folder]);
            }
        } catch (\Throwable $exception) {
            $this->removeRemoteTree($userPath);
            throw $exception;
        }

        return $directoryName;
    }

    public function removeForUser(string $directoryName): void
    {
        $this->validateDirectoryName($directoryName);
        $this->removeRemoteTree([...$this->masterPath(), $directoryName]);
    }

    public function listContents(string $directoryName, string $folderName): array
    {
        $this->validateDirectoryName($directoryName);
        $this->validateFolderName($folderName);
        $folderPath = [...$this->masterPath(), $directoryName, $folderName];
        $this->assertCollection($folderPath);

        return $this->listRemote($folderPath);
    }

    public function createFolderForUser(string $directoryName, string $folderName): void
    {
        $this->validateDirectoryName($directoryName);
        $this->validateFolderName($folderName);
        $path = [...$this->masterPath(), $directoryName, $folderName];
        $this->assertCollection([...$this->masterPath(), $directoryName]);
        if ($this->resourceExists($path)) {
            throw new RuntimeException('A folder with that name already exists.');
        }
        $this->createCollection($path);
    }

    public function removeEmptyFolderForUser(string $directoryName, string $folderName): void
    {
        $entries = $this->listContents($directoryName, $folderName);
        if ($entries !== []) {
            throw new RuntimeException('Only empty folders can be deleted.');
        }

        $this->deleteResource([...$this->masterPath(), $directoryName, $folderName]);
    }

    public function storeUploadedFile(
        string $directoryName,
        string $folderName,
        string $temporaryPath,
        string $fileName
    ): int {
        $this->validateDirectoryName($directoryName);
        $this->validateFolderName($folderName);
        $this->validateFileName($fileName);
        if (!is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('The uploaded file could not be verified.');
        }

        $path = [...$this->masterPath(), $directoryName, $folderName, $fileName];
        $this->assertCollection(array_slice($path, 0, -1));
        if ($this->resourceExists($path)) {
            throw new RuntimeException('An item with that name already exists.');
        }

        $size = filesize($temporaryPath);
        $source = fopen($temporaryPath, 'rb');
        if ($size === false || $source === false) {
            if (is_resource($source)) {
                fclose($source);
            }
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        try {
            $this->request($path, 'PUT', input: $source, inputSize: $size);
        } finally {
            fclose($source);
        }

        return $size;
    }

    public function fileSizeForUser(string $directoryName, string $folderName, string $fileName): int
    {
        $metadata = $this->fileMetadata($directoryName, $folderName, $fileName);
        if ($metadata['type'] !== 'file') {
            throw new RuntimeException('The requested storage item is not a file.');
        }

        return $metadata['size'];
    }

    public function streamFileForUser(
        string $directoryName,
        string $folderName,
        string $fileName,
        $destination
    ): void {
        $this->fileMetadata($directoryName, $folderName, $fileName);
        $path = [...$this->masterPath(), $directoryName, $folderName, $fileName];
        $this->request($path, 'GET', output: $destination);
    }

    public function deleteFileForUser(string $directoryName, string $folderName, string $fileName): int
    {
        $size = $this->fileSizeForUser($directoryName, $folderName, $fileName);
        $this->deleteResource([...$this->masterPath(), $directoryName, $folderName, $fileName]);

        return $size;
    }

    public function readiness(PDO $pdo): array
    {
        $configurationError = $this->configurationError();
        if ($configurationError !== null) {
            return [
                'ready' => false,
                'detail' => $configurationError,
                'fix' => 'Set APP_STORAGE_DRIVER=webdav and configure APP_STORAGE_WEBDAV_URL, APP_STORAGE_WEBDAV_USERNAME, APP_STORAGE_WEBDAV_PASSWORD, and APP_STORAGE_WEBDAV_ROOT. Use the HTTPS Web Disk URL and a dedicated Web Disk account restricted to this app’s private folder.',
            ];
        }

        try {
            $masterPath = $this->masterPath();
            $this->ensurePath($masterPath);
            $probePath = [...$masterPath, '.garwinai-check-' . bin2hex(random_bytes(6))];
            $this->createCollection($probePath);
            $this->deleteResource($probePath);

            $accounts = $pdo->query(
                'SELECT u.user_id, s.storage_key
                 FROM users u
                 LEFT JOIN storage_accounts s ON s.user_id = u.id'
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($accounts as $account) {
                $expectedKey = 'UCSD-' . $account['user_id'] . '-001';
                if (!is_string($account['storage_key'])
                    || !hash_equals($expectedKey, $account['storage_key'])) {
                    throw new RuntimeException('An account has an invalid WebDAV storage key.');
                }

                $userPath = [...$masterPath, $expectedKey];
                $this->assertCollection($userPath);
                $folders = array_column($this->listRemote($userPath), 'name');
                foreach (StorageProvisioner::DEFAULT_FOLDERS as $folder) {
                    if (!in_array($folder, $folders, true)) {
                        throw new RuntimeException('An account is missing a required storage folder.');
                    }
                }
            }
        } catch (RuntimeException $exception) {
            return [
                'ready' => false,
                'detail' => $exception->getMessage(),
                'fix' => 'Verify that the Web Disk account can read, create, and delete folders in its restricted app directory, and that existing account folders are present. Check cPanel Web Disk access and the PHP error log.',
            ];
        }

        return [
            'ready' => true,
            'detail' => 'The HTTPS WebDAV endpoint is writable and all account storage folders are present.',
            'fix' => 'No action required.',
        ];
    }

    /** @return array{name: string, type: string, size: int, modified_at: int} */
    private function fileMetadata(string $directoryName, string $folderName, string $fileName): array
    {
        $this->validateDirectoryName($directoryName);
        $this->validateFolderName($folderName);
        $this->validateFileName($fileName);
        $path = [...$this->masterPath(), $directoryName, $folderName, $fileName];
        $resources = $this->propfind($path, '0');
        foreach ($resources as $resource) {
            if ($resource['name'] === $fileName) {
                if ($resource['type'] !== 'file') {
                    throw new StorageItemNotFound('The requested storage file is unavailable.');
                }

                return [
                    'name' => $resource['name'],
                    'type' => $resource['type'],
                    'size' => $resource['size'],
                    'modified_at' => $resource['modified_at'],
                ];
            }
        }

        throw new StorageItemNotFound('The requested storage file is unavailable.');
    }

    /** @return list<array{name: string, type: string, size: int, modified_at: int}> */
    private function listRemote(array $path): array
    {
        $entries = $this->propfind($path, '1');
        $targetPath = $this->decodedUrlPath($path);

        $entries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['href_path'] !== $targetPath
        ));

        return array_map(
            static fn (array $entry): array => [
                'name' => $entry['name'],
                'type' => $entry['type'],
                'size' => $entry['size'],
                'modified_at' => $entry['modified_at'],
            ],
            $entries
        );
    }

    /**
     * @return list<array{name: string, type: string, size: int, modified_at: int, href_path: string}>
     */
    private function propfind(array $path, string $depth): array
    {
        $body = '<?xml version="1.0" encoding="utf-8" ?>'
            . '<d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/>'
            . '<d:getcontentlength/><d:getlastmodified/></d:prop></d:propfind>';
        $response = $this->request(
            $path,
            'PROPFIND',
            headers: ['Depth: ' . $depth, 'Content-Type: application/xml; charset=utf-8'],
            body: $body,
            allowMissing: true
        );
        if ($response['status'] === 404) {
            return [];
        }
        if (!in_array($response['status'], [200, 207], true)) {
            throw new RuntimeException('The WebDAV server returned an unexpected directory response.');
        }

        $xml = simplexml_load_string($response['body'], SimpleXMLElement::class, LIBXML_NONET);
        if (!$xml instanceof SimpleXMLElement) {
            throw new RuntimeException('The WebDAV server returned an invalid directory response.');
        }
        $responses = $xml->children('DAV:', true)->response;
        $resources = [];
        $targetPath = $this->decodedUrlPath($path);
        foreach ($responses as $item) {
            $responseChildren = $item->children('DAV:', true);
            $href = (string) $responseChildren->href;
            $hrefPath = parse_url($href, PHP_URL_PATH);
            if (!is_string($hrefPath)) {
                continue;
            }
            $hrefPath = rtrim(rawurldecode($hrefPath), '/');
            if ($hrefPath !== $targetPath && !str_starts_with($hrefPath, $targetPath . '/')) {
                continue;
            }

            $properties = null;
            foreach ($responseChildren->propstat as $propstat) {
                $propstatChildren = $propstat->children('DAV:', true);
                if (!str_contains((string) $propstatChildren->status, ' 200 ')) {
                    continue;
                }
                $properties = $propstatChildren->prop->children('DAV:', true);
                break;
            }
            if (!$properties instanceof SimpleXMLElement) {
                continue;
            }

            $name = rawurldecode(basename($hrefPath));
            $resourceType = $properties->resourcetype->children('DAV:', true);
            $modifiedAt = strtotime((string) $properties->getlastmodified);
            $resources[] = [
                'name' => $name,
                'type' => isset($resourceType->collection) ? 'folder' : 'file',
                'size' => max(0, (int) $properties->getcontentlength),
                'modified_at' => $modifiedAt === false ? 0 : $modifiedAt,
                'href_path' => $hrefPath,
            ];
        }

        return $resources;
    }

    private function ensurePath(array $path): void
    {
        $current = [];
        foreach ($path as $segment) {
            $current[] = $segment;
            if (!$this->collectionExists($current)) {
                $this->createCollection($current);
            }
        }
    }

    private function assertCollection(array $path): void
    {
        foreach ($this->propfind($path, '0') as $resource) {
            if ($resource['type'] === 'folder') {
                return;
            }
        }
        throw new RuntimeException('The requested private storage folder is unavailable.');
    }

    private function collectionExists(array $path): bool
    {
        foreach ($this->propfind($path, '0') as $resource) {
            if ($resource['type'] === 'folder') {
                return true;
            }
        }

        return false;
    }

    private function resourceExists(array $path): bool
    {
        return $this->propfind($path, '0') !== [];
    }

    private function createCollection(array $path): void
    {
        $response = $this->request($path, 'MKCOL', allowMissing: true);
        if (in_array($response['status'], [201, 200, 204], true)) {
            return;
        }
        if ($response['status'] === 405 && $this->collectionExists($path)) {
            return;
        }
        throw new RuntimeException('Unable to create a private WebDAV storage folder.');
    }

    private function removeRemoteTree(array $path): void
    {
        if (!$this->collectionExists($path)) {
            return;
        }

        foreach ($this->listRemote($path) as $entry) {
            $childPath = [...$path, $entry['name']];
            if ($entry['type'] === 'folder') {
                $this->removeRemoteTree($childPath);
            } else {
                $this->deleteResource($childPath);
            }
        }
        $this->deleteResource($path);
    }

    private function deleteResource(array $path): void
    {
        $response = $this->request($path, 'DELETE', allowMissing: true);
        if (!in_array($response['status'], [200, 202, 204, 404], true)) {
            throw new RuntimeException('Unable to delete a private WebDAV storage item.');
        }
    }

    /**
     * @param list<string> $headers
     * @param resource|null $input
     * @param resource|null $output
     * @return array{status: int, body: string}
     */
    private function request(
        array $path,
        string $method,
        array $headers = [],
        ?string $body = null,
        $input = null,
        ?int $inputSize = null,
        $output = null,
        bool $allowMissing = false
    ): array {
        $this->requireConfiguration();
        $curl = curl_init($this->urlFor($path));
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize the WebDAV request.');
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_RETURNTRANSFER => $output === null,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        if (is_resource($input)) {
            $options[CURLOPT_UPLOAD] = true;
            $options[CURLOPT_INFILE] = $input;
            $options[CURLOPT_INFILESIZE] = $inputSize ?? 0;
        }
        if (is_resource($output)) {
            $options[CURLOPT_FILE] = $output;
        }
        if (!curl_setopt_array($curl, $options)) {
            curl_close($curl);
            throw new RuntimeException('Unable to configure the WebDAV request.');
        }

        $result = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($result === false) {
            throw new RuntimeException('WebDAV connection failed: ' . $error);
        }
        $responseBody = is_string($result) ? $result : '';
        if ($allowMissing && $status === 404) {
            return ['status' => $status, 'body' => $responseBody];
        }
        $expectedStatuses = match ($method) {
            'PROPFIND' => [200, 207],
            'MKCOL' => [200, 201, 204, 405],
            'PUT' => [200, 201, 204],
            'GET' => [200],
            'DELETE' => [200, 202, 204],
            default => [],
        };
        if (!in_array($status, $expectedStatuses, true)) {
            $detail = match ($status) {
                401, 403 => 'WebDAV authentication or folder permissions were rejected.',
                default => 'The WebDAV server returned HTTP ' . $status . '.',
            };
            throw new RuntimeException($detail);
        }

        return ['status' => $status, 'body' => $responseBody];
    }

    private function urlFor(array $path): string
    {
        $encoded = array_map('rawurlencode', $path);

        return $this->url . ($encoded === [] ? '' : '/' . implode('/', $encoded));
    }

    private function decodedUrlPath(array $path): string
    {
        $parsedPath = parse_url($this->urlFor($path), PHP_URL_PATH);

        return is_string($parsedPath) ? rtrim(rawurldecode($parsedPath), '/') : '';
    }

    /** @return list<string> */
    private function masterPath(): array
    {
        $this->requireConfiguration();

        return [...$this->rootSegments, self::MASTER_DIRECTORY];
    }

    /** @return list<string>|null */
    private function parseRoot(?string $root): ?array
    {
        if (!is_string($root) || trim($root, '/') === '') {
            return [];
        }
        $segments = explode('/', trim($root, '/'));
        foreach ($segments as $segment) {
            if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9 _.-]{0,127}\z/', $segment)
                || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $segments;
    }

    private function configurationError(): ?string
    {
        if ($this->url === null || $this->username === null || $this->password === null
            || $this->rootSegments === null) {
            return 'The HTTPS WebDAV endpoint or credentials are incomplete or invalid.';
        }

        return null;
    }

    private function requireConfiguration(): void
    {
        if ($this->configurationError() !== null) {
            throw new RuntimeException('Configure the HTTPS WebDAV endpoint, account credentials, and safe root path.');
        }
    }

    private function validateDirectoryName(string $directoryName): void
    {
        if (!preg_match('/\AUCSD-[A-Za-z0-9]{9}-001\z/', $directoryName)) {
            throw new RuntimeException('Invalid private storage location.');
        }
    }

    private function validateFolderName(string $folderName): void
    {
        if (!preg_match('/\A[A-Za-z0-9 _-]{1,64}\z/', $folderName)
            || $folderName === '.' || $folderName === '..') {
            throw new RuntimeException('Folder names may contain letters, numbers, spaces, hyphens, and underscores only.');
        }
    }

    private function validateFileName(string $fileName): void
    {
        if ($fileName === '' || strlen($fileName) > 255 || $fileName === '.' || $fileName === '..'
            || str_contains($fileName, '/') || str_contains($fileName, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $fileName)) {
            throw new RuntimeException('That file name is not allowed.');
        }
    }
}
