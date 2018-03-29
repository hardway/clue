<?php
// REF: https://github.com/divinity76/slavehack_anticaptcha/blob/master/duplicate_image_finder.class.php
// 依赖php_gd扩展
namespace Clue;

class Color{
    /**
     * XYZ转Adobe RGB
     * REF: http://www.easyrgb.com/en/math.php
     */
    static function xyz2rgb($x, $y, $z){
        //X, Y and Z input refer to a D65/2° standard illuminant.
        //sR, sG and sB (standard RGB) output range = 0 ÷ 255

        $X = $x / 100;
        $Y = $y / 100;
        $Z = $z / 100;

        $R = $X *  3.2406 + $Y * -1.5372 + $Z * -0.4986;
        $G = $X * -0.9689 + $Y *  1.8758 + $Z *  0.0415;
        $B = $X *  0.0557 + $Y * -0.2040 + $Z *  1.0570;

        if ( $R > 0.0031308 )
            $R = 1.055 * pow( $R,  1 / 2.4 ) - 0.055;
        else
            $R = 12.92 * $R;

        if ( $G > 0.0031308 )
            $G = 1.055 * pow( $G,  1 / 2.4 ) - 0.055;
        else
            $G = 12.92 * $G;
        if ( $B > 0.0031308 )
            $B = 1.055 * pow( $B,  1 / 2.4 ) - 0.055;
        else
            $B = 12.92 * $B;

        return [round($R * 255), round($G * 255), round($B * 255)];
    }

    static function xyz2argb($x, $y, $z){
        //X, Y and Z input refer to a D65/2° standard illuminant.
        //aR, aG and aB (RGB Adobe 1998) output range = 0 ÷ 255

        $X = $x / 100;
        $Y = $y / 100;
        $Z = $z / 100;

        $R = max(0, $X *  2.04159 + $Y * -0.56501 + $Z * -0.34473);
        $G = max(0, $X * -0.96924 + $Y *  1.87597 + $Z *  0.03342);
        $B = max(0, $X *  0.01344 + $Y * -0.11836 + $Z *  1.34926);

        $R = pow($R, 1 / 2.19921875) ?: 0;
        $G = pow($G, 1 / 2.19921875) ?: 0;
        $B = pow($B, 1 / 2.19921875) ?: 0;

        return [round($R * 255), round($G * 255), round($B * 255)];
    }

    static function rgb2xyz($r, $g, $b){
        //sR, sG and sB (Standard RGB) input range = 0 ÷ 255
        //X, Y and Z output refer to a D65/2° standard illuminant.

        $R = ( $r / 255 );
        $G = ( $g / 255 );
        $B = ( $b / 255 );

        if ( $R > 0.04045 )
            $R = pow( ( $R + 0.055 ) / 1.055 , 2.4);
        else
            $R = $R / 12.92;

        if ( $G > 0.04045 )
            $G = pow( ( $G + 0.055 ) / 1.055 , 2.4);
        else
            $G = $G / 12.92;

        if ( $B > 0.04045 )
            $B = pow( ( $B + 0.055 ) / 1.055 , 2.4);
        else
            $B = $B / 12.92;

        $R = $R * 100;
        $G = $G * 100;
        $B = $B * 100;

        $X = $R * 0.4124 + $G * 0.3576 + $B * 0.1805;
        $Y = $R * 0.2126 + $G * 0.7152 + $B * 0.0722;
        $Z = $R * 0.0193 + $G * 0.1192 + $B * 0.9505;

        return [$X, $Y, $Z];
    }

    static function argb2xyz($r, $g, $b){
        //aR, aG and aB (RGB Adobe 1998) input range = 0 ÷ 255
        //X, Y and Z output refer to a D65/2° standard illuminant.

        $R = ( $r / 255 );
        $G = ( $g / 255 );
        $B = ( $b / 255 );

        $R = pow($R, 2.19921875);
        $G = pow($G, 2.19921875);
        $B = pow($B, 2.19921875);

        $R = $R * 100;
        $G = $G * 100;
        $B = $B * 100;

        $X = $R * 0.57667 + $G * 0.18556 + $B * 0.18823;
        $Y = $R * 0.29734 + $G * 0.62736 + $B * 0.07529;
        $Z = $R * 0.02703 + $G * 0.07069 + $B * 0.99134;

        return [round($X, 15), round($Y, 15), round($Z, 15)];
    }

    static function rgb2hsl($r, $g, $b){
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);

