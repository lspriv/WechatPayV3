<?php
namespace WechatPayV3;
use WechatPay\GuzzleMiddleware\{ Util\AesUtil, Util\PemUtil, Auth\CertificateVerifier };

/**
 * WechatPayV3\Notify
 * Require PHP 7+
 * 
 * @package WechatPayV3
 * @author lspriv
 */
class Notify {

	/**
     * 请求报文，请求数据， API V3 Key
	 * @access private
     * @var
     */
	private $data;
	private $decryptData;
	private $merchantKey;

	/**
     * 构造函数
     * @access public
     * @param string $merchantKey API V3 Key
     * @throws WxPayException
     */
	public function __construct (string $merchantKey) {
		if(empty($merchantKey)) throw new WxPayException('API V3 Key 不能为空', WxPayException::NotifyKeyErrorCode);
		$requestBody = file_get_contents("php://input");
		if(empty($requestBody)) throw new WxPayException('通知参数不能为空', WxPayException::NotifyDataErrorCode);
		$this->certPemFile = __DIR__ . DIRECTORY_SEPARATOR . 'cert' . DIRECTORY_SEPARATOR . 'cert.pem';
		if(!is_file($this->certPemFile)) throw new WxPayException('商户平台证书缺失', WxPayException::NotifyVerifyErrorCode);
		$this->data = $requestBody;
		$this->merchantKey = $merchantKey;
		$this->verifySign();
		$this->decrypt($merchantKey);
	}

	/**
     * 验证签名
     * @access protected
     * @throws WxPayException
     */
	protected function verifySign () :void {
		$headers = Util::getHeaders();
		if(!isset($headers['wechatpay-serial'], $headers['wechatpay-timestamp'], $headers['wechatpay-nonce'], $headers['wechatpay-signature'])) {
			throw new WxPayException('应答的微信支付签名验证参数缺失', WxPayException::NotifyVerifyErrorCode);
		}
		$serialNumber = $headers['wechatpay-serial'];
		$message = "{$headers['wechatpay-timestamp']}" . "\n"
				 . $headers['wechatpay-nonce'] . "\n"
				 . $this->data . "\n";
		$signature = $headers['wechatpay-signature'];
		$wechatpayCertificate = PemUtil::loadCertificate($this->certPemFile);
		$certificateVerifier = new CertificateVerifier([$wechatpayCertificate]);
		if(!$certificateVerifier->verify($serialNumber, $message, $signature)) throw new WxPayException('应答的微信支付签名验证失败', WxPayException::NotifyVerifyErrorCode);
	}

	/**
     * 解密请求报文
     * @access protected
     * @throws WxPayException
     */
	protected function decrypt () :void {
		$request_data = json_decode($this->data, true);
		if(empty($request_data)) throw new WxPayException('通知参数不能为空', WxPayException::NotifyDataErrorCode);
		$decryptData = [
			'id' => $request_data['id'] ?: '',
			'create_time' => $request_data['create_time'] ? date('Y-m-d H:i:s', Util::W3CStringToTime($request_data['create_time'])) : '',
			'summary' => $request_data['summary'] ?: ''
		];
		try {
			if($request_data['event_type'] && $request_data['event_type'] == 'TRANSACTION.SUCCESS') {
				$util = new AesUtil($this->merchantKey);
				$resource = $request_data['resource'];
				$decryptData['result'] = 'SUCCESS';
				$decrypt = json_decode($util->decryptToString($resource['associated_data'], $resource['nonce'], $resource['ciphertext']), true);
				$decrypt['success_time'] = $decrypt['success_time'] ? date('Y-m-d H:i:s', Util::W3CStringToTime($decrypt['success_time'])) : '';
 				$decryptData['data'] = $decrypt;
			}else {
				$decryptData['result'] = 'FAIL';
				$decryptData['data'] = [];
			}
		} catch (\InvalidArgumentException $e) {
			throw new WxPayException($e->getMessage(), WxPayException::NotifyDecryptErrorCode);
		} catch (\Exception $e) {
			throw new WxPayException($e->getMessage(), WxPayException::NotifyDecryptErrorCode);
		}
		$this->decryptData = $decryptData;
	}

	/**
     * 通知处理方法
     * @access public
	 * @param callable $userFunc
     */
	public function handle (callable $userFunc) :void {
		$userFunc($this->decryptData, new class {
			public function success ($msg = '') {
				self::out('SUCCESS', $msg);
			}
			public function fail ($err = '') {
				self::out('ERROR', $err);
			}
			protected static function out ($code, $message) {
				echo json_encode(compact('code', 'message'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				exit(0);
			}
		});
	}

}
