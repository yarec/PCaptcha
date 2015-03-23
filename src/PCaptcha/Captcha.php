<?php

namespace yarec\pcaptcha;

class Captcha {

       /**
        * The name of the GET parameter indicating whether the CAPTCHA image should be regenerated.
        */
       const REFRESH_GET_VAR = 'refresh';
       /**
        * @var integer how many times should the same CAPTCHA be displayed. Defaults to 3.
        * A value less than or equal to 0 means the test is unlimited (available since version 1.1.2).
        */
       public $testLimit = 3;
       /**
        * @var integer the width of the generated CAPTCHA image. Defaults to 120.
        */
       public $width = 120;
       /**
        * @var integer the height of the generated CAPTCHA image. Defaults to 50.
        */
       public $height = 50;
       /**
        * @var integer padding around the text. Defaults to 2.
        */
       public $padding = 2;
       /**
        * @var integer the background color. For example, 0x55FF00.
        * Defaults to 0xFFFFFF, meaning white color.
        */
       public $backColor = 0xFFFFFF;
       /**
        * @var integer the font color. For example, 0x55FF00. Defaults to 0x2040A0 (blue color).
        */                                                                                                                                                                                    
       public $foreColor = 0x2040A0;

       /**                                                                                                                                                                                    
        * @var boolean whether to use transparent background. Defaults to false.
        */
       public $transparent = false;
       /**
        * @var integer the minimum length for randomly generated word. Defaults to 6.
        */
       public $minLength = 6;
       /**
        * @var integer the maximum length for randomly generated word. Defaults to 7.
        */
       public $maxLength = 7;
       /**
        * @var integer the offset between characters. Defaults to -2. You can adjust this property
        * in order to decrease or increase the readability of the captcha.
        */
       public $offset = -2;
       /**
        * @var string the TrueType font file. This can be either a file path or path alias.
        */
       public $fontFile = 'Font/SpicyRice.ttf';
       /**
        * @var string the fixed verification code. When this property is set,
        * [[getVerifyCode()]] will always return the value of this property.
        * This is mainly used in automated tests where we want to be able to reproduce
        * the same verification code each time we run the tests.
        * If not set, it means the verification code will be randomly generated.
        */
       public $fixedVerifyCode;

       public $verifyCode;
   
       /**
        * Initializes the action.
        * @throws InvalidConfigException if the font file does not exist.
        */
       public function __construct()
       {
           $this->fontFile = dirname(__FILE__).'/'. $this->fontFile;
           if (!is_file($this->fontFile)) {
               throw new InvalidConfigException("The font file does not exist: {$this->fontFile}");
           }
       }   

       public function run(){
           $this->setHttpHeaders();
           return $this->renderImage($this->getVerifyCode());
       }

       public function output(){
           echo $this->renderImage($this->getVerifyCode());
       }

       /**
        * Checks if there is graphic extension available to generate CAPTCHA images.
        * This method will check the existence of ImageMagick and GD extensions.
        * @return string the name of the graphic extension, either "imagick" or "gd".
        * @throws InvalidConfigException if neither ImageMagick nor GD is installed.
        */
       public static function checkRequirements()
       {
           if (extension_loaded('imagick')) {
               $imagick = new \Imagick();
               $imagickFormats = $imagick->queryFormats('PNG');
               if (in_array('PNG', $imagickFormats)) {
                   return 'imagick';
               }
           }
           if (extension_loaded('gd')) {
               $gdInfo = gd_info();
               if (!empty($gdInfo['FreeType Support'])) {
                   return 'gd';
               }
           }
           throw new InvalidConfigException('GD with FreeType or ImageMagick PHP extensions are required.');
       }

    
       /**
        * Gets the verification code.
        * @param boolean $regenerate whether the verification code should be regenerated.
        * @return string the verification code.
        */
       public function getVerifyCode($regenerate = false)
       {
           if ($this->fixedVerifyCode !== null) {
               return $this->fixedVerifyCode;
           }

           if ($this->verifyCode=== null || $regenerate) {
               $this->verifyCode= $this->generateVerifyCode();
           }
           return $this->verifyCode;
       }

       /** 
        * Generates a new verification code.
        * @return string the generated verification code
        */ 
       protected function generateVerifyCode()
       {
           if ($this->minLength > $this->maxLength) {
               $this->maxLength = $this->minLength;
           }
           if ($this->minLength < 3) {
               $this->minLength = 3;
           }
           if ($this->maxLength > 20) {
               $this->maxLength = 20;
           }
           $length = mt_rand($this->minLength, $this->maxLength);
               
           $letters = 'bcdfghjklmnpqrstvwxyz';
           $vowels = 'aeiou';
           $code = '';
           for ($i = 0; $i < $length; ++$i) {
               if ($i % 2 && mt_rand(0, 10) > 2 || !($i % 2) && mt_rand(0, 10) > 9) {
                   $code .= $vowels[mt_rand(0, 4)];
               } else { 
                   $code .= $letters[mt_rand(0, 20)];
               }   
           }   
                   
           return $code;
       } 