        $h=$s=$l = ($max + $min) / 2;

        if($max == $min){
            $h = $s = 0; // achromatic
        }else{
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch($max){
                case $r: $h = ($g - $b) / $d + ($g < $b ? 6 : 0); break;
                case $g: $h = ($b - $r) / $d + 2; break;
                case $b: $h = ($r - $g) / $d + 4; break;
            }
            $h /= 6;
        }

        return [$h, $s, $l];
    }

    private static function _hue2rgb($p, $q, $t){
        if($t < 0) $t += 1;
        if($t > 1) $t -= 1;
        if($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if($t < 1/2) return $q;
        if($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
        return $p;
    }

    static function hsl2rgb($h, $s, $l){
        if($s == 0){
            $r = $g = $b = $l; // achromatic
        }else{
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::_hue2rgb($p, $q, $h + 1/3);
            $g = self::_hue2rgb($p, $q, $h);
            $b = self::_hue2rgb($p, $q, $h - 1/3);
        }

        return [round($r * 255), round($g * 255), round($b * 255)];
    }

    static function hex2rgb($hex){
        $hex = trim($hex);

        if (empty($hex)) return false;
        if ($hex[0] == '#') $hex = substr($hex, 1);

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return array($r, $g, $b);
    }

    static function rgb2hex($r, $g, $b){
       $hex = str_pad(dechex($r), 2, "0", STR_PAD_LEFT);
       $hex .= str_pad(dechex($g), 2, "0", STR_PAD_LEFT);
       $hex .= str_pad(dechex($b), 2, "0", STR_PAD_LEFT);

       return (string) $hex;
    }

    /**
     * 生成灰度Histogram作为快速校验指纹
     *
     * @param $filename 文件路径或者gd图片
     * @param $thumb_width 生成缩略图宽度
     * @param $rate 灰阶采样频率（8,16,32），越小越密集，图片匹配难度越大
     * @param $sensitivity 直方图的高度范围(1-15)，越大越精确，图片匹配难度越大
     */
    static function image_fingerprint($filename, $thumb_width=150, $rate=16, $sensitivity=2){
        $image=is_resource($filename) ? $filename : @imagecreatefromjpeg($filename);
        if(!$image) return null;

        // Create thumbnail sized copy for fingerprinting
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = $thumb_width / $width;
        $newwidth = $thumb_width;
        $newheight = round($height * $ratio);
        $thumb = imagecreatetruecolor($newwidth, $newheight);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        // 转化为灰度并生成直方图
        $numpixels = $newwidth * $newheight;
        $histogram = array();
        for ($i = 0; $i < $newwidth; ++$i) {
            for ($j = 0; $j < $newheight; ++$j) {
                $c = imagecolorsforindex($thumb, imagecolorat($thumb, $i, $j));
                // 16阶灰度  30% Red, 59% Blue, 11% Green
                $greyscale = round(($c['red'] * 0.3) + ($c['green'] * 0.59) + ($c['blue'] * 0.11));
                $grey = round((1+$greyscale) / $rate) * $rate -1;
                @$histogram[$grey]++;
            }
        }

        // 归一化到(0-1)范围
        $max = max($histogram);
        $histstring = "";
        for ($i = -1; $i <= 255; $i = $i + $rate) {
            // $h = round((@$histogram[$i] / $max) * $sensitivity);
            $h=dechex(round((@$histogram[$i] / $max) * $sensitivity));
            $histstring .= $h;
        }

        // 清理资源
        if(!is_resource($filename)) imagedestroy($image);
        imagedestroy($thumb);

        // 16阶灰度直方图作为为指纹
        return $histstring;
        // return md5($histstring);
    }

    /**
     * 提取调色板
     *（内部通过XYZ匹配颜色）
     *
     * @param $filename 图片文件或GD资源
     * @param $limit 返回多少种颜色
     * @param $tw 缩略图宽度
     */
    static function image_palette($filename, $limit=16, $central_priority=0, $tw=16){
        $src=is_resource($filename) ? $filename : @imagecreatefromjpeg($filename);
        if(!$src) return null;

        $thumb=imagecreatetruecolor($tw, $tw);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $tw, $tw, imagesx($src), imagesy($src));

        $colors=[];
        $cx=$cy=$tw/2;

        for ($px = 0; $px < $tw; ++$px) {
            for ($py = 0; $py < $tw; ++$py) {
                $rgb = imagecolorsforindex($thumb, imagecolorat($thumb, $px, $py));
                $r=$rgb['red']; $g=$rgb['green']; $b=$rgb['blue'];

                // 越靠近中心，权重越大
                $pri=$central_priority ? $central_priority * sin(M_PI * (abs($py-$cy) + abs($px - $cx))/$tw) + 1.0 : 1;

                // 忽略极端黑色和白色
                // if(diff_manhattan([0, 0, 0], [$r, $g, $b]) < 10) continue;
                // if(diff_manhattan([255, 255, 255], [$r, $g, $b]) < 10) continue;

                // 换成lab-XYZ计算相似颜色
                list($x, $y, $z)=self::rgb2xyz($r, $g, $b);
                $key=round($x, 4).','.round($y, 4).','.round($z, 4);

                @$colors[$key]+=$pri;
            }
        }

        // 根据limit压缩调色板，而不是切断
        $dmap=[];

        // 按照 XYZ距离合并相似颜色，
        foreach(array_keys($colors) as $a){
            foreach(array_keys($colors) as $b){
                if($a==$b) continue;

                // Manhattan距离
                list($a1, $a2, $a3)=explode(",", $a);
                list($b1, $b2, $b3)=explode(",", $b);
                $dist=(abs($a1-$b1) + abs($a2-$b2) + abs($a3-$b3)) / 3;

                $dmap["$a:$b"]=$dist;
            }
        }

        arsort($dmap);
        $kmap=array_keys($dmap);

        // 直到满足$limit数量要求
        while($dmap && count($colors)>$limit){
            list($a, $b)=explode(":", array_pop($kmap));
            if(!isset($colors[$a]) || !isset($colors[$b])) continue;

            // 最相近的两种颜色合并在一起
            if($colors[$a] > $colors[$b]){
                $colors[$a]+=$colors[$b]; unset($colors[$b]);
            }
            else{
                $colors[$b]+=$colors[$a]; unset($colors[$a]);
            }
        }

        // 按照出现数量排序
        arsort($colors);

        // 按照 {HEX: pcnt%} 形式返回
        $palette=[];
        $total=array_sum($colors);
        foreach($colors as $k=>$cnt){
            // 转换RGB
            list($x, $y, $z)=explode(",", $k);
            list($r, $g, $b)=self::xyz2rgb($x, $y, $z);
            $key=self::rgb2hex(round($r), round($g), round($b));
            // 归一化
            $palette[$key]=round($cnt/$total, 4);
        }

        // 清理资源
        if(!is_resource($filename)) imagedestroy($src);

        return $palette;
    }

    /**
     * 比较两个图片在像素颜色上的相似度
     * @param $tw 缩略图尺寸（取值越大,越难相似，计算更慢）
     * @param $sensitivy 取值越大，比较越宽松
     */
    static function image_similarity($filename1, $filename2, $tw=16, $sensitivity=10){
        $src1=is_resource($filename1) ? $filename1 : @imagecreatefromjpeg($filename1);
        if(!$src1) return null; $w1=imagesx($src1); $h1=imagesy($src1);
        $src2=is_resource($filename2) ? $filename2 : @imagecreatefromjpeg($filename2);
        if(!$src2) return null; $w2=imagesx($src2); $h2=imagesy($src2);

        // 生成缩略图
        $thumb1 = imagecreatetruecolor($tw, $tw);
        $thumb2 = imagecreatetruecolor($tw, $tw);
        imagecopyresampled($thumb1, $src1, 0, 0, 0, 0, $tw, $tw, $w1, $h1);
        imagecopyresampled($thumb2, $src2, 0, 0, 0, 0, $tw, $tw, $w2, $h2);

        $similar=0;

        // 比较所有像素的平均XYZ空间差异
        for ($x = 0; $x < $tw; ++$x) {
            for ($y = 0; $y < $tw; ++$y) {
                $c1 = imagecolorsforindex($thumb1, imagecolorat($thumb1, $x, $y));
                $c2 = imagecolorsforindex($thumb2, imagecolorat($thumb2, $x, $y));

                list($x1, $y1, $z1)=self::rgb2xyz($c1['red'], $c1['green'], $c1['blue']);
                list($x2, $y2, $z2)=self::rgb2xyz($c2['red'], $c2['green'], $c2['blue']);

                $diff=(abs($x1-$x2) + abs($y1-$y2) + abs($z1-$z2))/3;

                if($diff<$sensitivity){
                    $similar++;
                }
            }
        }

        return $similar / ($tw * $tw);
    }
}
