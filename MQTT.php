<?php
/*
    based on phpMQTT
    A simple php class to connect/publish/subscribe to an MQTT broker

    Licence

    Copyright (c) 2010 Blue Rhinos Consulting | Andrew Milsted
    andrew@bluerhinos.co.uk | http://www.bluerhinos.co.uk

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.
*/

/**
 * Usage:

 * $mqtt = new Clue\MQTT('client', MQTT_SERVER,['port'=>MQTT_PORT, 'username'=>MQTT_USERNAME, 'password'=>MQTT_PASSWORD]);
 * if ($mqtt->connect()) {
 *     $mqtt->subscribe('test', 'callback', $qos=0);
 *     $mqtt->publish("test","Hello World!");
 *
 *     while($mqtt->proc()){
 *     }
 *
 *     $mqtt->close();
 * }
 *
 * function callback($topic,$msg){
 *    global $mqtt;
 *
 *    printf("%s\t[%s]\t%s\n", date("c"), $topic, $msg);
 *    $mqtt->publish("test", "Hello - ".date("c"));
 * }
 */
namespace Clue;

class MQTT{
    public $keepalive = 10;     /* default keepalive timmer */
    public $timesinceping;      /* host unix time, used to detect disconects */
    public $topics = array();   /* used to store currently subscribed topics */
    public $debug = false;      /* should output debug messages */
    public $address;            /* broker address */
    public $port;               /* broker port */
    public $clientid;           /* client id sent to brocker */
    public $will;               /* stores the will of the client */

    private $username;          /* stores username */
    private $password;          /* stores password */
    private $socket;            /* holds the socket */
    private $msgid = 1;         /* counter for message id */
    private $reconnects=0;

    function __construct($clientid, $address, array $options=[]){
        // 必须的配置
        $this->clientid = $clientid;
        $this->address = $address;

        // 可选配置
        $this->port = @$options['port'] ?: 1883;
        $this->username = @$options['username'];
        $this->password = @$options['password'];
    }

    function reconnect(){
        if($this->socket) @fclose($this->socket);

        do{
            error_log(sprintf("Reconnecting #%d", $this->reconnects++));
            sleep(rand(5,10));  // 随机等待
            $ok=$this->connect(true, $this->will);
        }
        while($ok);

        // 重新订阅
        foreach($this->topics as $key=>$top){
            $this->subscribe($top['topic'], $top['callback'], $top['qos']);
        }

        return true;
    }

    /* connects to the broker
     *
     * @param $will: 最后的遗言
    */
    function connect($will = NULL){
        if($will) $this->will = $will;

        $address = gethostbyname($this->address);
        $this->socket = fsockopen($address, $this->port, $errno, $errstr, 60);

        if (!$this->socket ) {
            if($this->debug) error_log("fsockopen() $errno, $errstr \n");
            return false;
        }

        stream_set_timeout($this->socket, 5);
        stream_set_blocking($this->socket, 0);

        $i = 0;
        $buffer = "";

        $buffer .= chr(0x00); $i++;
        $buffer .= chr(0x06); $i++;
        $buffer .= chr(0x4d); $i++;
        $buffer .= chr(0x51); $i++;
        $buffer .= chr(0x49); $i++;
        $buffer .= chr(0x73); $i++;
        $buffer .= chr(0x64); $i++;
        $buffer .= chr(0x70); $i++;
        $buffer .= chr(0x03); $i++;

        //No Will
        $var = 0;
        if($this->reconnects==0) $var+=2;    // 新建连接，清除之前的会话

        //Add will info to header
        if($this->will != NULL){
            $var += 4; // Set will flag
            $var += ($this->will['qos'] << 3); //Set will qos
            if($this->will['retain'])   $var += 32; //Set will retain
        }

        if($this->username != NULL) $var += 128;    //Add username to header
        if($this->password != NULL) $var += 64; //Add password to header

        $buffer .= chr($var); $i++;

        //Keep alive
        $buffer .= chr($this->keepalive >> 8); $i++;
        $buffer .= chr($this->keepalive & 0xff); $i++;

        $buffer .= $this->strwritestring($this->clientid,$i);

        //Adding will to payload
        if($this->will != NULL){
            $buffer .= $this->strwritestring($this->will['topic'],$i);
            $buffer .= $this->strwritestring($this->will['content'],$i);
        }

        if($this->username) $buffer .= $this->strwritestring($this->username,$i);
        if($this->password) $buffer .= $this->strwritestring($this->password,$i);

        $head = "  ";
        $head{0} = chr(0x10);
        $head{1} = chr($i);

        fwrite($this->socket, $head, 2);
        fwrite($this->socket, $buffer);

        $string = $this->read(4);

        if(ord($string{0})>>4 == 2 && $string{3} == chr(0)){
            if($this->debug) echo "Connected to Broker\n";
        }else{
            error_log(sprintf(
                "Connection failed! (Error: 0x%02x 0x%02x)\n",
                ord($string{0}),ord($string{3})
            ));
            return false;
        }

        $this->timesinceping = time();
        return true;
    }

    /* read: reads in so many bytes */
    function read($int = 8192, $nb = false){
        //  print_r(socket_get_status($this->socket));

        $string="";
        $togo = $int;

        if($nb){
            return fread($this->socket, $togo);
        }

        while (!feof($this->socket) && $togo>0) {
            $fread = fread($this->socket, $togo);
            $string .= $fread;
            $togo = $int - strlen($string);
        }
        return $string;
    }

