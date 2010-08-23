<?php  
    class Clue_Image{
        protected $gfx=null;
        public $width, $height;
        
        function __construct($filename){
            $ext=strtolower(strrchr($filename, '.'));
            
            switch($ext){
                case '.jpg':
                    $this->gfx=imagecreatefromjpeg($filename);
                    break;
                case '.png':
                    $this->gfx=imagecreatefrompng($filename);
                    break;
                default:
                    throw new Exception("Image format not supported: $filename");
            }
            
            $this->width=imageSX($this->gfx);
            $this->height=imageSY($this->gfx);
        }
        
        function __destruct(){
            if($this->gfx) imagedestroy($this->gfx);
        }
        
        function _write($gfx, $filename){
            $ext=strtolower(strrchr($filename, '.'));
            
            switch($ext){
                case ".jpg":
                    imagejpeg($gfx, $filename);
                    break;
                case ".png":
                    imagepng($gfx, $filename);
                    break;
                default:
                    throw new Exception("Image format not supported: $filename");
            }            
        }
        
        function create_thumb($filename, $width, $height){        
            $ratio=$width/($height?:1); // prevent devide by zero
            $originRatio=$this->width/$this->height;
            
            if($ratio>$originRatio)
                $height=$width/$originRatio;
            else if($ratio<$originRatio)
                $width=$originRatio*$height;
            
            $thumb=imagecreatetruecolor($width, $height);
            imagecopyresampled($thumb, $this->gfx, 0, 0, 0, 0, $width, $height, $this->width, $this->height);

            $this->_write($thumb, $filename);
            imagedestroy($thumb);
        }
    }
?>
