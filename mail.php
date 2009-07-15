<?php  
	class Clue_Mail{
		public $subject;
		public $body;
		public $header;
		public $recipient;
		
		function send(){
			$recipient=is_array($this->recipient) ? implode(", ", $this->recipient) : $this->recipient;
			$header=is_array($this->header) ? implode("\r\n", $this->header) : $this->header;
			
			// TODO: error check and logging
			$ret=mail($recipient, $this->subject, $this->body, $header);
		}
		
		static function broadcast($recipient, $subject, $body, $reply='noreply', $undisclosed=true){
			$mail=new Clue_Mail();
			
			if(!empty($reply)) $mail->header[]="From: $reply";
			$mail->recipient=is_array($recipient) ? $recipient : array($recipient);
			$mail->subject=$subject;
			$mail->body=$body;
			
			if($undisclosed){
				$mail->header[]='To: Undisclosed Recipients';
				$mail->header[]='BCC: '.implode(', ', $mail->recipient);
				$mail->recipient=null;
			}
			
			$mail->send();
		}
		
		static function broadcast_with_attachment(){
			// TODO:
		}
	}
?>
