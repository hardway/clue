<?php
namespace Clue\Logger;

class GELF implements Logger{
    static $PSRLEVELS = array(
        'emergency'=>0,
        'alert'=>1,
        'critical'=>2,
        'error'=>3,
        'warning'=>4,
        'notice'=>5,
        'info'=>6,
        'debug'=>7,
    );

    function __construct($server, $port=12201, $defaults=['version'=>'1.1']){
        $this->server=$server;
        $this->port=$port;

        $this->defaults=$defaults;
        if(!isset($this->defaults['host'])) $this->defaults['host']=gethostname();

        $this->timeout=30;
        $this->udp=stream_socket_client("udp://$this->server:$this->port", $errno, $error, $this->timeout);

        if(empty($this->udp)) panic("UDP stream init fail($errno): $error");
        stream_set_blocking($this->udp, 0);
    }

    function __destruct(){
        if($this->udp){
            fclose($this->udp);
            $this->udp=null;
        }
    }

    /**
     * Data --> JSON --> Gzip
     */
    function encode($data){
        return gzcompress(json_encode($data), -1);
    }

    function write($data){
        $data=array_merge($this->defaults, $data);
        $data['level']=self::$PSRLEVELS[$data['level']];
        $data['short_message']=$data['message']; unset($data['message']);

        $payload=$this->encode($data);

        if($this->udp){
            $written=@fwrite($this->udp, $payload);

            if($written===false) panic("UDP stream write failed");
        }

        return $written;
    }

    // TODO: send large message in chunks
    // REF: https://github.com/bzikarsky/gelf-php/blob/master/src/Gelf/Transport/UdpTransport.php
}
