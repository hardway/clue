<?php
namespace Clue\Logger;

// TODO: 目前仅适合记录Summary日志，需要更多测试

class EmailHandler extends SyslogHandler{
    protected $font_style="font-family:consolas, monospace";
    protected $recipients;
    protected $mailer;

    function __construct($recipients, $mailer=null){
        // 支持 'a@x.com,b@y.com' 这种逗号/分号分隔的字符串（与 Clue\Mail 的其它接口保持一致）
        if(!is_array($recipients)) $recipients=preg_split('/[,;]/', (string)$recipients);
        $this->recipients=array_values(array_filter(array_map('trim', $recipients), 'strlen'));
        $this->mailer=$mailer ?: new \Clue\Mail\Sender('127.0.0.1', 25);

        foreach($this->recipients as $rcpt){
            $this->mailer->add_recipient($rcpt);
        }
    }

    function write($data){
        $content=$this->format($data);
        $lines=explode("\n", $content);

        $this->mailer->subject=$lines[0];
        $this->mailer->body="<pre style='$this->font_style'>".$content."</pre>";

        $this->mailer->send();
    }
}
