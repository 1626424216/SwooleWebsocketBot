<?php
use Swoole\Coroutine\Http\Client;
class inc{
    private $ip;
    private $port;
    private $token;
    private $op_data;
    private $op_message;
    public function __construct($ip,$port,$token){
        $this->ip = $ip;
        $this->port = $port;
        $this->token = $token;
    }
public function connect_ws()
{
        $client = new Client($this->ip, $this->port);
        $client->setHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ]);
        $client->upgrade('/');
        return $this->op_data=$client;
}
    public function convertip($ip)
    {
        $dir = './data/ip.json';
        $data = file_get_contents($dir);
        $data = json_decode($data, true);
        if (isset($data['ip'][$ip])) {
            return $data["ip"][$ip];//本地服务器给出结果 存在
        } else {
            $client = new Client('token.ip.api.useragentinfo.com', 443, true);
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
    public function update_op_message($op_data){
        $this->op_message=$op_data;
    }
    public function send_msg($message,$id=true,$type=0,$reply=true){
        $op_data=$this->op_message;
        if ($type===0){
            switch ($op_data['message_type']) {
                case 'private'://私聊消息
                    $type=2;
                     break;
                case 'group'://群聊消息
                    $type=1;
                    break;
            }
        }
        if ($type===1){
            if ($id===true){
                $id=$op_data['group_id'];
            }
            if ($reply===true){
                $reply=[
                    'type' => 'reply',
                    'data' => [
                        'id' => $op_data['message_id']
                    ]
                ];
            }else{
                $reply=[];
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
                                'text' => $message
                            ]
                        ],
                    ]
                ]
            ];
            $this->op_data->push(json_encode($message));
        }else if ($type===2){
            if ($id===true){
                $id=$op_data['user_id'];
            }
            if ($reply===true){
                $reply=[
                    'type' => 'reply',
                    'data' => [
                        'id' => $op_data['message_id']
                    ]
                ];
            }else{
                $reply=[];
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
                                'text' => $message
                            ]
                        ],
                    ]
                ]
            ];
            $this->op_data->push(json_encode($message));
        }
    }
}