<?php
/**
 * Credit: http://poss.sourceforge.net/email
 * Credit: http://fatfreeframework.com/
 */
namespace Clue\Mail;

class Sender{
    /**
     * @param $server 使用外部服务器或者内置MTA
     */
    function __construct($server=null, $port=null, $username=null, $password=null, $from=null){
        $this->headers=array(
            'MIME-Version'=>'1.0',
            'Content-Type'=>'text/plain; charset=UTF-8'
        );

        $this->server=$server;
        $this->port=$port;
        $this->scheme=$port!=25 ? 'ssl' : '';

        $this->username=$username;
        $this->password=$password;

        // 确定本机HOST NAME
        $this->hostname=@$_SERVER['SERVER_NAME'] ?: "localhost";

        $this->headers=[];

        $this->recipients=[];
        $this->sender=new Address($from ?: $this->username);
        $this->attachments=[];

        $this->log=null;
        $this->socket=null;
    }

    function add_recipient($email, $name=null){
        $this->recipients['to'][]=new Address($email, $name);
    }

    /**
    *   Send SMTP command and record server response
    *   @return string
    *   @param $cmd string
    *   @param $log bool
    **/
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

        if ($log) {
            $this->log.=$cmd."\n";
            $this->log.=str_replace("\r",'',$reply);
        }

        return $reply;
    }

    function debug($message){
        if(defined('DEBUG') && DEBUG){
            error_log($message);
        }
    }

    function send(){
        if ($this->scheme=='ssl' && !extension_loaded('openssl')){
            throw new \Exception("OpenSSL required for secure connection");
        }

        $headers=$this->headers;
        $headers['Subject']=$this->subject;
        $message=$this->body;

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

        // // Required headers
        // $reqd=array('From','To','Subject');
        // foreach ($reqd as $id)
        //     if (empty($headers[$id]))
        //         user_error(sprintf(self::E_Header,$id));
        $eol="\r\n";
        // $str='';
        // // Stringify headers
        // foreach ($headers as $key=>$val)
        //     if (!in_array($key,$reqd))
        //         $str.=$key.': '.$val.$eol;

        // Start message dialog
        $this->dialog('MAIL FROM: '.$this->sender);
        $this->dialog('RCPT TO: '.$this->recipients['to'][0]);
        // foreach ($fw->split($headers['To'].
        //     (isset($headers['Cc'])?(';'.$headers['Cc']):'').
        //     (isset($headers['Bcc'])?(';'.$headers['Bcc']):'')) as $dst)
        //     $this->dialog('RCPT TO: '.strstr($dst,'<'));

        $this->dialog('DATA');

        if ($this->attachments) {
            // Replace Content-Type
            $hash=uniqid(NULL,TRUE);
            $type=$headers['Content-Type'];
            $headers['Content-Type']='multipart/mixed; '.
                'boundary="'.$hash.'"';
            // Send mail headers
            $out='';
            foreach ($headers as $key=>$val)
                if ($key!='Bcc')
                    $out.=$key.': '.$val.$eol;
            $out.=$eol;
            $out.='This is a multi-part message in MIME format'.$eol;
            $out.=$eol;
            $out.='--'.$hash.$eol;
            $out.='Content-Type: '.$type.$eol;
            $out.=$eol;
            $out.=$message.$eol;
            foreach ($this->attachments as $attachment) {
                if (is_array($attachment)) {
                    list($alias, $file) = each($attachment);
                    $filename = $alias;
                    $attachment = $file;
                }
                else {
                    $filename = basename($attachment);
                }
                $out.='--'.$hash.$eol;
                $out.='Content-Type: application/octet-stream'.$eol;
                $out.='Content-Transfer-Encoding: base64'.$eol;
                $out.='Content-Disposition: attachment; '.
                    'filename="'.$filename.'"'.$eol;
                $out.=$eol;
                $out.=chunk_split(
                    base64_encode(file_get_contents($attachment))).$eol;
            }
            $out.=$eol;
            $out.='--'.$hash.'--'.$eol;
            $out.='.';
            $this->dialog($out,FALSE);
        }
        else {
            // Send mail headers
            $out='';
            foreach ($headers as $key=>$val){
                if ($key=='Bcc') continue;
                $out.=$key.': '.$val.$eol;
            }
            $out.=$eol;
            $out.=$message.$eol;
            $out.='.';
            // Send message
            $this->dialog($out);
        }

        $this->dialog('QUIT');

        // TODO: 若keepalive不用关闭
        if ($socket) fclose($socket);
        return TRUE;
    }
}
