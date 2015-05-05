<?php
/**
 * Credit: https://github.com/barbushin/php-imap
 */
namespace Clue\Mail;

if(!extension_loaded("imap")) exit("Extension imap required.");

class Fetcher{
    function __construct($server, $port, $username, $password, $spec=null){
        $this->server_encoding='utf-8';
        $this->imap_folder="INBOX";

        $default_spec=[
            110=>'/pop3/novalidate-cert',
            143=>'/imap/novalidate-cert',
            993=>'/imap/ssl/novalidate-cert',
            995=>'/pop3/ssl/novalidate-cert'
        ];
        $spec=$spec ?: $default_spec[$port];

        $this->imap_server="{"."$server:$port".$spec."}";
        $this->imap_options=CL_EXPUNGE;

        $this->stream=@imap_open("$this->imap_server$this->imap_folder", $username, $password, OP_HALFOPEN | $this->imap_options, 1);
        $errors=imap_errors();

        if(!$this->stream){
            throw new \Exception("Can't connect: ".$errors[count($errors)-1]);
        }

        $this->attachment_dir="/tmp/".uniqid(null, true);
        if(!is_dir($this->attachment_dir)) mkdir($this->attachment_dir, 0775, true);
    }

    function __destruct(){
        $this->close();

        // 删除临时附件
        \Clue\Tool::remove_directory($this->attachment_dir);
    }

    function close(){
        if($this->stream && is_resource($this->stream)){
            imap_expunge($this->stream);
            imap_close($this->stream, CL_EXPUNGE);
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

    function use_folder($folder){
        $success=@imap_reopen($this->stream, "$this->imap_server$folder", $this->imap_options);

        if($success){
            $this->imap_folder=$folder;
        }

        return $success;
    }

    function status($folder=null){
        return imap_status($this->stream, "$this->imap_server".($folder?:$this->imap_folder), SA_ALL);
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

    /**
     * Gets mails ids sorted by some criteria
     *
     * Criteria can be one (and only one) of the following constants:
     *  SORTDATE - mail Date
     *  SORTARRIVAL - arrival date (default)
     *  SORTFROM - mailbox in first From address
     *  SORTSUBJECT - mail subject
     *  SORTTO - mailbox in first To address
     *  SORTCC - mailbox in first cc address
     *  SORTSIZE - size of mail in octets
     *
     * @param array $mails 可以使mail header数组，或者uid数组
     * @param int $criteria
     * @param bool $reverse
     * @return array Mails ids
     */
    function sort(&$mails, $criteria = SORTARRIVAL, $reverse = true) {
        $sorts=imap_sort($this->stream, $criteria, $reverse, SE_UID);

        if(is_numeric($mails[0])){
            return array_values(array_intersect($sorts, $mails));
        }
        else{
            $sorts=array_combine(array_values($sorts), array_keys($sorts));
            usort($mails, function($a, $b) use($sorts){return $sorts[$b['uid']] - $sorts[$a['uid']];});
            return $mails;
        }
    }

    function delete_mail($ids){
        if(!is_array($ids)) $ids=[$ids];

        foreach($ids as $id){
            // imap_setflag_full($this->stream, $id, '\\Deleted', ST_UID);
            imap_delete($this->stream, $id, FT_UID);
        }
    }

    function move_mail($ids, $folder){
        if(!is_array($ids)) $ids=[$ids];

        foreach($ids as $id){
            imap_mail_move($stream, $id, $folder, CP_UID);
        }
    }

    function fetch_header($ids, $id_type='uid'){
        $single=false;   // 返回数组

        if(!is_array($ids)){
            $single=true;
            $ids=[$ids];
        }

        $mails = imap_fetch_overview($this->stream, implode(',', $ids), $id_type=='uid' ? FT_UID : null);
        $mails=array_map(function($m){
            $m->subject=$this->decode_mime_string($m->subject, $this->server_encoding);
            $m->from=$this->decode_mime_string($m->from, $this->server_encoding);
            $m->to=$this->decode_mime_string($m->to, $this->server_encoding);
            $m->id=$m->uid;

            return (array)$m;
        }, $mails);

        return $single ? $mails[0] : $mails;
    }

    function fetch_mail($id, $id_type='uid'){
        $head = imap_rfc822_parse_headers(imap_fetchheader($this->stream, $id, $id_type=='uid' ? FT_UID : null));

        if(!$head) throw new Exception("Message $id failed to fetch");

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

        $mailStructure = imap_fetchstructure($this->stream, $id, $id_type=='uid' ? FT_UID : null);

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

            $attach=[];
            $attach['id']=$attachmentId;
            $attach['name']=$fileName;
            $attach['path']=$this->attachment_dir."/".$mail['id'] . '_' . $attachmentId . '_' . basename($fileName);
            file_put_contents($attach['path'], $data);

            $mail['attachments'][]=$attach;
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
        if(!is_array($ids)) $ids=[$ids];

        return imap_setflag_full($this->stream, implode(',', $ids), $flag, ST_UID);
    }

    function mark_read($ids){ return $this->mark($ids, '\\Seen'); }
    function mark_important($ids){ return $this->mark($ids, '\\Flagged'); }

    function unmark($ids, $flag){
        if(!is_array($ids)) $ids=[$ids];

        return imap_clearflag_full($this->stream, implode(',', $ids), $flag, ST_UID);
    }

    function unmark_read($ids){ return $this->unmark($ids, '\\Seen'); }
    function unmark_important($ids){ return $this->unmark($ids, '\\Flagged'); }


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
