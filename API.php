<?php
namespace WechatPayV3;

/**
 * WechatPayV3\API
 * Require PHP 7+
 * 
 * @package WechatPayV3
 * @author lspriv
 */
class API {

	const BASE = 'https://api.mch.weixin.qq.com/v3/pay/partner/transactions/';
	const REFUND_BASE = 'https://api.mch.weixin.qq.com/v3/refund/';
	const BILL_BASE = 'https://api.mch.weixin.qq.com/v3/bill/';

	const UnifiedOrder = 'jsapi';
	const QueryOrder = 'id/%s';
	const QueryOutTradeOrder = 'out-trade-no/%s'; 
	const OutTradeOrderClose = 'out-trade-no/%s/close';
	const RefundOrder = 'domestic/refunds';
	const QueryRefundOrder = 'domestic/refunds/%s';
	const TradeBill = 'tradebill';
	const FundflowBill = 'fundflowbill';
	const CertificatesDwonload = 'https://api.mch.weixin.qq.com/v3/certificates';

}
