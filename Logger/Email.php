<?php
namespace Clue\Logger;

class Email extends Syslog{
    protected $option=[
        'backtrace'=>true,
        'context'=>true,
    ];

    protected $font_style="font-family:consolas, monospace";

    function __construct($recipients, $mailer=null){
        $this->recipients=is_array($recipients) ? $recipients : array($recipients);
        $this->mailer=$mailer ?: new \Clue\Mail\Sender('127.0.0.1', 25);

        foreach($this->recipients as $rcpt){
            $this->mailer->add_recipient($rcpt);
        }
    }

    function write($data){
        $this->mailer->subject=sprintf("%s %s %s",
            $data['timestamp'],
            substr($data['message'], 0, 160),
            '['.@$data['first_error'].'] ('.@$data['first_trace'].')'
        );

        $body="";

        if($this->option['backtrace'] && isset($data['backtrace'])){
            $body.="<dt>Backtrace</dt><dd><pre>".$this->format_backtrace($data['backtrace'])."</pre></dd>";
        }

        unset($data['backtrace']);

        foreach($data as $k=>$v){
            $body.="<dt>".htmlspecialchars(ucfirst($k))."</dt><dd><pre>".$this->format_var($v)."</pre></dd>";
        }
        $this->mailer->body="<div style='$this->font_style'>$body</div>";
        $this->mailer->body=str_replace('<pre', "<pre style='$this->font_style'", $this->mailer->body);


        $this->mailer->send();
    }
}
