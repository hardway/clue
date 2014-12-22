<?php
/**
 * Credit: https://github.com/barbushin/php-imap
 */
namespace Clue\Mail;

class Fetcher{
    function __construct($server, $port, $username, $password){
        $this->server_encoding='utf-8';
        $this->imap_folder="inbox";
        $this->imap_server="{"."$server:$port/imap/ssl"."}";

        $this->stream=@imap_open("$this->imap_server$this->imap_folder", $username, $password, null, 1);
        $errors=imap_errors();

        if(!$this->stream){
            throw new \Exception("Can't connect: ".$errors[count($errors)-1]);
        }
    }

    function __destruct(){
        if($this->stream && is_resource($this->stream)){
            imap_close($this->stream);
            $this->stream=null;
        }
    }

    function list_folders(){
        $folders=imap_list($this->stream, $this->imap_server, '*');
        foreach($folders as $idx=>$path){
            $folders[$idx]=str_replace($this->imap_server, '', imap_utf7_decode($path));
        }

        return $folders;
    }

    /**
     * This function performs a search on the mailbox currently opened in the given IMAP stream.
     * For example, to match all unanswered mails sent by Mom, you'd use: "UNANSWERED FROM mom".
     * Searches appear to be case insensitive. This list of criteria is from a reading of the UW
     * c-client source code and may be incomplete or inaccurate (see also RFC2060, section 6.4.4).
     *
     * @param string $criteria String, delimited by spaces, in which the following keywords are allowed. Any multi-word arguments (e.g. FROM "joey smith") must be quoted. Results will match all criteria entries.
     *    ALL - return all mails matching the rest of the criteria
     *    ANSWERED - match mails with the \\ANSWERED flag set
     *    BCC "string" - match mails with "string" in the Bcc: field
     *    BEFORE "date" - match mails with Date: before "date"
     *    BODY "string" - match mails with "string" in the body of the mail
     *    CC "string" - match mails with "string" in the Cc: field
     *    DELETED - match deleted mails
     *    FLAGGED - match mails with the \\FLAGGED (sometimes referred to as Important or Urgent) flag set
     *    FROM "string" - match mails with "string" in the From: field
     *    KEYWORD "string" - match mails with "string" as a keyword
     *    NEW - match new mails
     *    OLD - match old mails
     *    ON "date" - match mails with Date: matching "date"
     *    RECENT - match mails with the \\RECENT flag set
     *    SEEN - match mails that have been read (the \\SEEN flag is set)
     *    SINCE "date" - match mails with Date: after "date"
     *    SUBJECT "string" - match mails with "string" in the Subject:
     *    TEXT "string" - match mails with text "string"
     *    TO "string" - match mails with "string" in the To:
     *    UNANSWERED - match mails that have not been answered
     *    UNDELETED - match mails that are not deleted
     *    UNFLAGGED - match mails that are not flagged
     *    UNKEYWORD "string" - match mails that do not have the keyword "string"
     *    UNSEEN - match mails which have not been read yet
     *
     * @return array Mails ids
     */
    function search($criteria = 'ALL') {
        $ids = imap_search($this->stream, $criteria, SE_UID, $this->server_encoding);
        return $ids ?: array();
    }

    function delete_mail($ids){
        if(!is_array($ids)) $ids=[$ids];

        foreach($ids as $id){
            imap_delete($this->stream, $id, FT_UID);
        }
    }

    function move_mail($ids, $folder){
        if(!is_array($ids)) $ids=[$ids];

        foreach($ids as $id){
            imap_mail_move($stream, $id, $folder, CP_UID);
        }

        $this->flush();
    }

    function flush(){
        return imap_expunge($this->stream);
    }

    function fetch_header($ids){
        if(!is_array($ids)) $ids=[$ids];

        $mails = imap_fetch_overview($this->stream, implode(',', $ids), FT_UID);
        array_walk($mails, function($m){
            $m->subject=$this->decode_mime_string($m->subject, $this->server_encoding);
            $m->from=$this->decode_mime_string($m->from, $this->server_encoding);
            $m->to=$this->decode_mime_string($m->to, $this->server_encoding);
        });

        return $mails;
    }

