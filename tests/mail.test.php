<?php
require_once 'clue/stub.php';

require_once dirname(__DIR__).'/stub.php';
define("DEBUG", 1);

class Test_Mail_Manual extends PHPUnit_Framework_TestCase{
    protected function setUp(){
    }

    function test_mx_dns(){
        $s=new Clue\Mail\Sender();
        $this->assertTrue(!!preg_match('/gmail-smtp-in.l.google.com/', $s->resolve_mx("gmail.com")));
        $this->assertEquals(null, $s->resolve_mx("never-existed.com"));
    }

    function test_parse_email(){
        $a=new Clue\Mail\Address("Tiger Wu <tiger@domain.com>");
        $this->assertEquals("Tiger Wu", $a->name);
        $this->assertEquals("tiger@domain.com", $a->email);
        $this->assertEquals("domain.com", $a->domain);
        $this->assertEquals("tiger", $a->user);
        $this->assertEquals("Tiger Wu <tiger@domain.com>", (string)$a);

        $a=new Clue\Mail\Address("\"Domain.com\" <info@domain.com>");
        $this->assertEquals("Domain.com", $a->name);
        $this->assertEquals("info@domain.com", $a->email);
        $this->assertEquals("Domain.com <info@domain.com>", (string)$a);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessageRegExp "Can not authenticate to IMAP server:"
     */
    function test_login_error(){
        // $this->markTestSkipped();

        $f=new Clue\Mail\Fetcher('imap.gmail.com', 993, 'username', 'password');
    }
}
