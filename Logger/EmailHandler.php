<?php
namespace Clue\Logger;

// TODO: 目前仅适合记录Summary日志，需要更多测试

class EmailHandler extends SyslogHandler{
    protected $font_style="font-family:consolas, monospace";

    function __construct($recipients, $mailer=null){
        $this->recipients=is_array($recipients) ? $recipients : array($recipients);
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
