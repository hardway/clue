<?php
namespace Clue\Text{
    include_once dirname(__DIR__).'/vendor/parsedown.php';
    class ParseDown extends \ParseDown{
        static function format($text){
            static $instance=null;
            $instance=$instance ?: new self();
            return $instance->text($text);
        }

        protected $breaksEnabled=true;  // 默认打开自动换行

        protected function inlineLink($Excerpt){
            $link=parent::inlineLink($Excerpt);

            // 区分内外链接
            if(isset($link['element']) && $link['element']['name']=='a'){
                $href=$link['element']['attributes']['href'];

                // 外部链接将用新窗口打开
                if(preg_match('/:\/\//', $href)){
                    $link['element']['attributes']['class'].="external";
                    $link['element']['attributes']['target']='_blank';
                }
            }

            return $link;
        }
    }
}