    function fetch_mail($id){
        $head = imap_rfc822_parse_headers(imap_fetchheader($this->stream, $id, FT_UID));

        $mail=[];
        $mail['id']=$id;
        $mail['date']=date('Y-m-d H:i:s', isset($head->date) ? strtotime($head->date) : time());
        $mail['subject']=isset($head->subject) ? $this->decode_mime_string($head->subject, $this->server_encoding) : null;
        $mail['from'] = new Address(
            strtolower($head->from[0]->mailbox . '@' . $head->from[0]->host),
            isset($head->from[0]->personal) ? $this->decode_mime_string($head->from[0]->personal, $this->server_encoding) : null
        );
        $mail['text']=$mail['html']=null;

        foreach(['to', 'cc', 'reply_to'] as $rcpt){
            if(isset($head->$rcpt)) {
                foreach($head->$rcpt as $r) {
                    if(!empty($r->mailbox) && !empty($r->host)) {
                        $mail[$rcpt][] = new Address(
                            strtolower($r->mailbox . '@' . $r->host),
                            isset($r->personal) ? $this->decode_mime_string($r->personal, $this->server_encoding) : null
                        );
                    }
                }
            }
        }

        $mailStructure = imap_fetchstructure($this->stream, $id, FT_UID);
        if(empty($mailStructure->parts)) {
            $this->initMailPart($mail, $mailStructure, 0);
        }
        else {
            foreach($mailStructure->parts as $partNum => $partStructure) {
                $this->initMailPart($mail, $partStructure, $partNum + 1);
            }
        }
        return $mail;
    }

    protected function initMailPart(&$mail, $partStructure, $partNum) {
        $data = $partNum ? imap_fetchbody($this->stream, $mail['id'], $partNum, FT_UID) : imap_body($this->stream, $mail['id'], FT_UID);
        if($partStructure->encoding == 1) {
            $data = imap_utf8($data);
        }
        elseif($partStructure->encoding == 2) {
            $data = imap_binary($data);
        }
        elseif($partStructure->encoding == 3) {
            $data = imap_base64($data);
        }
        elseif($partStructure->encoding == 4) {
            $data = imap_qprint($data);
        }

        $params = array();
        if(!empty($partStructure->parameters)) {
            foreach($partStructure->parameters as $param) {
                $params[strtolower($param->attribute)] = $param->value;
            }
        }

        if(!empty($partStructure->dparameters)) {
            foreach($partStructure->dparameters as $param) {
                $paramName = strtolower(preg_match('~^(.*?)\*~', $param->attribute, $matches) ? $matches[1] : $param->attribute);
                if(isset($params[$paramName])) {
                    $params[$paramName] .= $param->value;
                }
                else {
                    $params[$paramName] = $param->value;
                }
            }
        }
        if(!empty($params['charset'])) {
            $data = iconv(strtoupper($params['charset']), $this->server_encoding . '//IGNORE', $data);
        }
        // attachments
        $attachmentId = $partStructure->ifid
            ? trim($partStructure->id, " <>")
            : (isset($params['filename']) || isset($params['name']) ? mt_rand() . mt_rand() : null);
        if($attachmentId) {
            if(empty($params['filename']) && empty($params['name'])) {
                $fileName = $attachmentId . '.' . strtolower($partStructure->subtype);
            }
            else {
                $fileName = !empty($params['filename']) ? $params['filename'] : $params['name'];
                $fileName = $this->decode_mime_string($fileName, $this->server_encoding);
                $fileName = $this->decode_rfc2231($fileName, $this->server_encoding);
            }
            var_dump($fileName);//exit();
            // $attachment = new IncomingMailAttachment();
            // $attachment->id = $attachmentId;
            // $attachment->name = $fileName;
            // if($this->attachmentsDir) {
            //     $replace = array(
            //         '/\s/' => '_',
            //         '/[^0-9a-zA-Z_\.]/' => '',
            //         '/_+/' => '_',
            //         '/(^_)|(_$)/' => '',
            //     );
            //     $fileSysName = preg_replace('~[\\\\/]~', '', $mail->id . '_' . $attachmentId . '_' . preg_replace(array_keys($replace), $replace, $fileName));
            //     $attachment->filePath = $this->attachmentsDir . DIRECTORY_SEPARATOR . $fileSysName;
            //     file_put_contents($attachment->filePath, $data);
            // }
            // $mail->addAttachment($attachment);
        }
        elseif($partStructure->type == 0 && $data) {
            if(strtolower($partStructure->subtype) == 'plain') {
                $mail['text'] .= $data;
            }
            else {
                $mail['html'] .= $data;
            }
        }
        elseif($partStructure->type == 2 && $data) {
            $mail['text'] .= trim($data);
        }
        if(!empty($partStructure->parts)) {
            foreach($partStructure->parts as $subPartNum => $subPartStructure) {
                if($partStructure->type == 2 && $partStructure->subtype == 'RFC822') {
                    $this->initMailPart($mail, $subPartStructure, $partNum);
                }
                else {
                    $this->initMailPart($mail, $subPartStructure, $partNum . '.' . ($subPartNum + 1));
                }
            }
        }
    }

