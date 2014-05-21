<?php
    include "optionparser.php";

    class CLI_OptionParser_Test extends PHPUnit_Framework_TestCase{
        function setup(){
            $this->parser=new Clue\CLI\OptionParser();
            $this->parser->add_option(['name'=>'output', 'short'=>'-o', 'long'=>'--output OUTPUT-FILE', 'type'=>'string', 'help'=>'output file']);
            $this->parser->add_option(['name'=>'switch', 'short'=>'-s', 'long'=>'--switch', 'type'=>'flag', 'help'=>'switch/flag']);
            $this->parser->add_option(['name'=>'verbose', 'short'=>'-v', 'long'=>'--verbose', 'help'=>'verbose']);
            $this->parser->add_option(['name'=>'default', 'short'=>'-d', 'long'=>'--default', 'type'=>'string', 'default'=>'default', 'help'=>'switch/flag']);
            $this->parser->add_option(['name'=>'list', 'short'=>'-l', 'long'=>'--list A B C ...', 'type'=>'list', 'help'=>'input list files']);
            $this->parser->add_option(['name'=>'alpha', 'short'=>'-a', 'help'=>'For Usage Test Only']);
            $this->parser->add_option(['name'=>'beta', 'long'=>'--beta', 'help'=>'For Usage Test Only']);
        }

        function test_usage(){
            echo $this->parser->get_usage();
        }

        function test_default_value(){
            list($options, $args)=$this->parser->parse([]);

            $this->assertEquals($options['output'], null);
            $this->assertEquals($options['default'], 'default');
            $this->assertEquals($options['switch'], false);
            $this->assertEquals($options['list'], []);
        }

        function test_switch(){
            list($options, $args)=$this->parser->parse(['-s']);
            $this->assertEquals($options['switch'], true);
            $this->assertEquals($options['verbose'], false);

            list($options, $args)=$this->parser->parse(['--verbose']);
            $this->assertEquals($options['switch'], false);
            $this->assertEquals($options['verbose'], true);

            list($options, $args)=$this->parser->parse(['-s', '-v']);
            $this->assertEquals($options['switch'], true);
            $this->assertEquals($options['verbose'], true);

            list($options, $args)=$this->parser->parse(['-vs']);
            $this->assertEquals($options['switch'], true);
            $this->assertEquals($options['verbose'], true);

            list($options, $args)=$this->parser->parse(['-vvv']);
            $this->assertEquals($options['switch'], false);
            $this->assertEquals($options['verbose'], 3);
        }

        function test_string(){
            list($options, $args)=$this->parser->parse(['-o', 'output.txt']);
            $this->assertEquals($options['output'], 'output.txt');

            list($options, $args)=$this->parser->parse(['-ooutput.txt']);
            $this->assertEquals($options['output'], 'output.txt');

            list($options, $args)=$this->parser->parse(['--output=output.txt']);
            $this->assertEquals($options['output'], 'output.txt');
        }

        function test_list(){
            list($options, $args)=$this->parser->parse(["--list","a","b","c"]);
            $this->assertEquals($options['list'], ['a', 'b', 'c']);

            list($options, $args)=$this->parser->parse(["--list","a","-l","b"]);
            $this->assertEquals($options['list'], ['a', 'b']);

            list($options, $args)=$this->parser->parse(["--list","/var/log","~/*.txt"]);
            $this->assertEquals($options['list'], ['/var/log', '~/*.txt']);
        }

        function test_combination(){
            list($options, $args)=$this->parser->parse(["--switch", "--list", "abc"]);
            $this->assertEquals($options['switch'], true);
            $this->assertEquals($options['list'], ['abc']);
            $this->assertEquals($args, []);

            list($options, $args)=$this->parser->parse(["get","-s", "-o", "abc", "def"]);
            $this->assertEquals($options['switch'], true);
            $this->assertEquals($options['output'], 'abc');
            $this->assertEquals($args, ['get', 'def']);

            list($options, $args)=$this->parser->parse(["--output", "abc def", "get"]);
            $this->assertEquals($options['output'], 'abc def');
            $this->assertEquals($args, ['get']);

            list($options, $args)=$this->parser->parse(["--list", "a", "b", "c", "-s"]);
            $this->assertEquals($options['list'], ['a', 'b', 'c']);
            $this->assertEquals($options['switch'], true);
            $this->assertEquals($args, []);

            list($options, $args)=$this->parser->parse(["-vs", "get", "-o", "out.txt", "--list", "a", "b", "c", "-s", "next"]);
            $this->assertEquals($options['list'], ['a', 'b', 'c']);
            $this->assertEquals($options['output'], 'out.txt');
            $this->assertEquals($options['switch'], true);
            $this->assertEquals($options['verbose'], true);
            $this->assertEquals($args, ['get', 'next']);
        }
    }
?>
