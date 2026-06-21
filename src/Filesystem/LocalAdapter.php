<?php

namespace Discuz\Filesystem;

use League\Flysystem\Local\LocalFilesystemAdapter;
use Discuz\Http\UrlGenerator;

class LocalAdapter extends LocalFilesystemAdapter
{
    protected $config;
    protected $url;

    public function __construct(array $config = [])
    {
        $this->config = $config;

        parent::__construct($this->config['root']);

        $this->url = app(UrlGenerator::class);
    }

    public function getUrl($path)
    {
        return $this->url->to(str_replace('public', '/storage', $path));
    }
}
