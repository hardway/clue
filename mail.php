<?php  
    class Clue_Mail{
        public $subject;
        public $body;
        public $header;
        public $from;
        public $recipient;
        protected $attach;
        protected $boundary;
        
        function __construct(){
            $this->header=array();
            $this->attach=array();
            $this->boundary="<<CLUE-".md5(time()).">>";
        }
        
        function attach($path, $name=null){
            if(!is_file($path)) return false;
            
            if(empty($name)) $name=basename($path);
            $type='application/octet-stream';
            
            $this->attach[]="Content-Type:$type name=\"$name\"";
            $this->attach[]= "Content-Transfer-Encoding: base64"; 
            $this->attach[]= "Content-Disposition: attachment; filename=\"$name\"\r\n"; 
            $this->attach[]= chunk_split(base64_encode(file_get_contents($path))); 
            $this->attach[]="--".$this->boundary;
        }
        
        function send(){
            // TODO: plain text email
            $recipient=is_array($this->recipient) ? implode(", ", $this->recipient) : $this->recipient;

            if(!empty($reply)) $mail->header[]="From: $reply";
            
            $header =is_array($this->header) ? implode("\r\n", $this->header) : $this->header;
            $header.="\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"".$this->boundary."\"\r\n";
            
            $message ="--$this->boundary\r\n";
            $message.="Content-Type: text/html; charset=\"UTF-8\"\r\n";
            $message.="Content-Transfer-Encoding: quoted-printable\r\n\r\n";            
            $message.=$this->body;
            $message.="\r\n--$this->boundary\r\n";
            $message.=implode("\r\n", $this->attach);
            
            // TODO: error check and logging
            $ret=mail($recipient, $this->subject, $message, $header);
            
            return $ret;
        }

        function send_to($recipients, $undisclosed=true){        
            $this->recipient=is_array($recipients) ? $recipients : array($recipients);
            if($undisclosed){
                $this->header[]='To: Undisclosed Recipients';
                $this->header[]='BCC: '.implode(', ', $this->recipient);
                $this->recipient=null;
            }
            $this->send();
        }
        
        /* Deprecated */
        static function broadcast($recipient, $subject, $body, $reply='noreply', $undisclosed=true){
            $mail=new Clue_Mail();
            
            $mail->from=$reply;
            $mail->subject=$subject;
            $mail->body=$body;
            
            return $mail->send_to($recipient, $undisclosed);
        }
    }
?>
