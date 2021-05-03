<?php
namespace WechatPayV3;

/**
 * WechatPayV3\Util
 * Require PHP 7+
 * 
 * @package WechatPayV3
 * @author lspriv
 */
class Util {

	/**
     * 随机串字符集
     * @const string
     */
	const NONCE_CHARACTERS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	/**
     * 搜索数组索引
	 * @static
     * @access public
	 * @param array $array  
	 * @param callable $userFunc 
	 * @return int
     */
	public static function indexOfArr(array $array, callable $userFunc) :int {
		if(empty($userFunc) && !is_callable($userFunc)) return -1; 
		foreach($array as $key => $v) {
			if($userFunc($v)) return $key;
		}
		return -1;
	}

	/**
     * 转换w3c格式日期为时间戳
	 * @static
     * @access public
	 * @param string $utcdatestring  
	 * @return int
     */
	public static function W3CStringToTime(string $utcdatestring) :int {
		$time = \DateTimeImmutable::createFromFormat(\DateTimeInterface::W3C, $utcdatestring, new \DateTimeZone(date_default_timezone_get()));
		return $time->getTimestamp();
	}

	/**
     * 生成随机串
	 * @static
     * @access public
	 * @return string
     */
	public static function generateNonceStr() :string {
        $charactersLength = strlen(self::NONCE_CHARACTERS);
        $randomString = '';
        for ($i = 0; $i < 32; $i++) {
            $randomString .= self::NONCE_CHARACTERS[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

	/**
     * 获取请求头
	 * @static
     * @access public
	 * @return array
     */
	public static function getHeaders (array $header = []) :array {
		if (function_exists('apache_request_headers') && $result = apache_request_headers()) {
			return array_change_key_case($result);
		} 
		foreach ($_SERVER as $key => $val) {
			if (0 === strpos($key, 'HTTP_')) {
				$key          = str_replace('_', '-', strtolower(substr($key, 5)));
				$header[$key] = $val;
			}
		}
		if (isset($_SERVER['CONTENT_TYPE'])) {
			$header['content-type'] = $_SERVER['CONTENT_TYPE'];
		}
		if (isset($_SERVER['CONTENT_LENGTH'])) {
			$header['content-length'] = $_SERVER['CONTENT_LENGTH'];
		}
		return array_change_key_case($header);
	}

	/**
     * 获取目录文件列表
	 * @static
     * @access public
	 * @param string $directory 路径
	 * @param string $ext 文件扩展  
	 * @return array
     */
	public static function getFiles (string $directory, string $ext = null) :array {
		if(!is_dir($directory)) return [];
		$files = array_diff(scandir($directory), ['.', '..']);
		$filter = empty($ext) ? function ($file) use($directory){
			return is_file($directory . DIRECTORY_SEPARATOR . $file);
		} : function ($file) use($ext){
			return pathinfo($file, PATHINFO_EXTENSION) === $ext;
		};
		return array_filter($files, $filter);
	} 

	/**
     * 删除目录文件列表
	 * @static
     * @access public
	 * @param string $directory 路径
	 * @param string $ext 文件扩展  
	 * @return array
     */
	public static function deleteFiles (string $directory, string $ext = null) :array {
		if(!is_dir($directory)) return false;
		if(empty($ext)) {
			return array_map(function($file) use($directory){
				return unlink($directory . DIRECTORY_SEPARATOR . $file);
			}, array_diff(scandir($directory), ['.', '..']));
		}
		return array_map(function($file) use($directory){
			return unlink($directory . DIRECTORY_SEPARATOR . $file);
		}, glob($directory . DIRECTORY_SEPARATOR . '*.' . $ext));
	} 

}