    function mark($ids, $flag){

    }

    function unmark($ids, $flag){

    }

    protected function decode_mime_string($string, $charset = 'utf-8') {
        if($string==null) return null;

        $ret = '';
        $elements = imap_mime_header_decode($string);
        for($i = 0; $i < count($elements); $i++) {
            if($elements[$i]->charset == 'default') {
                $elements[$i]->charset = 'iso-8859-1';
            }
            $ret .= iconv(strtoupper($elements[$i]->charset), $charset . '//IGNORE', $elements[$i]->text);
        }
        return $ret;
    }

    protected function is_urlencoded($string) {
        $hasInvalidChars = preg_match( '#[^%a-zA-Z0-9\-_\.\+]#', $string );
        $hasEscapedChars = preg_match( '#%[a-zA-Z0-9]{2}#', $string );
        return !$hasInvalidChars && $hasEscapedChars;
    }

    /**
     * MIME Parameter Value and Encoded Word Extensions
     */
    protected function decode_rfc2231($string, $charset = 'utf-8') {
        if(preg_match("/^(.*?)'.*?'(.*?)$/", $string, $matches)) {
            $encoding = $matches[1];
            $data = $matches[2];
            if($this->is_urlencoded($data)) {
                $string = iconv(strtoupper($encoding), $charset . '//IGNORE', urldecode($data));
            }
        }
        return $string;
    }
}

class IncomingMail {
    public $id;
    public $date;
    public $subject;
    public $fromName;
    public $fromAddress;
    public $to = array();
    public $toString;
    public $cc = array();
    public $replyTo = array();
    public $textPlain;
    public $textHtml;
    /** @var IncomingMailAttachment[] */
    protected $attachments = array();
    public function addAttachment(IncomingMailAttachment $attachment) {
        $this->attachments[$attachment->id] = $attachment;
    }
    /**
     * @return IncomingMailAttachment[]
     */
    public function getAttachments() {
        return $this->attachments;
    }
    /**
     * Get array of internal HTML links placeholders
     * @return array attachmentId => link placeholder
     */
    public function getInternalLinksPlaceholders() {
        return preg_match_all('/=["\'](ci?d:(\w+))["\']/i', $this->textHtml, $matches) ? array_combine($matches[2], $matches[1]) : array();
    }
    public function replaceInternalLinks($baseUri) {
        $baseUri = rtrim($baseUri, '\\/') . '/';
        $fetchedHtml = $this->textHtml;
        foreach($this->getInternalLinksPlaceholders() as $attachmentId => $placeholder) {
            $fetchedHtml = str_replace($placeholder, $baseUri . basename($this->attachments[$attachmentId]->filePath), $fetchedHtml);
        }
        return $fetchedHtml;
    }
}
