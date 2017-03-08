<?php
/**
 * Sunlight Captcha Class.
 * @version 1.0.0
 * @package Plug
 * @author Linxu
 * @link http://article.zhile.name
 * @email lliangxu@qq.com
 * @date 2014-01-01
 */
/**
 * 安全验证码
 * 
 * 安全的验证码要：验证码文字旋转，使用不同字体，可加干扰码、可加干扰线、可使用中文、可使用背景图片
 * 可配置的属性都是一些简单直观的变量，我就不用弄一堆的setter/getter了
 *
 * @author  
 * @copyright 
 */
class SunlightCaptcha {
	/**
	 * 验证码的session的下标
	 * 
	 * @var string
	 */
	public static $seKey     = 'captcha';
	public static $expire    = 600;     // 验证码过期时间（s）
	public static $useZh     = false;    // 使用中文验证码 
	public static $useImgBg  = false;     // 使用背景图片 
	public static $fontSize  = 0;     // 验证码字体大小(px)
	public static $useCurve  = false;   // 是否画混淆曲线
	public static $useNoise  = true;   // 是否添加杂点
	public static $noiseDensity  = 3;   // 是否添加杂点
	public static $height    = 35;        // 验证码图片宽
	public static $width    = 0;        // 验证码图片长
	public static $length    = 4;        // 验证码位数
	public static $bg        = array(243, 251, 254);  // 背景
	public static $frame = false;	//yii2
	
	/**
	 * 验证码中使用的字符，01IlO容易混淆，建议不用
	 *
	 * @var string
	 */
	private static $_codeSet   = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
	private static $_image   = null;     // 验证码图片实例
	private static $_color   = null;     // 验证码字体颜色
	
	/**
	 * 验证验证码是否正确
	 *
	 * @param string $code 用户验证码
	 * @return bool 用户验证码是否正确
	 */
	public static function check($code, $destroy = false) {

		/**
		 * 普通Session
		 */
		if (!self::$frame) {
			session_id() || session_start();
			
			if(empty($code) || empty($_SESSION[self::$seKey])) {
				return false;
			}
			$secode = $_SESSION[self::$seKey];
		}

		/**
		 * Yii2 Session
		 * @var [type]
		 */
		if (self::$frame === 'yii2') {
			$secode = Yii::$app->session[self::$seKey];
			if (empty($code) || !$secode) {
				return false;
			}
		}

		// session 过期
		if(time() - $secode['time'] > self::$expire) {
			return false;
		}

		if(strcasecmp($code, $secode['code']) === 0) {
			if ($destroy) {
				if (!self::$frame)
					unset($_SESSION[self::$seKey]);	//普通 Session
				else
					unset(Yii::$app->session[self::$seKey]);	//Yii2 Session
			}
			return true;
		}

		return false;
	}

	/**
	 * 输出验证码并把验证码的值保存的session中
	 * 验证码保存到session的格式为： $_SESSION[self::$seKey] = array('code' => '验证码值', 'time' => '验证码创建时间');
	 */
	public static function output () {
		// 图片高(px)
		self::$fontSize || self::$fontSize = self::$height * 0.6;
		// 图片宽(px)
		self::$width || self::$width = self::$length * self::$fontSize * 1.1; 
		// 建立一幅 self::$width x self::$height 的图像
		self::$_image = imagecreate(self::$width, self::$height); 
		// 设置背景      
		imagecolorallocate(self::$_image, self::$bg[0], self::$bg[1], self::$bg[2]); 

		// 验证码使用随机字体
		$ttfPath = dirname(__FILE__) . '/Captcha/' . (self::$useZh ? 'zhttfs' : 'ttfs') . '/';

		$dir = dir($ttfPath);
		$ttfs = array();
		while (false !== ($file = $dir->read())) {
				if($file[0] != '.' && substr($file, - 4) == '.ttf') {
				$ttfs[] = $ttfPath . $file;
			}
		}
		$dir->close();

		$ttfNum = count($ttfs) - 1;
		
		if(self::$useImgBg) {
			self::_background();
		}
		
		if (self::$useNoise) {
			// 绘杂点
			self::_writeNoise();
		} 
		if (self::$useCurve) {
			// 绘干扰线
			self::_writeCurve();
		}
		
		// 绘验证码
		$code = array(); // 验证码
		$codeNX = -7; // 验证码第N个字符的左边距
		$codeCount = strlen(self::$_codeSet) - 1;
		for ($i = 0; $i<self::$length; $i++) {
			// 验证码字体随机颜色
			self::$_color = imagecolorallocate(self::$_image, mt_rand(100,170), mt_rand(100,170), mt_rand(100,170));
			if(self::$useZh) {
				$code[$i] = chr(mt_rand(0xB0,0xF7)).chr(mt_rand(0xA1,0xFE));
			} else {
				$code[$i] = self::$_codeSet[mt_rand(0, $codeCount)];
				$codeNX += self::$fontSize - 2;
				$rotation = mt_rand(-50, 50);
				// 写一个验证码字符
				self::$useZh || imagettftext(self::$_image, self::$fontSize, $rotation, $codeNX + ($rotation / 6), self::$fontSize * 1.3 + ($rotation / 6), self::$_color, $ttfs[mt_rand(0, $ttfNum)], $code[$i]);
			}
		}
		
		// 保存验证码
		//session_id() || session_start();
		// 把校验码保存到session
		// 验证码创建时间
		if (!self::$frame)
			$_SESSION[self::$seKey] = array('code' => join('', $code), 'time' => time());		//普通 Session
		else
			Yii::$app->session[self::$seKey] = array('code' => join('', $code), 'time' => time());	//Yii2 Session

		self::$useZh && imagettftext(self::$_image, self::$fontSize, 0, (self::$width - self::$fontSize*self::$length*1.2)/5, self::$fontSize * 1.5, self::$_color, $ttfs[0], iconv("GB2312","UTF-8", join('', $code)));

		header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);		
		header('Pragma: no-cache');
		header("content-type: image/png");
	
