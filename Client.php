<?php
namespace WechatPayV3;
use GuzzleHttp\{ HandlerStack, Client as GuzzleClient, Exception\RequestException };
use WechatPay\GuzzleMiddleware\{ WechatPayMiddleware, Util\PemUtil, Auth\PrivateKeySigner };

/**
 * WechatPayV3\Client
 * Require PHP 7+
 * 
 * @package WechatPayV3
 * @author lspriv
 */
class Client {

	/**
     * 请求头必要信息
     * @const array
     */
	const BASE_HEADERS = [ 'accept' => 'application/json' ];

	/**
     * 私钥和证书文件路径
     * @const string
     */
	const KEY_FILE_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'key' . DIRECTORY_SEPARATOR . 'key.pem';
	const CERT_FILE_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'certs';
	const CERT_TRUST_CHAIN_PATH = '';

	/**
     * 平台证书扩展名
     * @const string
     */
	const CERT_EXTENSION = 'pem';

	/**
     * 商户号信息
	 * @static
	 * @access private
     * @var string
     */
	private static $merchantId;
	private static $merchantSerialNumber;
	private static $merchantKey;

	/**
     * v3 私钥，证书，中间件，Guzzle客户端，Stack
	 * @access private
     * @var
     */
	private $merchantPrivateKey;
	private $wechatpayCertificates;
	private $wechatpayMiddleware = null;
	private $client = null;
	private $stack = null;
	
	/**
     * 服务商应用ID，子商户应用ID，子商户号 
	 * @access private
     * @var
     */
	private $spAppId = '';
	private $subAppId = '';
	private $subMchId = '';

	private $certs;

	/**
     * 构造函数
     * @access public
     * @param string $merchantId 商户号
     * @param string $merchantKey API V3 Key
	 * @param string $mchSerialNo 商户API证书序列号
     * @throws WxPayException
     */
	public function __construct(string $merchantId, string $merchantKey, string $mchSerialNo) {
		self::$merchantId = $merchantId;
		self::$merchantSerialNumber = $mchSerialNo;
		self::$merchantKey = $merchantKey;

		if(!is_file(self::KEY_FILE_PATH)) throw new WxPayException('密钥不存在', WxPayException::FileErrorCode);
		$this->merchantPrivateKey = PemUtil::loadPrivateKey(self::KEY_FILE_PATH);
		$this->initLoad();
	}

	/**
     * 创建Guzzle Client
     * @access protected
     * @param string $hasCertFile 是否存在平台证书
     */
	protected function initClient (bool $repeat = true) :void {
		$hasWeChatCerts = !empty($this->wechatpayCertificates);
		$handler = WechatPayMiddleware::builder()->withMerchant(self::$merchantId, self::$merchantSerialNumber, $this->merchantPrivateKey);
		$this->wechatpayMiddleware = $hasWeChatCerts ? $handler->withWechatPay($this->wechatpayCertificates)->build() 
								   : $handler->withValidator(new Noop)->build();
		$this->createStack();
		$this->createClient();
		if(!$hasWeChatCerts && $repeat) $this->downloadWeChatCerts();
	}

	/**
     * 加载平台证书
     * @access protected
     */
	protected function initLoad (bool $repeat = true) :void {
		$this->certs = $this->filterCerts(Util::getFiles(self::CERT_FILE_PATH, self::CERT_EXTENSION));
		$this->wechatpayCertificates = array_filter(array_map(function($certFile) {
			return PemUtil::loadCertificate(self::CERT_FILE_PATH . DIRECTORY_SEPARATOR . $certFile);
		}, $this->certs), function($cert) {
			return !empty($cert);
		});
		$this->initClient($repeat);
	}

	/**
     * 过滤平台证书
     * @access protected
     */
	protected function filterCerts (array $certs) :array {
		$filter = function($cert) {
			$cert = file_get_contents(self::CERT_FILE_PATH . DIRECTORY_SEPARATOR . $cert);
			$certParse = \openssl_x509_parse($cert);
			if(!$certParse && !isset($certParse['issuer'], $certParse['validTo_time_t']) && !isset($certParse['issuer']['CN'])) return false;
			if($certParse['issuer']['CN'] != 'Tenpay.com Root CA' || $certParse['validTo_time_t'] <= time()) return false;
			if(empty(self::CERT_TRUST_CHAIN_PATH) || !is_file(self::CERT_TRUST_CHAIN_PATH)) {
				// 验证可信证书，暂时没有平台证书信任链，下一版	
				// $chainCert = file_get_contents(self::CERT_TRUST_CHAIN_PATH);
				// $chainPublicKey = openssl_get_publickey($chainCert);
				// $verify = openssl_x509_verify($cert, $chainPublicKey);
				// if($verify !== 1) return false;
			}
			return true;
		};
		return array_filter($certs, $filter);
	}

