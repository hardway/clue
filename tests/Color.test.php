<?php
include dirname(__DIR__).'/Color.php';
use Clue\Color;

class Test_Color extends PHPUnit_Framework_TestCase{
    const TEST_RGB_STEP=25; // 1, 5, 25

    function test_fingerprint_palette(){
        // 生成各种纯色图片
        $color_fill=[
            'FFFFFF'=>'00000000000000002',
            '000000'=>'20000000000000000',
            'FF0000'=>'00000200000000000',
            '00FF00'=>'00000000020000000',
            '0000FF'=>'00200000000000000',
        ];

        foreach($color_fill as $hex=>$fingerprint){
            $im=imagecreate(150, 150);
            list($r, $g, $b)=Color::hex2rgb($hex);
            imagefill($im, 1, 1, imagecolorallocate($im, $r, $g, $b));

            $this->assertEquals($fingerprint, Color::image_fingerprint($im));

            $palette=Color::image_palette($im, 3);
            $this->assertEquals(1, count($palette));
            $this->assertEquals(strtolower($hex), array_keys($palette)[0]);

            imagedestroy($im);
        }
    }

    function test_xyz_rgb(){
        for($r=0; $r<=255; $r+=self::TEST_RGB_STEP){
            for($g=0; $g<=255; $g+=self::TEST_RGB_STEP){
                for($b=0; $b<=255; $b+=self::TEST_RGB_STEP){
                    list($x, $y, $z)=Color::rgb2xyz($r, $g, $b);
                    list($R, $G, $B)=Color::xyz2rgb($x, $y, $z);

                    $this->assertTrue(0 >= abs($r+$g+$b - $R-$G-$B), "RGB($r, $g, $b) <==> RGB($R, $G, $B)");
                }
            }
        }
    }

    function test_hex_rgb(){
        for($r=0; $r<=255; $r+=self::TEST_RGB_STEP){
            for($g=0; $g<=255; $g+=self::TEST_RGB_STEP){
                for($b=0; $b<=255; $b+=self::TEST_RGB_STEP){
                    $hex=Color::rgb2hex($r, $g, $b);
                    list($R, $G, $B)=Color::hex2rgb($hex);

                    $this->assertEquals("$r, $g, $b", "$R, $G, $B", "RGB($r, $g, $b) <==> RGB($R, $G, $B)");
                }
            }
        }
    }

    function test_hsl_rgb(){
        for($r=0; $r<=255; $r+=self::TEST_RGB_STEP){
            for($g=0; $g<=255; $g+=self::TEST_RGB_STEP){
                for($b=0; $b<=255; $b+=self::TEST_RGB_STEP){
                    list($h, $s, $l)=Color::rgb2hsl($r, $g, $b);
                    list($R, $G, $B)=Color::hsl2rgb($h, $s, $l);

                    $this->assertEquals("$r, $g, $b", "$R, $G, $B", "RGB($r, $g, $b) <==> RGB($R, $G, $B)");
                }
            }
        }
    }
}