		// 输出图像
		imagepng(self::$_image); 
		imagedestroy(self::$_image);
	}
	
	/** 
	 * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线(你可以改成更帅的曲线函数) 
		 *      
		 *      高中的数学公式咋都忘了涅，写出来
	 *		正弦型函数解析式：y=Asin(ωx+φ)+b
	 *      各常数值对函数图像的影响：
	 *        A：决定峰值（即纵向拉伸压缩的倍数）
	 *        b：表示波形在Y轴的位置关系或纵向移动距离（上加下减）
	 *        φ：决定波形与X轴位置关系或横向移动距离（左加右减）
	 *        ω：决定周期（最小正周期T=2π/∣ω∣）
	 *
	 */
	protected static function _writeCurve() {
		$px = $py = 0;
			
		// 曲线前部分
		$A = mt_rand(1, self::$height/2);                  // 振幅
		$b = mt_rand(-self::$height/4, self::$height/4);   // Y轴方向偏移量
		$f = mt_rand(-self::$height/4, self::$height/4);   // X轴方向偏移量
		$T = mt_rand(self::$height, self::$width*2);  // 周期
		$w = (2* M_PI)/$T;
						
		$px1 = 0;  // 曲线横坐标起始位置
		$px2 =  self::$width;
		$color = imagecolorallocate(self::$_image, mt_rand(130,190), mt_rand(130,190), mt_rand(130,190));
		for ($px=$px1; $px<=$px2; $px=$px+ 0.9) {
			if ($w!=0) {
				$py = $A * sin($w*$px + $f)+ $b + self::$height/2;  // y = Asin(ωx+φ) + b
				$i = (int) (self::$fontSize/5);
				while ($i > 0) {	
						imagesetpixel(self::$_image, $px , $py + $i, $color);  // 这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出（不用这while循环）性能要好很多				    
						$i--;
				}
			}
		}
		
		// 曲线后部分
		$A = mt_rand(1, self::$height/2);                  // 振幅		
		$f = mt_rand(-self::$height/4, self::$height/4);   // X轴方向偏移量
		$T = mt_rand(self::$height, self::$width*2);  // 周期
		$w = (2* M_PI)/$T;		
		$b = $py - $A * sin($w*$px + $f) - self::$height/2;
		$px1 = $px2;
		$px2 = self::$width;

		for ($px=$px1; $px<=$px2; $px=$px+ 0.9) {
			if ($w!=0) {
				$py = $A * sin($w*$px + $f)+ $b + self::$height/2;  // y = Asin(ωx+φ) + b
				$i = (int) (self::$fontSize/5);
				while ($i > 0) {			
						imagesetpixel(self::$_image, $px, $py + $i, self::$_color);	
						$i--;
				}
			}
		}
	}
	
	/**
	 * 画杂点
	 * 往图片上写不同颜色的字母或数字
	 */
	protected static function _writeNoise() {
		$codeCount = strlen(self::$_codeSet) - 1;
		for($i = 0; $i < 10; $i++){
			//杂点颜色
			$noiseColor = imagecolorallocate(
				self::$_image, 
				mt_rand(180,225), 
				mt_rand(180,225), 
				mt_rand(180,225)
			);
			for($j = 0; $j < self::$noiseDensity; $j++) {
				// 绘杂点
				imagestring(
					self::$_image,
					5, 
					mt_rand(-5, self::$width), 
					mt_rand(-5, self::$height), 
					self::$_codeSet[mt_rand(0, $codeCount)], // 杂点文本为随机的字母或数字
					$noiseColor
				);
			}
		}
	}
	
	/**
	 * 绘制背景图片
	 * 注：如果验证码输出图片比较大，将占用比较多的系统资源
	 */
	private static function _background() {
		$path = dirname(__FILE__).'/Captcha/bgs/';
		$dir = dir($path);

		$bgs = array();		
		while (false !== ($file = $dir->read())) {
				if($file[0] != '.' && substr($file, -4) == '.jpg') {
				$bgs[] = $path . $file;
			}
		}
		$dir->close();

		$gb = $bgs[array_rand($bgs)];

		list($width, $height) = getimagesize($gb);
		// Resample
		$bgImage = imagecreatefromjpeg($gb);
		imagecopyresampled(self::$_image, $bgImage, 0, 0, 0, 0, self::$width, self::$height, $width, $height);
		imagedestroy($bgImage);
	}
}