	/**
     * 创建stack
     * @access protected
     */
	protected function createStack () :void {
		$this->stack = HandlerStack::create();
		$this->stack->push($this->wechatpayMiddleware, 'wechatpay');
	}

	/**
     * 创建guzzle client
     * @access protected
     */
	protected function createClient () :void {
		$this->client = new GuzzleClient(['handler' => $this->stack]);
	}

	/**
     * 设置服务商应用ID
     * @access public
	 * @param string $appId 服务商应用ID
	 * @return self
     */
	public function setAppId ($appId) :self {
		$this->spAppId = $appId;
		return $this;
	}

	/**
     * 设置子商户信息  
     * @access public
	 * @param string $subMchId 子商户号
	 * @param string $subAppId 子商户应用ID
	 * @return self
     */
	public function setSubInfo ($subMchId, $subAppId = '') :self {
		$this->subMchId = $subMchId;
		$this->subAppId = $subAppId;
		return $this;
	}

	/**
     * 统一下单接口  
     * @access public
	 * @param array $base 除去sp_appid，sp_mchid和sub_mchid的V3_JSAPI统一下单文档基本参数
	 * @param array $amount V3_JSAPI统一下单文档amount参数
	 * @param array $payer V3_JSAPI统一下单文档payer参数
	 * @param array $detail V3_JSAPI统一下单文档detail参数
	 * @param array $scene_info V3_JSAPI统一下单文档scene_info参数
	 * @see V3统一下单JSAPI文档 https://pay.weixin.qq.com/wiki/doc/apiv3_partner/apis/chapter4_1_1.shtml
	 * @return array jsApiParams JSAPI调起支付参数 ; prepay_id 订单详情扩展字符串
	 * @throws WxPayException
     */
	public function UnifiedOrder (array $base, array $amount, array $payer, array $detail = null, array $scene_info = null) :array {
		$params = array_merge($base, array_filter(compact('amount', 'payer', 'detail', 'scene_info'), function($param) {
			return !empty($param);
		}), [
			'sp_appid' => $this->spAppId,
			'sp_mchid' => self::$merchantId,
			'sub_mchid' => $this->subMchId
		]);
		Validator::checkUnified($params);
		$result = $this->request(API::BASE . API::UnifiedOrder, $params, 'POST');
		if($result['code'] == 200) {
			$prepay_id = $result['data']['prepay_id'];
			$result['data'] = [
				'jsApiParams' => $this->generateJsApiParameters($prepay_id),
				'prepay_id' => $prepay_id
			];
		}
		return $result;
	}

	/**
     * 微信支付订单号查询  
     * @access public
	 * @param string $transactionId 微信支付订单号
	 * @return array
	 * @throws WxPayException
     */
	public function query (string $transactionId) :array {
		$params = [
			'sp_mchid' => self::$merchantId,
			'sub_mchid' => $this->subMchId
		];
		Validator::checkQueryTransaction($transactionId);
		Validator::checkQuery($params);
		return $this->request(API::BASE . sprintf(API::QueryOrder, $transactionId), $params);
	}

	/**
     * 商户订单号查询  
     * @access public
	 * @param string $outTradeNo 商户订单号
	 * @return array
	 * @throws WxPayException
     */
	public function queryOrder (string $outTradeNo) :array {
		$params = [
			'sp_mchid' => self::$merchantId,
			'sub_mchid' => $this->subMchId
		];
		Validator::checkQueryOutTrade($outTradeNo);
		Validator::checkQuery($params);
		return $this->request(API::BASE . sprintf(API::QueryOutTradeOrder, $outTradeNo), $params);
	}

	/**
     * 关闭订单
     * @access public
	 * @param string $outTradeNo 商户订单号
	 * @return array
	 * @throws WxPayException
     */
	public function closeOrder (string $outTradeNo) :array {
		$params = [
			'sp_mchid' => self::$merchantId,
			'sub_mchid' => $this->subMchId
		];
		Validator::checkQueryOutTrade($outTradeNo);
		Validator::checkQuery($params);
		return $this->request(API::BASE . sprintf(API::OutTradeOrderClose, $outTradeNo), $params, 'POST');
	}

