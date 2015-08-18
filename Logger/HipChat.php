<?php
namespace Clue\Logger;

class HipChat extends Syslog{
    static $COLOR_CODES = array(
        'emergency'=>'red',
        'alert'=>'red',
        'critical'=>'red',
        'error'=>'red',
        'warning'=>'yellow',
        'notice'=>'yellow',
        'info'=>'green',
        'debug'=>'gray',
    );

    function __construct($room, $token, $name){
        $this->host='api.hipchat.com';
        $this->token=$token;
        $this->room=$room;
        $this->name=$name;
    }

    protected function getAlertColor($level)
    {
        switch (true) {
            case $level >= Logger::ERROR:
                return 'red';
            case $level >= Logger::WARNING:
                return 'yellow';
            case $level >= Logger::INFO:
                return 'green';
            case $level == Logger::DEBUG:
                return 'gray';
            default:
                return 'yellow';
        }
    }

    function write($data){
        $payload=json_encode([
            'notify' => true,
            'name'=>$this->name,
            'room_id'=>$this->room,
            'message' => implode("\n", $this->format_text($data)),
            'message_format' => 'text',
            'color'=>self::$COLOR_CODES[$data['level']],
        ]);


        $url="https://api.hipchat.com/v2/room/$this->room/notification";
        $url.="?".http_build_query(['format'=>'json', 'auth_token'=>$this->token]);

        $c=curl_init($url);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($c, CURLOPT_TIMEOUT, 5);

        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $this->token"
        ]);

        curl_setopt($c, CURLOPT_POSTFIELDS, $payload);

        $r=curl_exec($c);

        $errno=curl_errno($c);
        $error=curl_error($c);
        curl_close($c);

        if($errno){
            panic("Network error($errno): $error");
        }
    }
}