    /* subscribe: subscribes to topics */
    function subscribe($topic, $callback, $qos=0){
        $i = 0;
        $buffer = "";
        $id = $this->msgid;
        $buffer .= chr($id >> 8);  $i++;
        $buffer .= chr($id % 256);  $i++;

        $buffer .= $this->strwritestring($topic,$i);
        $buffer .= chr($qos);  $i++;
        $this->topics[$topic] = ['topic'=>$topic, 'callback'=>$callback, 'qos'=>$qos];

        // REF: https://github.com/mcxiaoke/mqtt/blob/master/mqtt/02-ControlPacketFormat.md
        $cmd = 0x82;

        $head = chr($cmd);
        $head .= chr($i);

        fwrite($this->socket, $head, 2);
        fwrite($this->socket, $buffer, $i);
        $string = $this->read(2);

        $bytes = ord(substr($string,1,1));
        $string = $this->read($bytes);
    }

    /* ping: sends a keep alive ping */
    function ping(){
        $head = " ";
        $head = chr(0xc0);
        $head .= chr(0x00);
        fwrite($this->socket, $head, 2);
        if($this->debug) echo "ping sent\n";
    }

    /* disconnect: sends a proper disconect cmd */
    function disconnect(){
        $head = " ";
        $head{0} = chr(0xe0);
        $head{1} = chr(0x00);
        fwrite($this->socket, $head, 2);
    }

    /* close: sends a proper disconect, then closes the socket */
    function close(){
        $this->disconnect();
        fclose($this->socket);
    }

    /* publish: publishes $content on a $topic */
    function publish($topic, $content, $qos = 0, $retain = 0){
        $i = 0;
        $buffer = "";

        $buffer .= $this->strwritestring($topic,$i);

        //$buffer .= $this->strwritestring($content,$i);

        if($qos){
            $id = $this->msgid++;
            $buffer .= chr($id >> 8);  $i++;
            $buffer .= chr($id % 256);  $i++;
        }

        $buffer .= $content;
        $i+=strlen($content);


        $head = " ";
        $cmd = 0x30;
        if($qos) $cmd += $qos << 1;
        if($retain) $cmd += 1;

        $head{0} = chr($cmd);
        $head .= $this->setmsglength($i);

        fwrite($this->socket, $head, strlen($head));
        fwrite($this->socket, $buffer, $i);
    }

    /* message: processes a recieved topic */
    function message($msg){
        $tlen = (ord($msg{0})<<8) + ord($msg{1});
        $topic = substr($msg,2,$tlen);
        $msg = substr($msg,($tlen+2));
        $found = 0;
        foreach($this->topics as $key=>$top){
            if( preg_match("/^".str_replace("#",".*",
                    str_replace("+","[^\/]*",
                        str_replace("/","\/",
                            str_replace("$",'\$',
                                $key))))."$/",$topic) ){
                if(is_callable($top['callback'])){
                    call_user_func($top['callback'],$topic,$msg);
                    $found = 1;
                }
            }
        }

        if($this->debug && !$found) echo "msg recieved but no match in subscriptions\n";
    }

    /* proc: the processing loop for an "allways on" client
        set true when you are doing other stuff in the loop good for watching something else at the same time */
    function proc($loop = true){
        if(1){
            $sockets = array($this->socket);
            $w = $e = NULL;
            $cmd = 0;

            //$byte = fgetc($this->socket);
            if(feof($this->socket)){
                if($this->debug) echo "eof receive going to reconnect for good measure\n";
                $this->reconnect();
            }

            $byte = $this->read(1, true);

            if(!strlen($byte)){
                if($loop){
                    usleep(100000);
                }
            }else{
                $cmd = (int)(ord($byte)/16);
                if($this->debug) echo "Recevid: $cmd\n";

                $multiplier = 1;
                $value = 0;
                do{
                    $digit = ord($this->read(1));
                    $value += ($digit & 127) * $multiplier;
                    $multiplier *= 128;
                    }while (($digit & 128) != 0);

                if($this->debug) echo "Fetching: $value\n";

                if($value)
                    $string = $this->read($value,"fetch");

                if($cmd){
                    switch($cmd){
                        case 3:
                            $this->message($string);
                        break;
                    }

                    $this->timesinceping = time();
                }
            }

            if($this->timesinceping < (time() - $this->keepalive )){
                if($this->debug) echo "not found something so ping\n";
                $this->ping();
            }

            if($this->timesinceping<(time()-($this->keepalive*2))){
                if($this->debug) echo "not seen a package in a while, disconnecting\n";
                $this->reconnect();
            }
        }
        return 1;
    }

    /* getmsglength: */
    function getmsglength(&$msg, &$i){
        $multiplier = 1;
        $value = 0 ;
        do{
          $digit = ord($msg{$i});
          $value += ($digit & 127) * $multiplier;
          $multiplier *= 128;
          $i++;
        }while (($digit & 128) != 0);

        return $value;
    }


    /* setmsglength: */
    function setmsglength($len){
        $string = "";
        do{
          $digit = $len % 128;
          $len = $len >> 7;
          // if there are more digits to encode, set the top bit of this digit
          if ( $len > 0 )
            $digit = ($digit | 0x80);
          $string .= chr($digit);
        }while ( $len > 0 );
        return $string;
    }

    /* strwritestring: writes a string to a buffer */
    function strwritestring($str, &$i){
        $ret = " ";
        $len = strlen($str);
        $msb = $len >> 8;
        $lsb = $len % 256;
        $ret = chr($msb);
        $ret .= chr($lsb);
        $ret .= $str;
        $i += ($len+2);
        return $ret;
    }

    function printstr($string){
        $strlen = strlen($string);
        for($j=0;$j<$strlen;$j++){
            $num = ord($string{$j});
            if($num > 31)
                $chr = $string{$j}; else $chr = " ";
            printf("%4d: %08b : 0x%02x : %s \n",$j,$num,$num,$chr);
        }
    }
}
