<?php

namespace Discuz\Filesystem;

use Discuz\Contracts\Setting\SettingsRepository;
use GuzzleHttp\Client;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemServiceProvider as ServiceProvider;
use Illuminate\Support\Arr;
use League\Flysystem\Filesystem;

class FilesystemServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->make('filesystem')->extend('local', function ($app, $config) {
            $adapter = new LocalAdapter($config);
            $driver = new Filesystem($adapter);
            return new FilesystemAdapter($driver, $adapter, $config);
        });

        $this->app->make('filesystem')->extend('cos', function ($app, $config) {
            $settings = $this->app->make(SettingsRepository::class);
            $qcloud = $settings->tag('qcloud');
            $container = getenv('KUBERNETES_SERVICE_HOST');
            if ($container && Arr::get($qcloud, 'qcloud_cos')) {
                $data = $this->getTmpSecret($app);
                if ($data) {
                    $qcloud['qcloud_secret_id'] = Arr::get($data, 'TmpSecretId');
                    $qcloud['qcloud_secret_key'] = Arr::get($data, 'TmpSecretKey');
                    $qcloud['qcloud_token'] = Arr::get($data, 'Token');
                }
            }

            $config = array_merge($config, $app->config('filesystems.disks.cos'));

            $config['region'] = Arr::get($qcloud, 'qcloud_cos_bucket_area');
            $config['bucket'] = Arr::get($qcloud, 'qcloud_cos_bucket_name');
            $config['cdn'] = Arr::get($qcloud, 'qcloud_cos_cdn_url', '');

            $config['credentials'] = [
                'secretId' => Arr::get($qcloud, 'qcloud_secret_id'),
                'secretKey' => Arr::get($qcloud, 'qcloud_secret_key'),
                'token' => Arr::get($qcloud, 'qcloud_token', '')
            ];

            $adapter = new CosAdapter($config);
            $driver = new Filesystem($adapter);
            return new FilesystemAdapter($driver, $adapter, $config);
        });
    }

    private function getTmpSecret($app)
    {
        $data = $app['cache']->get('tmp.secret');

        if (!is_null($data)) {
            return $data;
        }

        $client = new Client();
        $response = $client->request('GET', 'http://metadata.tencentyun.com/meta-data/cam/security-credentials/TCB_QcsRole');
        $data = json_decode($response->getBody()->getContents(), TRUE);

        if (is_null($data)) return false;

        $expiredTime = $data['ExpiredTime'] - time() - 10;
        $app['cache']->put('tmp.secret', $data, $expiredTime);

        return $data;
    }
}