	/**
     * 申请退款
     * @access public
	 * @param array $base 除去sub_mchid的V3_JSAPI申请退款文档基本参数
	 * @param array $amount V3_JSAPI申请退款文档amount参数
	 * @param array $goods_detail V3_JSAPI申请退款文档goods_detail参数
	 * @see V3申请退款JSAPI文档 https://pay.weixin.qq.com/wiki/doc/apiv3_partner/apis/chapter4_1_9.shtml
	 * @return array
	 * @throws WxPayException
     */
	public function refund (array $base, array $amount, array $goods_detail = null) :array {
		$params = array_merge($base, array_filter(compact('amount', 'goods_detail'), function($param) {
			return !empty($param);
		}), ['sub_mchid' => $this->subMchId]);
		Validator::checkRefund($params);
		return $this->request(API::REFUND_BASE . API::RefundOrder, $params, 'POST');
	}

	/**
     * 查询单笔退款
     * @access public
	 * @param string $outRefundNo 商户退款单号
	 * @return array
	 * @throws WxPayException
     */
	public function queryRefund (string $outRefundNo) :array {
		$params = ['sub_mchid' => $this->subMchId];
		Validator::checkQueryRefundNo($outRefundNo);
		Validator::checkRefund($params);
		return $this->request(API::REFUND_BASE . sprintf(API::QueryRefundOrder, $outRefundNo), $params);
	}

	/**
     * 申请交易账单
     * @access public
	 * @param array $params V3_JSAPI申请交易账单文档参数
	 * @see V3申请交易账单JSAPI文档 https://pay.weixin.qq.com/wiki/doc/apiv3_partner/apis/chapter4_1_6.shtml
	 * @return array
	 * @throws WxPayException
     */
	public function tradeBill(array $params) :array {
		Validator::checkTradeBill($params);
		return $this->request(API::REFUND_BASE . API::TradeBill, $params);
	}

	/**
     * 申请资金账单
     * @access public
	 * @param array $params V3_JSAPI申请资金账单文档参数
	 * @see V3申请资金账单JSAPI文档 https://pay.weixin.qq.com/wiki/doc/apiv3_partner/apis/chapter4_1_7.shtml
	 * @return array
	 * @throws WxPayException
     */
	public function fundFlowBill($params) {
		Validator::checkTradeBill($params);
		return $this->request(API::REFUND_BASE . API::FundflowBill, $params);
	}

	/**
     * 官方上传媒体文件方法
     * @access public
	 * @param string $path 上传文件本地路径
	 * @param string $url 上传地址
	 * @see https://github.com/wechatpay-apiv3/wechatpay-guzzle-middleware#%E4%B8%8A%E4%BC%A0%E5%AA%92%E4%BD%93%E6%96%87%E4%BB%B6
	 * @return array
	 * @throws WxPayException
     */
	public function upload(string $path, string $url) :array {
		$media = new \WechatPay\GuzzleMiddleware\Util\MediaUtil($path);
		try {
			$resp = $this->client->post($url, [
				'body'    => $media->getStream(),
				'headers' => [
					'Accept'       => 'application/json',
					'content-type' => $media->getContentType()
				]
			]);
		} catch (RequestException $e) {
			if ($e->hasResponse()) return self::out($e->getResponse()->getStatusCode(), $e->getResponse()->getReasonPhrase(), json_decode("{$e->getResponse()->getBody()}", true));
			return self::out(500, $e->getMessage());
		}
		$result = json_decode("{$resp->getBody()}", true);
		return self::out($resp->getStatusCode(), $resp->getReasonPhrase(), $result);
	}

	/**
     * 处理支付通知（放到收取支付通知的接口里就可以了）
	 * @static
     * @access public
	 * @param string $key API V3 Key
	 * @return Notify
	 * @throws WxPayException
     */
	public static function notify (string $key) :Notify {
		return new Notify($key);
	}

	/**
     * 生成JSAPI调起支付参数
     * @access protected
	 * @param string $prepay_id 订单详情扩展字符串
	 * @return array
     */
	protected function generateJsApiParameters (string $prepay_id) :array {
		$timestamp = time();
		$noncestr = Util::generateNonceStr();
		$message = $this->spAppId . "\n"
		         . $timestamp . "\n"
				 . $noncestr . "\n"
				 . "prepay_id=" . $prepay_id . "\n";
		$sign = $this->generateSign($message);
		return [
			'appId' => $this->spAppId,
			'timeStamp' => "{$timestamp}",
			'nonceStr' => $noncestr,
			'package' => "prepay_id=" . $prepay_id,
			'signType' => 'RSA',
			'paySign' => $sign
		];
	}

	/**
     * 生成签名
     * @access protected
	 * @param string $message 明文
	 * @return string
     */
	protected function generateSign (string $message) :string {
		$signer = new PrivateKeySigner(self::$merchantSerialNumber, $this->merchantPrivateKey);
		$signRes = $signer->sign($message);
		return $signRes->getSign();
	}

