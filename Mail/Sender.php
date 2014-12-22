<?php
/**
 * Credit: http://poss.sourceforge.net/email
 * Credit: http://fatfreeframework.com/
 */
namespace Clue\Mail;

class Sender{
    const EOL="\r\n";

    /**
     * @param $server 使用外部服务器或者内置MTA
     */
    function __construct($server=null, $port=null, $username=null, $password=null, $from=null){
        $this->headers=array('MIME-Version'=>'1.0');

        $this->server=$server;
        $this->port=$port;

        $this->username=$username;
        $this->password=$password;

        $this->scheme='';
        // 根据端口自动判别SSL/TLS，也可以在后续自行更改
        if($this->port==587) $this->scheme='tls';
        if($this->port==465) $this->scheme='ssl';


        // 确定本机HOST NAME
        $this->hostname=@$_SERVER['SERVER_NAME'] ?: "localhost";

        $this->sender=new Address($from ?: $this->username);
        $this->recipients=[];

        $this->headers=[];
        $this->attachments=[];

        $this->socket=null;
    }

    /**
     * 添加收件人
     */
    function add_recipient($email, $name=null, $rcpt='to'){
        if(!in_array($rcpt, ['to', 'cc', 'bcc'])) user_error("Invalid rcpt type, valid values: to, cc, bcc");

        $this->recipients[$rcpt][]=new Address($email, $name);
    }

    function add_cc($email, $name=null){ $this->add_recipient($email, $name, 'cc'); }
    function add_bcc($email, $name=null){ $this->add_recipient($email, $name, 'bcc'); }

    /**
     * 普通附件
     */
    function attach($src, $name=null){
        $this->attachments[]=[$name ?: basename($src), $src, 'attachment'];
    }
    /**
     * 在HTML中引用应该使用<img src='cid:xxx' />
     */
    function embed($src, $name=null){
        $this->attachments[]=[$name ?: basename($src), $src, 'inline'];
    }

    function send(){
        if ($this->scheme=='ssl' && !extension_loaded('openssl')){
            throw new \Exception("OpenSSL required for secure connection");
        }

        $headers=$this->headers;

        // 检查From, To, Subject必须存在
        $headers['Subject']=$this->subject;
        $headers['From']=$this->sender;
        $headers['To']=implode(";", $this->recipients['to']);
        if(isset($this->recipients['cc'])){
            $headers['Cc']=implode(";", $this->recipients['cc']);
        }

        // 自动检测是否HTML
        $mimetype=strlen($this->body) > strlen(strip_tags($this->body)) ? "text/html" : "text/plain";
        $headers['Content-Type']="$mimetype; charset=UTF-8";

        // 准备邮件内容
        $data="";
        if ($this->attachments) {
            // Replace Content-Type
            $hash=uniqid(NULL,TRUE);
            $type=$headers['Content-Type'];
            $headers['Content-Type']="multipart/mixed; boundary=\"$hash\"";

            $data ='This is a multi-part message in MIME format'.self::EOL;
            $data.=self::EOL;

            // TODO: 同时支持text/plain和text/html的multipart/alternative，有必要么，现在还有不识别HTML的客户端吗
            $data.='--'.$hash.self::EOL;
            $data.='Content-Type: '.$type.self::EOL;
            $data.=self::EOL;
            $data.=$this->body.self::EOL;

            foreach($this->attachments as list($name, $path, $type)) {
                $data.='--'.$hash.self::EOL;
                $data.='Content-Type: application/octet-stream'.self::EOL;
                $data.='Content-Transfer-Encoding: base64'.self::EOL;
                $data.="Content-Disposition: $type; filename=\"$name\"".self::EOL;
                if($type=='inline'){
                    $data.="Content-ID: <$name>".self::EOL;
                }
                $data.=self::EOL;
                $data.=chunk_split(base64_encode(file_get_contents($path))).self::EOL;
            }
            $data.=self::EOL;

            $data.='--'.$hash.'--'.self::EOL;
        }
        else {
            $data =$this->body.self::EOL;
        }

        $header="";
        foreach ($headers as $key=>$val){
            $header.=$key.': '.$val.self::EOL;
        }
        $header.=self::EOL;


        $socket=&$this->socket;
        $socket=fsockopen(($this->scheme ? 'ssl://' : '').$this->server, $this->port);
        if(!$socket){
            throw new \Exception("Connection $this->server:$this->port failed");
        }

        stream_set_blocking($socket, TRUE);

        // 接收Server申明
        $this->dialog(null);

        // 登录认证
        $reply=$this->dialog('EHLO '.$this->hostname);
        if (strtolower($this->scheme)=='tls') {
            $this->dialog('STARTTLS');
            stream_socket_enable_crypto($socket,TRUE,STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $reply=$this->dialog('EHLO '.$this->hostname);
            if (preg_match('/8BITMIME/',$reply))
                $headers['Content-Transfer-Encoding']='8bit';
            else {
                $headers['Content-Transfer-Encoding']='quoted-printable';
                $message=quoted_printable_encode($message);
            }
        }
        if ($this->username && $this->password && preg_match('/AUTH/',$reply)) {
            // Authenticate
            $this->dialog('AUTH LOGIN');
            $this->dialog(base64_encode($this->username));
            $this->dialog(base64_encode($this->password));
        }

        // Start message dialog
        $this->dialog('MAIL FROM: '.$this->sender);

        foreach(array_merge($this->recipients['to'], @$this->recipients['cc'] ?: [], @$this->recipients['bcc'] ?: []) as $r){
            $this->dialog('RCPT TO: '.$r);
        }

        $this->dialog('DATA');
        $this->dialog($header.$data.self::EOL.".");

        $this->dialog('QUIT');

        // TODO: 若keepalive不用关闭
        if ($socket) fclose($socket);
        return TRUE;
    }

    protected function dialog($cmd=NULL,$log=TRUE) {
        $socket=&$this->socket;

        $this->debug(" >> $cmd");

        if (!is_null($cmd)) fputs($socket,$cmd."\r\n");

        $reply='';
        while (
            !feof($socket) &&
            ($info=stream_get_meta_data($socket) && !@$info['timed_out']) &&
            $str=fgets($socket,4096)
        ) {
            $reply.=$str;
            if (preg_match('/(?:^|\n)\d{3} .+?\r\n/s',$reply)) break;
        }

        $this->debug(" << $reply");

        return $reply;
    }

    protected function debug($message){
        if(defined('DEBUG') && DEBUG){
            error_log($message);
        }
    }
}
