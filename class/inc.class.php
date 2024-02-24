<?php
class bot_inc
{
    private $op_data;
    private $op_message;
    private $config;
    private $botqq;
    private $coid;
    private $debug;
    private $coroutineIds = [];
    public function __construct($config)
    {
        $this->config = $config;
        $this->coid = swoole\Coroutine::getuid() - 1;
    }
    public function run($debug = true)
    {
        $this->debug = $debug;
        $client = $this->connect_ws();
        if ($client->getStatusCode() == '403') {
            echo "[" . $this->coid . "]" . "自动退出 Toekn错误：" . $client->getStatusCode() . '/' . $client->errCode . PHP_EOL;
        } else if ($client->getStatusCode() == '-1' or $client->errCode == '114') {
            echo "[" . $this->coid . "]" . "网络连接失败 ";
        } else {
            $this->botqq = json_decode(@$client->recv()->data, true)['self_id'];
            echo "[" . $this->coid . "]" . "连接ws服务端成功：" . ' BOT_QQ：' . json_decode(@$client->recv()->data, true)['self_id'] . PHP_EOL;
        }
        while ($client->getStatusCode() != '403') {
            $ws_data = $client->recv();
            if (empty($ws_data)) {
                echo "[" . $this->coid . "]" . "网络中断 等待5s重连" . PHP_EOL;
                $client->close();
                Swoole\Coroutine\System::sleep(5);
                $client = $this->connect_ws();
                switch ($client->getStatusCode()) {
                    default:
                        echo "[" . $this->coid . "]" . "错误码：" . $client->getStatusCode() . '/' . $client->errCode . PHP_EOL;
                        break;
                    case '101':
                        echo "[" . $this->coid . "]" . "恢复ws连接成功" . $client->getStatusCode() . PHP_EOL;
                        $this->botqq = json_decode(@$client->recv()->data, true)['self_id'];
                        break;
                    case '403':
                        echo "[" . $this->coid . "]" . "自动退出 Token错误" . PHP_EOL;
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
                            $this->update_op_message($op_data);
                            switch ($op_data['message_type']) {
                                case 'private'://私聊消息
                                    echo "[" . $this->coid . "]" . '[' . date('Y-m-d H:i:s') . ']' . '(' . $op_data['user_id'] . ')：→私收：' . $op_data['message'] . PHP_EOL;
                                    break;
                                case 'group'://群聊消息
                                    echo "[" . $this->coid . "]" . '[' . date('Y-m-d H:i:s') . ']' . '[' . $op_data['group_id'] . '](' . $op_data['user_id'] . ')：→群收：' . $op_data['message'] . PHP_EOL;
                                    break;
                            }
                            $coroutineId = Swoole\Coroutine::create(function () use ($client, $op_data) {
                                foreach (glob('./plugins/*.php') as $file) {
                                    $file = explode('/', $file)['2'];
                                    require './plugins/' . $file;
                                }
                            });
                            $this->coroutineIds = $coroutineId;
                            $coroutineIds[] = $this->coroutineIds;
                            while (!empty($coroutineIds)) {
                                foreach ($coroutineIds as $key => $coroutineId) {
                                    $coroutineStats = Swoole\Coroutine::stats();
                                    if (!isset($coroutineStats[$coroutineId]) || $coroutineStats[$coroutineId]['finished']) {
                                        if ($this->debug)
                                            echo "协程 [" . $coroutineId . "] 已结束" . PHP_EOL;

                                        unset($coroutineIds[$key]);
                                    }
                                }

                                usleep(100000);
                            }
                            break;
                    }
                }
            }
        }
    }
    private function connect_ws()
    {
        $client = new Swoole\Coroutine\Http\Client($this->config['ip'], $this->config['port']);
        $client->setHeaders([
            'Authorization' => 'Bearer ' . $this->config['token']
        ]);
        $client->upgrade('/');
        return $this->op_data = $client;
    }
    private function convertip($ip)
    {
        $dir = './data/ip.json';
        $data = file_get_contents($dir);
        $data = json_decode($data, true);
        if (isset($data['ip'][$ip])) {
            return $data["ip"][$ip];//本地服务器给出结果 存在
        } else {
            $client = new Swoole\Coroutine\Http\Client('token.ip.api.useragentinfo.com', 443, true);
            $client->set(['timeout' => 2]);
            $client->setHeaders([
                'User-Agent' => 'Mozilla/4.0 (compatible; MSIE 5.00; Windows 98)'
            ]);
            $client->get("/json?token=ab28a017dc0b7536f452fd951aed51d2&ip=" . $ip);
            $gip = $client->getBody();
            if ($gip) {
                $gip = json_decode($gip, true);
                if ($gip['code'] == 200) {
                    $local = $gip['country'] . $gip['province'] . $gip['city'] . $gip['area'] . $gip['isp'] . $gip['net'];
                    $data = file_get_contents($dir);
                    $json = json_decode($data, true);
                    $json["ip"][$ip] = $local;
                    file_put_contents($dir, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    return $json["ip"][$ip];//远端服务器给出IP结果 存在

                } else {
                    return "数据库未给出结果";//远端服务器未给出IP结果 不存在
                }
            } else {
                return "数据库访问失败";//无法访问远程服务器 异常
            }
        }
    }
    private function update_op_message($op_data)
    {
        $this->op_message = $op_data;
    }
    private function send_msg($mess, $id = true, $type = 0, $reply = true)
    {
        $op_data = $this->op_message;
        $type = [
            'private' => 2,
            'group' => 1,
        ][$op_data['message_type']] ?? 0;
        if ($type === 1) {
            if ($id === true) {
                $id = $op_data['group_id'];
            }
            if ($reply === true) {
                $reply = [
                    'type' => 'reply',
                    'data' => [
                        'id' => $op_data['message_id']
                    ]
                ];
            } else {
                $reply = [];
            }
            $message = [
                'action' => 'send_msg',
                'params' => [
                    'group_id' => $id,
                    "message" => [
                        $reply,
                        [
                            'type' => 'text',
                            'data' => [
                                'text' => $mess
                            ]
                        ],
                    ]
                ]
            ];
            $this->op_data->push(json_encode($message));
            echo "[" . $this->coid . "]" . '[' . date('Y-m-d H:i:s') . ']' . '[' . $op_data['group_id'] . '](' . $op_data['user_id'] . ')：←群发：' . $mess . PHP_EOL;
        } else if ($type === 2) {
            if ($id === true) {
                $id = $op_data['user_id'];
            }
            if ($reply === true) {
                $reply = [
                    'type' => 'reply',
                    'data' => [
                        'id' => $op_data['message_id']
                    ]
                ];
            } else {
                $reply = [];
            }
            $message = [
                'action' => 'send_msg',
                'params' => [
                    'user_id' => $id,
                    "message" => [
                        $reply,
                        [
                            'type' => 'text',
                            'data' => [
                                'text' => $mess
                            ]
                        ],
                    ]
                ]
            ];
            $this->op_data->push(json_encode($message));
            echo "[" . $this->coid . "]" . '[' . date('Y-m-d H:i:s') . ']' . '(' . $op_data['user_id'] . ')：←私发：' . $mess . PHP_EOL;
        }
    }
}