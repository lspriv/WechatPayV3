<?php
namespace WechatPayV3;

/**
 * WechatPayV3\Validator
 * Require PHP 7+
 * 
 * @package WechatPayV3
 * @author lspriv
 */
class Validator {

	/**
     * 统一下单必要参数
     * @const array
     */
	const UnifiedBase = [
		'sp_appid' => '服务商申请的公众号sp_appid缺失', 
		'sp_mchid' => '服务商户号sp_mchid缺失',  
		'sub_mchid' => '子商户的商户号sub_mchid缺失', 
		'description' => '商品描述description缺失', 
		'out_trade_no' => '商户系统内部订单号out_trade_no缺失', 
		'notify_url' => '通知notify_url缺失'
	];

	/**
     * 统一下单amount必要参数
     * @const array
     */
	const UnifiedAmount = [
		'total' => '订单总金额amount.total缺失'
	];

	/**
     * 查询订单必要参数
     * @const array
     */
	const QueryOrder = [
		'sp_mchid' => '服务商户号sp_mchid缺失',
		'sub_mchid' => '子商户的商户号sub_mchid缺失'
	];

	/**
     * 申请退款必要参数
     * @const array
     */
	const RefundBase = [
		'sub_mchid' => '子商户的商户号sub_mchid缺失',
		'out_refund_no' => '商户系统内部的退款单号out_refund_no缺失'
	];

	/**
     * 申请退款amount必要参数
     * @const array
     */
	const RefundAmount = [
		'refund' => '退款金额refund缺失',
		'total' => '原支付交易的订单总金额total缺失',
		'currency' => '退款币种currency缺失'
	];

	/**
     * 申请交易账单必要参数
     * @const array
     */
	const TradeBill = [
		'bill_date' => '账单日期bill_date缺失'
	];

	/**
     * 验证统一下单参数
	 * @static
     * @access public
	 * @param array $params 统一下单参数
	 * @throws WxPayException
     */
	public static function checkUnified (array $params) :void {
		self::check($params, self::UnifiedBase);
		self::check($params['amount'], self::UnifiedAmount);
		self::checkUnifiedPayer($params['payer']);
	}

	/**
     * 验证统一下单payer参数
	 * @static
     * @access protected
	 * @param array $params 统一下单payer参数
	 * @throws WxPayException
     */
	protected static function checkUnifiedPayer (array $params) :void {
		if(empty($params['sp_openid']) && empty($params['sub_openid'])) {
			throw new WxPayException('用户在服务商appid下的唯一标识sp_openid和用户在子商户appid下的唯一标识sub_openid二选一', WxPayException::ParamsErrorCode);
		}
	}

	/**
     * 验证查询订单参数
	 * @static
     * @access public
	 * @param array $params 查询订单参数
	 * @throws WxPayException
     */
	public static function checkQuery (array $params) :void {
		self::check($params, self::QueryOrder);
	}

	/**
     * 验证微信支付订单号查询参数transaction_id
	 * @static
     * @access public
	 * @param string $transactionId
	 * @throws WxPayException
     */
	public static function checkQueryTransaction (string $transactionId) :void {
		if(empty($transactionId)) throw new WxPayException('微信支付系统生成的订单号transaction_id缺失', WxPayException::ParamsErrorCode);
	}

	/**
     * 验证商户订单号查询参数out_trade_no
	 * @static
     * @access public
	 * @param string $outTradeNo
	 * @throws WxPayException
     */
	public static function checkQueryOutTrade (string $outTradeNo) :void {
		if(empty($outTradeNo)) throw new WxPayException('商户系统内部订单号out_trade_no缺失', WxPayException::ParamsErrorCode);
	}

	/**
     * 验证查询单笔退款参数out_trade_no
	 * @static
     * @access public
	 * @param string $outTradeNo
	 * @throws WxPayException
     */
	public static function checkQueryRefundNo (string $outRefundNo) :void {
		if(empty($outRefundNo)) throw new WxPayException('商户系统内部的退款单号out_refund_no缺失', WxPayException::ParamsErrorCode);
	}

	/**
     * 验证申请退款参数
	 * @static
     * @access public
	 * @param array $params 申请退款参数
	 * @throws WxPayException
     */
	public static function checkRefund (array $params) :void {
		self::checkRefundOrderNo($params);
		self::check($params, self::RefundBase);
		self::check($params['amount'], self::RefundAmount);
	}

	/**
     * 验证申请退款参数transaction_id和out_trade_no
	 * @static
     * @access protected
	 * @param array $params 申请退款参数
	 * @throws WxPayException
     */
	protected static function checkRefundOrderNo ($params) :void {
		if(empty($params['transaction_id']) && empty($params['out_trade_no'])) {
			throw new WxPayException('原支付交易对应的微信订单号transaction_id和原支付交易对应的商户订单号out_trade_no二选一', WxPayException::ParamsErrorCode);
		}
	}

	/**
     * 验证申请交易账单参数
	 * @static
     * @access protected
	 * @param array $params 申请退款参数
	 * @throws WxPayException
     */
	public static function checkTradeBill (array $params) :void {
		self::check($params, self::TradeBill);
	}

	/**
	 * @static
     * @access protected
	 * @param array $params 
	 * @param array $checks 
	 * @throws WxPayException
     */
	protected static function check (array $params, array $checks) :void {
		$keys = array_keys($checks);
		$filters = array_filter($keys, function($key) use($params){
			return empty($params[$key]);
		});
		if(!empty($filters)) throw new WxPayException($checks[$filters[0]], WxPayException::ParamsErrorCode);
	}

}

