<?php
if (substr($op_data['message'], 0, 1) === '/' and str_contains($op_data['message'], '/测试消息')) {
    $inc->send_msg('自动回复私聊或群聊发送实例');//默认当前消息 群就回群 私聊回私聊
    $inc->send_msg('指定群发送', '1043240470', 1);//非默认当前场景 默认0自动选择环境 群发为1 私发为2
    $inc->send_msg('指定联系人发送', '1626424216', 2);//非默认当前场景 默认0自动选择环境 群发为1 私发为2
}