	/**
     * 请求方法
     * @access protected
	 * @param string $url 请求地址
	 * @param array $params 请求参数
	 * @param string $method 请求方法
	 * @param array $headers 请求头
	 * @return array
     */
	protected function request(string $url, array $params, string $method = 'GET', array $headers = []) :array {
		$headers = array_merge(self::BASE_HEADERS, $headers);
		$isGet = strtoupper($method) == 'GET';
		$url = $isGet ? $url . '?' . http_build_query($params) : $url;
		$message = ['headers' => $headers];
		if(!$isGet && !empty($params)) $message['json'] = $params;
		try {
			$resp = $this->client->request($method, $url, $message);
		} catch (RequestException $e) {
			if ($e->hasResponse()) return self::out($e->getResponse()->getStatusCode(), $e->getResponse()->getReasonPhrase(), json_decode("{$e->getResponse()->getBody()}", true));
			return self::out(500, $e->getMessage());
		}
		$result = json_decode("{$resp->getBody()}", true);
		return self::out($resp->getStatusCode(), $resp->getReasonPhrase(), $result);
	}

	/**
     * 下载平台证书 参考官方下载
     * @access protected
	 * @see wechatpay/wechatpay-guzzle-middleware/tool/CertificateDownloader.php => CertificateDownloader::downloadCert()
	 * @throws WxPayException
     */
	public function downloadWeChatCerts() :void {
		try {
            // 接下来，正常使用Guzzle发起API请求，WechatPayMiddleware会自动地处理签名和验签
            $resp = $this->client->request('GET', API::CertificatesDwonload, [
				'headers' => self::BASE_HEADERS
			]);
            if ($resp->getStatusCode() < 200 || $resp->getStatusCode() > 299) {
				throw new WxPayException("download certificates failed, code={$resp->getStatusCode()}, body=[{$resp->getBody()}]", WxPayException::FileErrorCode);
            }
            $certs = json_decode($resp->getBody(), true);
            [$plainCerts, $x509Certs] = $this->decryptCerts($certs['data']);
            // 使用下载的证书再来验证一次应答的签名
            $validator = new \WechatPay\GuzzleMiddleware\Auth\WechatPay2Validator(new \WechatPay\GuzzleMiddleware\Auth\CertificateVerifier($x509Certs));
            if (!$validator->validate($resp)) {
				throw new WxPayException("validate response fail using downloaded certificates!", WxPayException::FileErrorCode);
            }
			Util::deleteFiles(self::CERT_FILE_PATH, self::CERT_EXTENSION);
			$effectiveCerts = array_filter($certs['data'], function($cert) {
				return Util::W3CStringToTime($cert['expire_time']) > time();
			});
			foreach($effectiveCerts as $idx => $cert) {
				file_put_contents(self::CERT_FILE_PATH . DIRECTORY_SEPARATOR . $cert['serial_no'] . '.pem', $plainCerts[$idx]);
			}
        } catch (RequestException $e) {
			throw new WxPayException("download certificates failed, message=[{$e->getMessage()}]", WxPayException::FileErrorCode);
        } catch (\Exception $e) {
			throw new WxPayException("download certificates failed, message=[{$e->getMessage()}]", WxPayException::FileErrorCode);
        }
		$this->initLoad(false);
	}

	/**
     * 解密证书
     * @access protected
	 * @throws WxPayException
     */
	protected function decryptCerts (array $certs) :array {
		$plainCerts = [];$x509Certs = [];
		$decrypter = new \WechatPay\GuzzleMiddleware\Util\AesUtil(self::$merchantKey);
		foreach ($certs as $item) {
			$encCert = $item['encrypt_certificate'];
			$plain = $decrypter->decryptToString($encCert['associated_data'], $encCert['nonce'], $encCert['ciphertext']);
			if (!$plain) throw new WxPayException("encrypted certificate decrypt fail!", WxPayException::FileErrorCode);
			// 通过加载对证书进行简单合法性检验
			$cert = \openssl_x509_read($plain); // 从字符串中加载证书
			if (!$cert) throw new WxPayException("downloaded certificate check fail!", WxPayException::FileErrorCode);
			$plainCerts[] = $plain;
			$x509Certs[] = $cert;
		}
		return [$plainCerts, $x509Certs];
	}

	/**
     * 输出结果
	 * @static
     * @access protected
	 * @param string $code 
	 * @param string $msg 
	 * @param array $data
	 * @return array
     */
	protected static function out(int $code, string $msg, $data = []) :array {
		return compact('code', 'msg', 'data');
	}

}