       /** 
        * Renders the CAPTCHA image.
        * @param string $code the verification code
        * @return string image contents
        */
       protected function renderImage($code)
       {   
           if (self::checkRequirements() === 'gd') {
               return $this->renderImageByGD($code);
           } else {
               return $this->renderImageByImagick($code);
           }
       } 

       /**
        * Renders the CAPTCHA image based on the code using ImageMagick library.
        * @param string $code the verification code
        * @return string image contents in PNG format.
        */
       protected function renderImageByImagick($code)
       {
           $backColor = $this->transparent ? new \ImagickPixel('transparent') : new \ImagickPixel('#' . dechex($this->backColor));
           $foreColor = new \ImagickPixel('#' . dechex($this->foreColor));
   
           $image = new \Imagick();
           $image->newImage($this->width, $this->height, $backColor);
   
           $draw = new \ImagickDraw();
           $draw->setFont($this->fontFile);
           $draw->setFontSize(30);
           $fontMetrics = $image->queryFontMetrics($draw, $code);
           
           $length = strlen($code);
           $w = (int) ($fontMetrics['textWidth']) - 8 + $this->offset * ($length - 1);
           $h = (int) ($fontMetrics['textHeight']) - 8;                                                                                                                                       
           $scale = min(($this->width - $this->padding * 2) / $w, ($this->height - $this->padding * 2) / $h);
           $x = 10;
           $y = round($this->height * 27 / 40);
           for ($i = 0; $i < $length; ++$i) {
               $draw = new \ImagickDraw();
               $draw->setFont($this->fontFile);
               $draw->setFontSize((int) (rand(26, 32) * $scale * 0.8));
               $draw->setFillColor($foreColor);
               $image->annotateImage($draw, $x, $y, rand(-10, 10), $code[$i]);
               $fontMetrics = $image->queryFontMetrics($draw, $code[$i]);
               $x += (int) ($fontMetrics['textWidth']) + $this->offset;
           }   
               
           $image->setImageFormat('png');
           return $image->getImageBlob();
       }    

      /**
        * Renders the CAPTCHA image based on the code using GD library.
        * @param string $code the verification code
        * @return string image contents in PNG format.
        */ 
       protected function renderImageByGD($code)
       {
           $image = imagecreatetruecolor($this->width, $this->height);

           $backColor = imagecolorallocate(
               $image,
               (int) ($this->backColor % 0x1000000 / 0x10000),
               (int) ($this->backColor % 0x10000 / 0x100),
               $this->backColor % 0x100 
           );  
           imagefilledrectangle($image, 0, 0, $this->width, $this->height, $backColor);
           imagecolordeallocate($image, $backColor);

           if ($this->transparent) {
               imagecolortransparent($image, $backColor);
           }

           $foreColor = imagecolorallocate(
               $image,
               (int) ($this->foreColor % 0x1000000 / 0x10000),
               (int) ($this->foreColor % 0x10000 / 0x100),
               $this->foreColor % 0x100
           );
           $length = strlen($code);
           $box = imagettfbbox(30, 0, $this->fontFile, $code);
           $w = $box[4] - $box[0] + $this->offset * ($length - 1);
           $h = $box[1] - $box[5];
           $scale = min(($this->width - $this->padding * 2) / $w, ($this->height - $this->padding * 2) / $h);
           $x = 10;
           $y = round($this->height * 27 / 40);
           for ($i = 0; $i < $length; ++$i) {
               $fontSize = (int) (rand(26, 32) * $scale * 0.8);
               $angle = rand(-10, 10);
               $letter = $code[$i];
               $box = imagettftext($image, $fontSize, $angle, $x, $y, $foreColor, $this->fontFile, $letter);
               $x = $box[2] + $this->offset;
           }   

           imagecolordeallocate($image, $foreColor);

           ob_start();
           imagepng($image);
           imagedestroy($image);

           return ob_get_clean();
       }

       /**
        * Sets the HTTP headers needed by image response.
        */
       protected function setHttpHeaders()
       {
               header('Pragma', 'public');
               header('Expires', '0');
               header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
               header('Content-Transfer-Encoding', 'binary');
               header('Content-type', 'image/jpeg');
       }
}
