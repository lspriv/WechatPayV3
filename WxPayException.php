<?php
namespace WechatPayV3;

/**
 * WechatPayV3\WxPayException
 * Require PHP 7+
 * 
 * @package WechatPayV3
 * @author lspriv
 */
class WxPayException extends \Exception {

	/**
     * 请求参数错误，文件错误（密钥和证书）
     * @const array
     */
	const ParamsErrorCode = 501;
	const FileErrorCode = 502;

	/**
     * 支付通知错误，解析，通知数据，验证签名，Key参数错误
     * @const array
     */
	const NotifyDecryptErrorCode = 503;
	const NotifyDataErrorCode = 504;
	const NotifyVerifyErrorCode = 505;
	const NotifyKeyErrorCode = 506;

}

