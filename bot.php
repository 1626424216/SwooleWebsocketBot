#! /opt/soft/swoole-cli
<?php

date_default_timezone_set('Asia/Shanghai');

foreach (glob('./class/*.class.php') as $file) {
    $file = explode('/', $file)['2'];
    include './class/' . $file;
}
Swoole\Coroutine\run(function () {
    $debug='';
    $configs='';
    require './configs.php';
    $coroutineIds = [];

    for ($i = 0; $i < count($configs); $i++) {
        echo "正在创建第 [" . ($i + 1) . "] 个协程" . PHP_EOL;

        $config = $configs[$i];

        $coroutineId = Swoole\Coroutine::create(function () use ($config,$debug) {
            $inc = new bot_inc($config);
            $inc->run($debug);
        });


        $coroutineIds[] = $coroutineId;
    }

    echo "共创建 [" . $i . "] 个协程" . PHP_EOL;


    while (!empty($coroutineIds)) {
        foreach ($coroutineIds as $key => $coroutineId) {
            $coroutineStats = Swoole\Coroutine::stats();
            if (!isset($coroutineStats[$coroutineId]) || $coroutineStats[$coroutineId]['finished']) {
                if ($debug) echo "协程 [" . $coroutineId . "] 已结束" . PHP_EOL;

                unset($coroutineIds[$key]);
            }
        }

        usleep(100000);
    }

});