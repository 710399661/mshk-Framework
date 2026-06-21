<?php

namespace Discuz\Filesystem;

use Exception;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Arr;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\PathPrefixer;
use Qcloud\Cos\Client;
use Throwable;

class CosAdapter implements FilesystemAdapter
{
    protected $client;
    protected $httpClient;
    protected $config;
    protected $prefixer;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->prefixer = new PathPrefixer($config['root'] ?? '');
    }

    public function getBucket()
    {
        return $this->config['bucket'];
    }

    public function getAppId()
    {
        return $this->config['credentials']['appId'] ?? null;
    }

    public function getRegion()
    {
        return $this->config['region'] ?? '';
    }

    public function getSourcePath($path)
    {
        $schema = $this->config['schema'] ? $this->config['schema'] . '://' : 'https://';
        return sprintf(
            $schema . '%s.cos.%s.myqcloud.com/%s',
            $this->getBucket(),
            $this->getRegion(),
            $path
        );
    }

    public function getUrl($path)
    {
        if (!empty($this->config['cdn'])) {
            return rtrim($this->config['cdn'], '/') . '/' . ltrim($path, '/');
        }

        $options = [
            'Schema' => $this->config['schema'] ?? 'https',
        ];

        return $this->getClient()->getObjectUrl(
            $this->getBucket(),
            $path,
            null,
            $options
        );
    }

    public function getTemporaryUrl($path, $expiration, array $options = [])
    {
        $options = array_merge($options, ['Schema' => $this->config['schema'] ?? 'https']);
        $expiration = date('c', !is_numeric($expiration) ? strtotime($expiration) : intval($expiration));

        $objectUrl = $this->getClient()->getObjectUrl(
            $this->getBucket(),
            $path,
            $expiration,
            $options
        );

        $url = parse_url($objectUrl);

        if (!empty($this->config['cdn'])) {
            return sprintf(
                '%s/%s?%s',
                rtrim($this->config['cdn'], '/'),
                ltrim(urldecode($url['path']), '/'),
                $url['query']
            );
        }

        return $objectUrl;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $options = $this->getUploadOptions($config);
        $this->getClient()->upload($this->getBucket(), $path, $contents, $options);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $options = $this->getUploadOptions($config);
        $this->getClient()->upload(
            $this->getBucket(),
            $path,
            stream_get_contents($contents, -1, 0),
            $options
        );
    }

    public function update(string $path, string $contents, Config $config): void
    {
        $this->write($path, $contents, $config);
    }

    public function updateStream(string $path, $contents, Config $config): void
    {
        $this->writeStream($path, $contents, $config);
    }

    public function rename(string $path, string $newPath): void
    {
        $this->copy($path, $newPath, new Config());
        $this->delete($path);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $sourceConfig = [
            'Region' => $this->getRegion(),
            'Bucket' => $this->getBucket(),
            'Key' => $source,
        ];
        $this->getClient()->copy($this->getBucket(), $destination, $sourceConfig);
    }

    public function delete(string $path): void
    {
        $this->getClient()->deleteObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);
    }

    public function deleteDirectory(string $path): void
    {
        $response = $this->listObjects($path);
        if (empty($response['Contents'])) {
            return;
        }
        $keys = array_map(function ($item) {
            return ['Key' => $item['Key']];
        }, (array) $response['Contents']);
        $this->getClient()->deleteObjects([
            'Bucket' => $this->getBucket(),
            'Objects' => $keys,
        ]);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->getClient()->putObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path . '/',
            'Body' => '',
        ]);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->getClient()->PutObjectAcl([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
            'ACL' => $this->normalizeVisibility($visibility),
        ]);
    }

    public function visibility(string $path): FileAttributes
    {
        $meta = $this->getClient()->getObjectAcl([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);

        $visibility = 'private';
        foreach ($meta['Grants'] as $grant) {
            if ('READ' === $grant['Grant']['Permission'] && false !== strpos($grant['Grantee']['URI'] ?? '', 'global/AllUsers')) {
                $visibility = 'public';
                break;
            }
        }

        return new FileAttributes(
            $path,
            $visibility,
            0,
            null,
            null,
            ['type' => 'file']
        );
    }

    public function mimeType(string $path): FileAttributes
    {
        $meta = $this->getClient()->headObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);

        return new FileAttributes(
            $path,
            null,
            0,
            null,
            $meta['ContentType'] ?? 'application/octet-stream',
            ['type' => 'file']
        );
    }

    public function fileSize(string $path): FileAttributes
    {
        $meta = $this->getClient()->headObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);

        return new FileAttributes(
            $path,
            null,
            $meta['ContentLength'] ?? 0,
            null,
            null,
            ['type' => 'file']
        );
    }

    public function lastModified(string $path): FileAttributes
    {
        $meta = $this->getClient()->headObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);

        return new FileAttributes(
            $path,
            null,
            0,
            isset($meta['LastModified']) ? strtotime($meta['LastModified']) : null,
            null,
            ['type' => 'file']
        );
    }

    public function read(string $path): string
    {
        try {
            if (Arr::get($this->config, 'read_from_cdn')) {
                return $this->getHttpClient()
                    ->get($this->getTemporaryUrl($path, date('+5 min')))
                    ->getBody()
                    ->getContents();
            } else {
                return (string) $this->getClient()->getObject([
                    'Bucket' => $this->getBucket(),
                    'Key' => $path,
                ])['Body'];
            }
        } catch (Throwable $e) {
            return '';
        }
    }

    public function readStream(string $path)
    {
        $temporaryUrl = $this->getTemporaryUrl($path, strtotime('+5 min'));
        return $this->getHttpClient()
            ->get($temporaryUrl, ['stream' => true])
            ->getBody()
            ->detach();
    }

    public function listContents(string $path = '', bool $deep = false): iterable
    {
        $response = $this->listObjects($path, $deep);
        $list = [];

        foreach ((array) $response['Contents'] as $content) {
            $pathInfo = pathinfo($content['Key']);
            $isDir = '/' === substr($content['Key'], -1);

            $list[] = new FileAttributes(
                $content['Key'],
                null,
                $isDir ? 0 : intval($content['Size']),
                isset($content['LastModified']) ? strtotime($content['LastModified']) : null,
                null,
                ['type' => $isDir ? 'dir' : 'file']
            );
        }

        return $list;
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->getClient()->headObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        return $this->fileExists(rtrim($path, '/') . '/');
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function getClient()
    {
        return $this->client ?: $this->client = new Client($this->config);
    }

    public function setClient(Client $client)
    {
        $this->client = $client;
        return $this;
    }

    public function getHttpClient()
    {
        return $this->httpClient ?: $this->httpClient = new HttpClient();
    }

    public function setHttpClient(HttpClient $client)
    {
        $this->httpClient = $client;
        return $this;
    }

    public function setPathPrefix(string $prefix): void
    {
        $this->prefixer = new PathPrefixer($prefix);
    }

    protected function listObjects(string $directory = '', bool $recursive = false)
    {
        return $this->getClient()->listObjects([
            'Bucket' => $this->getBucket(),
            'Prefix' => ('' === (string) $directory) ? '' : ($directory . '/'),
            'Delimiter' => $recursive ? '' : '/',
        ]);
    }

    protected function getUploadOptions(Config $config)
    {
        $options = [];
        if ($config->has('header')) {
            $options += $config->get('header');
        }
        if ($config->has('params')) {
            $options['params'] = $config->get('params');
        }
        if ($config->has('visibility')) {
            $options['params']['ACL'] = $this->normalizeVisibility($config->get('visibility'));
        }
        return $options;
    }

    protected function normalizeVisibility($visibility)
    {
        switch ($visibility) {
            case 'public':
                $visibility = 'public-read';
                break;
        }
        return $visibility;
    }
}
