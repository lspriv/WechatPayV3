<?php
namespace WechatPayV3;
use Psr\Http\Message\ResponseInterface;
use WechatPay\GuzzleMiddleware\Validator as GuzzleValidator;

/**
 * WechatPayV3\Noop
 * Require PHP 7+
 * 
 * @package WechatPayV3
 * @author lspriv
 * @link https://github.com/wechatpay-apiv3/wechatpay-guzzle-middleware#%E5%A6%82%E4%BD%95%E4%B8%8B%E8%BD%BD%E5%B9%B3%E5%8F%B0%E8%AF%81%E4%B9%A6
 */
class Noop implements GuzzleValidator {

	public function validate(ResponseInterface $response) {
		return true;
	}

}

