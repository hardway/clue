<?php
namespace Clue\Logger;

class WebHandler extends SyslogHandler{
    function format_backtrace($trace){
        return htmlspecialchars(parent::format_backtrace($trace));
    }

    function format($item){
        if(isset($item['location'])){
            $error_position=$item['location'];
        }
        elseif(isset($item['backtrace'][0]['file']))
            $error_position=$item['backtrace'][0]['file'].':'.$item['backtrace'][0]['line'];
        else
            $error_position=$item['backtrace'][0]['class'].$item['backtrace'][0]['type'].$item['backtrace'][0]['function'].'('.')';

        $diag=null;

        if(is_array($item['backtrace'])){
            $diag.="<dt>Backtrace</dt><pre class='clue-log-trace'>";
            $diag.=$this->format_backtrace($item['backtrace']);
            $diag.="</pre>";
            unset($item['backtrace']);
        }

        $type=@$item['type'];
        $message=$item['message'];

        foreach(['backtrace', 'location', 'channel', 'type', 'level', 'message', 'timestamp'] as $field) unset($item[$field]);

        if($item){
            $diag.="<dt>Context</dt><pre class='clue-log-context'>".$this->format_var($item)."</pre>";
            unset($item['context']);
        }

        $uid=uniqid();
        $html="
            <div id='clue-log-$uid' class='clue-log clue-log-".strtolower($type)." ".(strlen($diag)>0 ? 'clue-log-more' : '')."'>
                <div class='clue-log-subject' onclick='clue_log_toggle(\"clue-log-diagnose-$uid\");'>
                    <div style='float:right;'>$error_position</div>
                    <strong>{$type}</strong>: <i>{$message}</i>
                </div>
        ";

        if($diag){
            $html.="<div class='clue-log-diagnose' style='display:none;' id='clue-log-diagnose-$uid'>";
            $html.=$diag;
            $html.="</div>";
        }

        $html.="</div><script>clue_log_visible('clue-log-$uid');</script>";

        return $html;
    }

    function write_html_prerequisite(){
        static $css_script_written=false;

        if(!$css_script_written){
            echo "
                <style>
                    .clue-log {border:none; font:12px consolas; border-radius:0}
                    .clue-log i {white-space:pre; font-style:normal;}
                    .clue-log dt {font-weight:bold;}
                    .clue-log pre {background:inherit; border-radius:0; border:0; font:12px consolas;}
                    .clue-log .clue-log-subject {padding:1em; background:#911; color:#fff; border-bottom:1px solid #CCC; padding-left:1em;}

                    .clue-log-more .clue-log-subject {cursor:pointer; }
                    .clue-log-more .clue-log-subject::before { content: '+'; margin-left:-.75em;}
                    .clue-log-more:hover {z-index:99; box-shadow:0px 0px 10px 3px #966;}
                    .clue-log-more:hover .clue-log-subject {background:#600; color:#FFF;}
                    .clue-log-more:hover .clue-log-diagnose {background:#FFF;}

                    .clue-log-info .clue-log-subject,
                    .clue-log-notice .clue-log-subject
                    {
                        background:#D9EDF7; color:#000;
                    }
                    .clue-log-info:hover .clue-log-subject,
                    .clue-log-notice:hover .clue-log-subject
                    {
                        background:navy; color:#FFF;
                    }

                    .clue-log-debug .clue-log-subject,
                    .clue-log-warning .clue-log-subject
                    {
                        background:#FCF8E3; color:#000;
                    }
                    .clue-log-debug:hover .clue-log-subject,
                    .clue-log-debug:hover .clue-log-subject
                    {
                        background:orange; color:#FFF;
                    }

                    .clue-log-diagnose {background:#EEE; margin:0; padding:1em; border:none;}
                </style>
                <script>
                    function clue_log_toggle(id){
                        var el = document.getElementById(id);
                        if(!el) return;
                        el.style.display = el.style.display === 'none' ? '' : 'none';
                    }
                    function clue_log_visible(id){
                        var el = document.getElementById(id);
                        if(!el) return;
                        document.body.appendChild(el);
                    }
                </script>
            ";
            $css_script_written=true;
        }
    }

    function write($data){
        $this->write_html_prerequisite();

        if(!isset($data['errors'])){
            $data['type']=strtoupper($data['type'] ?: $data['level']);
            echo $this->format($data);
        }
        elseif(isset($data['errors'])){
            // 针对Guard::summarize()返回的全部error
            $errors=$data['errors']; unset($data['errors']);
            echo $this->format($data);

            foreach($errors as $err){
                echo $this->format($err);
            }
        }
    }
}
