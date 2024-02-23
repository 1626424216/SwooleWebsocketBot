#! /opt/soft/swoole-cli
<?php
use Swoole\Coroutine;
use function Swoole\Coroutine\run;

date_default_timezone_set('Asia/Shanghai');


foreach (glob('./class/*.php') as $file) {
    $file = explode('/', $file)['2'];
    include './class/' . $file;
}

run(function () {
    include './config.php';
    $inc = new inc($config['ip'], $config['port'], $config['token']);
    $client = $inc->connect_ws();
    if ($client->getStatusCode() == '403') {
        echo "Toekn错误：" . $client->getStatusCode() . '/' . $client->errCode;
    } else if ($client->getStatusCode() == '-1' or $client->errCode == '114') {
        echo "网络连接失败 ";
    } else {
        echo "连接ws服务端成功：" . ' BOT_QQ：' . json_decode(@$client->recv()->data, true)['self_id'] . PHP_EOL;
    }
    while ($client->getStatusCode() != '403') {
        $ws_data = $client->recv();
        if (empty($ws_data)) {
            echo "网络中断 等待5s重连" . PHP_EOL;
            $client->close();
            Swoole\Coroutine\System::sleep(5);
            $client = $inc->connect_ws();
            switch ($client->getStatusCode()) {
                default:
                    echo "错误码：" . $client->getStatusCode() . '/' . $client->errCode . PHP_EOL;
                    break;
                case '101':
                    echo "恢复ws连接成功" . $client->getStatusCode() . PHP_EOL;
                    break;
                case '403':
                    echo "Token错误" . PHP_EOL;
                    break;
            }
        } else {
            $op_data = json_decode($ws_data->data, true);
            if (isset($op_data['post_type'])) {
                switch ($op_data['post_type']) {
                    case 'meta_event'://心跳通知
                        //echo '心跳：' . $op_data['time'] . PHP_EOL;
                        break;
                    case 'notice'://群通知
                        break;
                    case 'request'://好友通知
                        break;
                    case 'message'://接收消息
                        $inc->update_op_message($op_data);
                        switch ($op_data['message_type']) {
                            case 'private'://私聊消息
                                echo '[' . date('Y-m-d H:i:s') . ']' . '(' . $op_data['user_id'] . ')私收：' . $op_data['message'] . PHP_EOL;
                                break;
                            case 'group'://群聊消息
                                echo '[' . date('Y-m-d H:i:s') . ']' . '[' . $op_data['group_id'] . '](' . $op_data['user_id'] . ')：群收：' . $op_data['message'] . PHP_EOL;
                                break;
                        }
                        Coroutine::create(function () use ($client, $op_data,$inc) {
                                foreach (glob('./plugins/*.php') as $file) {
                                    $file = explode('/', $file)['2'];
                                    require './plugins/' . $file;
                                }
                        });
                        break;
                }
            }
        }
    }
});