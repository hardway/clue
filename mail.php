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

            if(class_exists('PHPMailer')){  // TODO集成简化版PHPMailer
                $this->mailer=new \PHPMailer();

                $this->mailer->IsSMTP();
                $this->mailer->Host=$this->options['host'];
                $this->mailer->Port=$this->options['port'];
                $this->mailer->CharSet=$this->options['charset'];

                if(@$this->options['hostname']){
                	$this->mailer->Hostname=$this->options['hostname'];
                }
                if(!empty($this->options['username'])){
                    $this->mailer->SMTPAuth=true;
                    $this->mailer->Username=$this->options['username'];
                    $this->mailer->Password=$this->options['password'];
                }
                if(!empty($this->options['secure'])){
                    $this->mailer->SMTPSecure=$this->options['secure'];
                }
            }
        }

        function send($subject, $html, $to, $from=null, $reply=null){
        	// 使用PHPMailer
            if($this->mailer){
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

                return $ret ?: $this->mailer->ErrorInfo;
            }
            else{
            	$sender=new Mail\Sender(
            		$this->options['host'], $this->options['port'],
            		$this->options['username'], $this->options['password'],
            		$from
            	);

                if(@$this->options['hostname']){
                	$sender->hostname=$this->options['hostname'];
                }

            	$sender->add_recipient($to);

            	if($reply){
            		$sender->add_recipient($reply, null, 'reply');
				}

            	$sender->subject=$subject;
            	$sender->body=$html;

            	$sender->send();
            }
        }
    }
?>
