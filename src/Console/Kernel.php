<?php

/**
 * Copyright (C) 2020 Tencent Cloud.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace mshk\Console;

use App\Common\CacheKey;
use mshk\Base\DzqCache;
use mshk\Base\DzqKernel;
use mshk\Common\Utils;
use mshk\Console\Event\Configuring;
use mshk\Foundation\SiteApp;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use mshk\Foundation\Application;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use ReflectionClass;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Finder\Finder;

class Kernel extends SiteApp implements KernelContract
{
    /**
     * The output from the previous command.
     *
     * @var \Symfony\Component\Console\Output\BufferedOutput
     */
    protected $lastOutput;

    protected $disco;

    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->app->booted(function () {
            $this->defineConsoleSchedule();
        });
    }

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function defineConsoleSchedule()
    {

        $lastReq = app('cache')->get(CacheKey::OAC_REQUEST_TIME);
        if (!empty($lastReq)) {
            //超过30分钟,关闭定时脚本
            if ((time() - $lastReq) / 60.0 > 30) return;
        }
        $this->app->singleton(Schedule::class, function ($app) {
            return tap(new Schedule($this->scheduleTimezone()), function (Schedule &$schedule) use ($app) {
                $this->schedule($schedule);
                $pluginList = Utils::getPluginList();
                foreach ($pluginList as $item) {
                    $consolePath = $item['plugin_' . $item['app_id']]['console'];
                    if (empty($consolePath) || !file_exists($consolePath)) continue;
                    $commands = Finder::create()->in($consolePath)->files();
                    foreach ($commands as $command) {
                        $command = $this->getPluginFileClass($command);
                        if (is_subclass_of($command, DzqKernel::class)) {
                            $kernel = new $command($app);
                            if (method_exists($kernel, 'schedule')) {
                                $kernel->schedule($schedule);
                            }
                            break;
                        }
                    }
                }
            });
        });
    }

    private function getPluginFileClass($pluginFinderCommand)
    {
        return 'Plugin' . str_replace(['/', '.php'], ['\\', ''], Str::after($pluginFinderCommand->getPathname(), base_path('plugin')));
    }

    /**
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function listen()
    {
        $this->siteBoot();

        $console = $this->getDisco();

        $this->app['events']->dispatch(new Configuring($console, $this->app));

        $this->load($console);

        exit($console->run());
    }

    protected function getName()
    {
        return <<<EOF
 _____   _                           _____   _
(____ \ (_)                         (____ \ (_)
 _   \ \ _  ___  ____ _   _ _____    _   \ \ _  ___  ____ ___
| |   | | |/___)/ ___) | | (___  )  | |   | | |/___)/ ___) _ \
| |__/ /| |___ ( (___| |_| |/ __/   | |__/ /| |___ ( (__| |_| |
|_____/ |_(___/ \____)\____(_____)  |_____/ |_(___/ \____)___/
EOF;
    }

    protected function registerServiceProvider()
    {
        $this->app->register(ConsoleServiceProvider::class);
    }

    /**
     * Handle an incoming console command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     * @return int
     */
    public function bootstrap()
    {
        $this->siteBoot();
    }

    public function handle($input, $output = null)
    {
        // TODO: Implement handle() method.
    }

    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        $this->siteBoot();

        $console = $this->getDisco();

        $this->app['events']->dispatch(new Configuring($console, $this->app));

        [$command, $input] = $this->parseCommand($command, $parameters);

        return $console->get($command)->run($input, $this->lastOutput = $outputBuffer ?: new BufferedOutput());
    }

    /**
     * Queue an Artisan console command by name.
     *
     * @param string $command
     * @param array $parameters
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     */
    public function queue($command, array $parameters = [])
    {
        // TODO: Implement queue() method.
    }

    /**
     * Get all of the commands registered with the console.
     *
     * @return array
     */
    public function all()
    {
        // TODO: Implement all() method.
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        // TODO: Implement output() method.
    }

    /**
     * Terminate the application.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param int $status
     * @return void
     */
    public function terminate($input, $status)
    {
        // TODO: Implement terminate() method.
    }

    public function getDisco(): ConsoleApplication
    {
        return $this->disco ?? $this->disco = new ConsoleApplication($this->getName(), Application::VERSION);
    }

    public function setDisco(ConsoleApplication $application)
    {
        $this->disco = $application;
    }

    /**
     * @param ConsoleApplication $console
     * @throws \ReflectionException
     */
    protected function load(ConsoleApplication $console)
    {
        $paths = app_path('Console/Commands');
        $paths = array_unique(Arr::wrap($paths));
        $paths = array_filter($paths, function ($path) {
            return is_dir($path);
        });
        if (empty($paths)) {
            return;
        }
        $commands = Finder::create()->in($paths)->files();
        foreach ($commands as $command) {
            $command = $this->app->getNamespace() . str_replace(['/', '.php'], ['\\', ''], Str::after($command->getPathname(), realpath(app_path()) . DIRECTORY_SEPARATOR));
            $this->doCommand($console, $command);
        }
        $pluginList = Utils::getPluginList();
        foreach ($pluginList as $item) {
            $consolePath = $item['plugin_' . $item['app_id']]['console'];
            if (empty($consolePath) || !file_exists($consolePath)) continue;
            $commands = Finder::create()->in($consolePath)->files();
            foreach ($commands as $command) {
                $command = $this->getPluginFileClass($command);
                $this->doCommand($console, $command);
            }
        }
    }

    private function doCommand(&$console, $command)
    {
        if (is_subclass_of($command, Command::class) &&
            !(new ReflectionClass($command))->isAbstract()) {
            $console->add($this->app->make($command));
        }
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return \DateTimeZone|string|null
     */
    protected function scheduleTimezone()
    {
        return $this->app->config('timezone');
    }

    /**
     * Get the name of the cache store that should manage scheduling mutexes.
     *
     * @return string
     */
    protected function scheduleCache()
    {
        return;
    }

    /**
     * Parse the incoming Artisan command and its input.
     *
     * @param string $command
     * @param array $parameters
     * @return array
     */
    protected function parseCommand($command, $parameters)
    {
        if (is_subclass_of($command, Command::class)) {
            $callingClass = true;

            $command = $this->app->make($command)->getName();
        }

        if (!isset($callingClass) && empty($parameters)) {
            $input = new StringInput($command);
        } else {
            array_unshift($parameters, $command);

            $input = new ArrayInput($parameters);
        }

        return [$command, $input ?? null];
    }
}
