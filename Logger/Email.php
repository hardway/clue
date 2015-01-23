<?php
namespace Clue\Logger;

class Email extends Syslog{
    function __construct($recipients, $mailer=null){
        $this->recipients=is_array($recipients) ? $recipients : array($recipients);
        $this->mailer=$mailer ?: new \Clue\Mail\Sender;

        foreach($this->recipients as $rcpt){
            $this->mailer->add_recipient($rcpt);
        }
    }

    function write($data){
        $this->mailer->subject=sprintf("%s | %s | %s", $data['time'], $data['level'], substr($data['message'], 0, 160));

        $body="";
        foreach($data as $k=>$v){
            $body.="<dt>".htmlspecialchars($k)."</dt><dd>".htmlspecialchars($v)."</dd>";
        }
        $this->mailer->body=$body;

        $this->mailer->send();
    }
}
