<?php
require_once 'clue/stub.php';

require_once dirname(__DIR__).'/stub.php';
define("DEBUG", 1);

class Test_Mail extends PHPUnit_Framework_TestCase{
    protected function setUp(){
        $this->outgoing_server=@$_SERVER['smtp_out_server'] ?: "smtp.gmail.com";
        $this->outgoing_port=@$_SERVER['smtp_out_port'] ?: 465;
        $this->incoming_server=@$_SERVER['smtp_in_server'] ?: "imap.gmail.com";
        $this->incoming_port=@$_SERVER['smtp_in_port'] ?: 993;

        if(!isset($_SERVER['smtp_username']) || !isset($_SERVER['smtp_password'])){
            exit(error_log("Gmail username and password required (Environment: smtp_username, smtp_password)"));
        }
        $this->username=$_SERVER['smtp_username'];
        $this->password=$_SERVER['smtp_password'];

        $this->app=new Clue\Application(['config'=>null]);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessageRegExp "Can not authenticate to IMAP server:"
     */
    function test_login_error(){
        $this->markTestSkipped();

        $f=new Clue\Mail\Fetcher($this->incoming_server, $this->incoming_port, 'username', 'password');
        $mails=$f->search("ALL");
    }

    function test_fetch_mail(){
        $this->markTestSkipped();

        $f=new Clue\Mail\Fetcher($this->incoming_server, $this->incoming_port, $this->username, $this->password);

        $mails=$f->search("ALL");
        $this->assertTrue(count($mails)>0);

        $mail=$f->fetch_mail($mails[0]);
        $this->assertTrue(!!$mail['subject']);
        $this->assertTrue(!!$mail['html']);
    }

    function test_html_mail(){
        $this->markTestSkipped();

        $s=new Clue\Mail\Sender($this->outgoing_server, $this->outgoing_port, $this->username, $this->password, $this->username);

        $s->subject="[CLUE-AUTOTEST] send html email";
        $s->body="<h1>Title</h1><img src='cid:baidu.png' />";

        $s->add_recipient($this->username);
        $s->add_cc($this->username);
        $s->add_bcc("hou.danwu@gmail.com");

        $s->is_html();

        $s->attach("http://www.baidu.com/img/bdlogo.png", "baidu2.png");
        $s->embed("http://www.baidu.com/img/bdlogo.png", "baidu.png");

        $s->send();
    }

    function test_send_mail(){
        $this->markTestSkipped();

        $s=new Clue\Mail\Sender($this->outgoing_server, $this->outgoing_port, $this->username, $this->password);

        $s->sender=new Clue\Mail\Address($this->username);
        $s->subject="[CLUE-AUTOTEST] send new email";
        $s->body="This is body";

        $s->add_recipient($this->username);
        $s->add_cc($this->username);
        $s->add_bcc($this->username);

        $s->send();

        sleep(1);

        $f=new Clue\Mail\Fetcher($this->incoming_server, $this->incoming_port, $this->username, $this->password);
        $mails=$f->search("SUBJECT \"$s->subject\"");
        $this->assertEquals(1, count($mails));

        $mail=$f->fetch_mail($mails[0]);

        // 检查to, from, title, body都正确
        $this->assertEquals($this->username, $mail['from']->email);
        $this->assertEquals($this->username, $mail['to']->email);
        $this->assertEquals($this->subject, $mail['subject']);
        $this->assertEquals($this->body, $mail['body']);
        var_dump($mail);exit();
    }

    function test_remove_mail(){
        $this->markTestSkipped();

        $f=new Clue\Mail\Fetcher($this->incoming_server, $this->incoming_port, $this->username, $this->password);

        // 删除包含有[CLUE-AUTOTEST]的邮件
        $mails=$f->search("SUBJECT [CLUE-AUTOTEST]");

        $f->delete_mail($mails);
        $f->flush();

        $mails=$f->search("SUBJECT [CLUE-AUTOTEST]");
        $this->assertEmpty($mails);
    }

    function test_read_and_send(){
        $this->markTestSkipped();

        $f=new Clue\Mail\Fetcher($this->incoming_server, $this->incoming_port, $this->username, $this->password);

        // 所有邮件
        $mails=$f->search("SINCE \"2014-01-01\"");

        foreach($f->fetch_header($mails) as $h){
            // 找到一个有附件的或者最大的邮件
            if($h['size'] > 10*1024){
                // 重新发送给自己
                $m=$f->fetch_mail($h['uid']);

                $s=new Clue\Mail\Sender($this->outgoing_server, $this->outgoing_port, $this->username, $this->password);

                $s->sender=new Clue\Mail\Address($this->username);
                $s->subject=$m['subject'];
                $s->body=$m['html'];

                $s->add_recipient($this->username);

                foreach($m['attachments'] as $a){
                    $s->attach($a['path'], $a['name']);
                }

                $s->send();

                break;
            }
        }
    }
}
