<?php
    namespace Clue;
    class Mail{
        public $mailer;

        function __construct($options=array()){
            $this->options=array_merge(array(
                'host'=>'127.0.0.1',
                'port'=>25,
                'charset'=>'UTF-8'
            ), $options);

            $this->mailer=new \PHPMailer();
            $this->mailer->IsSMTP();
            $this->mailer->Host=$this->options['host'];
            $this->mailer->Port=$this->options['port'];
            $this->mailer->CharSet=$this->options['charset'];

            if(!empty($this->options['username'])){
                $this->mailer->SMTPAuth=true;
                $this->mailer->Username=$this->options['username'];
                $this->mailer->Password=$this->options['password'];
            }
            if(!empty($this->options['secure'])){
                $this->mailer->SMTPSecure=$this->options['secure'];
            }
        }

        function send($subject, $html, $to, $from=null, $reply=null){
            $this->mailer->Subject=$subject;
            $this->mailer->MsgHTML($html);
            $this->mailer->ClearAllRecipients();

            if(!empty($from)){
                $this->mailer->SetFrom($from);
            }

            if(!empty($reply))
                $this->mailer->AddReplyTo($reply);

            $this->mailer->AddAddress($to);

            $ret=$this->mailer->Send();

            return $ret;
        }
    }
?>
