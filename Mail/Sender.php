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

        $this->dns_server='8.8.8.8';

        // 确定本机HOST NAME
        $this->hostname=defined('APP_HOST') ? APP_HOST : @$_SERVER['SERVER_NAME'] ?: gethostname() ?: "localhost";

        $this->sender=new Address($from ?: $this->username);
        $this->recipients=[
            'to'=>[], 'cc'=>[], 'bcc'=>[], 'reply'=>[]
        ];

        $this->headers=[];
        $this->attachments=[];

        $this->socket=null;
    }

    /**
     * 添加收件人
     */
    function add_recipient($email, $name=null, $rcpt='to'){
    	$valid_rcpts=['to', 'cc', 'bcc', 'reply'];
        if(!in_array($rcpt, $valid_rcpts)) user_error("Invalid rcpt type, valid values: ".implode(", ", $valid_rcpts));

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
        if(count($this->recipients['cc'])>0){
            $headers['Cc']=implode(";", $this->recipients['cc']);
        }
        if(count($this->recipients['reply'])>0){
            $headers['Reply-To']=implode(";", $this->recipients['reply']);
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

        $recipients=array_merge($this->recipients['to'], $this->recipients['cc'], $this->recipients['bcc']);

        if(!$this->server && !$this->port){
            // 尝试直接登录对方邮件服务器
            $scheme='';
            $port=25;
            $sent=0;
            foreach($recipients as $r){
                $server=$this->resolve_mx($r->domain);
                if(!$server){
                    user_error("Can't resolve MX for domain: $r->domain");
                }
                else{
                    $success=$this->send_smtp($server, $port, $scheme, $header, $data, [$r]);
                    if($success) $sent++;
                }
            }

            return $sent;
        }
        else{
            // 通过MTA代发
            return $this->send_smtp($this->server, $this->port, $this->scheme, $header, $data, $recipients);
        }
    }

    function send_smtp($server, $port, $scheme, $header, $data, $recipients){
        $socket=&$this->socket;
        $socket=fsockopen(($scheme ? 'ssl://' : '').$server, $port);
        if(!$socket){
            user_error("Connection $this->server:$this->port failed");
            return false;
        }

        stream_set_blocking($socket, TRUE);

        // 跳过Server欢迎信息
        $this->dialog(null);

        // 登录认证
        $reply=$this->dialog('EHLO '.$this->hostname);
        if (strtolower($scheme)=='tls') {
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

        foreach($recipients as $r){
            $this->dialog('RCPT TO: '.$r);
        }

        $this->dialog('DATA');
        $ret=$this->dialog($header.$data.self::EOL.".");

        $this->dialog('QUIT');

        if ($socket) fclose($socket);

        return $ret;
    }

    function resolve_mx($domain){
        $ret=getmxrr($domain, $hosts, $weights);
        if($ret && count($hosts)){
            return $hosts[0];
        }
        else
            return null;
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
