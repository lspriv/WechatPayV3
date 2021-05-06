# WechatPayV3

 为微信支付v3接口中间件wechatpay-guzzle-middleware封装的服务商平台微信支付SDK

>     · 自动下载和验证平台证书
>     · JsApi参数自动处理
>     · 支付通知报头自动验签，报文解密

> **`注意`** 此版本适用于JSAPI支付和小程序支付，后面将会扩展至其他支付和商户平台。

> [欢迎到issues面板提出建议和BUG](https://github.com/lspriv/WechatPayV3/issues)

### 要求

>     · PHP 7+
>     · OpenSSL Extension
>     · wechatpay/wechatpay-guzzle-middleware Lib

### 配置

[**`WechatPayV3\Client::KEY_FILE_PATH`**](#KEY_FILE_PATH)  私钥本地路径

[**`WechatPayV3\Client::CERT_FILE_PATH`**](#CERT_FILE_PATH)  平台证书本地保存目录

[**`WechatPayV3\Client::CERT_EXTENSION`**](#CERT_EXTENSION)  平台证书扩展名

[**`WechatPayV3\Client::CERT_TRUST_CHAIN_PATH`**](#CERT_TRUST_CHAIN_PATH)  平台证书信任链本地保存路径

### 使用

```php
<?php
use WechatPayV3\Client;
use WechatPayV3\WxPayException;

// 基本配置
$merchantId = 'xxxxxxxx'; // 商户号
$merchantKey = 'xxxxxxxxxxxx'; // API V3 Key
$merchantSerialNumber = 'xxxxxxxxxxxx'; // API证书序列号
$appId = 'xxxxxxxx'; // 应用ID
$subMchId = 'xxxxxxxx'; // 子商户号

try {
    $wechatPay = new Client($merchantId, $merchantKey, $merchantSerialNumber);
    $wechatPay->setApp($appId)->setSub($subMchId);
} catch (WxPayException $e) {
    // 错误处理
}
```

### 方法
> **`注意`** 所有的接口参数`$params`中的`sp_appid`，`sp_mchid`，`sub_appid`，`sub_mchid`无需再填写

#### 统一下单

```php
    $wechatPay->unifiedOrder($params);
```

> `$params`参数为微信支付v3 JsApi统一下单接口的参数，详情见 [微信支付v3统一下单](https://pay.weixin.qq.com/wiki/doc/apiv3_partner/apis/chapter4_1_1.shtml)

#### 微信支付订单号查询

```php
    $transactionId = 'xxxxxxxx'; // 微信支付订单号
    $wechatPay->query($transactionId); 
```

#### 商户订单号查询

```php
    $outTradeNo = 'xxxxxxxx'; // 商户订单号
    $wechatPay->queryOrder($outTradeNo); 
```

#### 关闭订单

```php
    $outTradeNo = 'xxxxxxxx'; // 商户订单号
    $wechatPay->closeOrder($outTradeNo); 
```

#### 申请退款

```php
    $wechatPay->refund($params); 
```

> `$params`参数为微信支付v3 JsApi申请退款接口的参数，详情见 [微信支付v3申请退款](https://pay.weixin.qq.com/wiki/doc/apiv3_partner/apis/chapter4_1_9.shtml)

#### 查询单笔退款

```php
    $outRefundNo = 'xxxxxxxx'; // 商户退款单号
    $wechatPay->queryRefund($outRefundNo); 
```

#### 申请交易账单

```php
    $wechatPay->tradeBill($params); 
```

> `$params`参数为微信支付v3 JsApi申请交易账单接口的参数，详情见 [微信支付v3申请交易账单](https://pay.weixin.qq.com/wiki/doc/apiv3_partner/apis/chapter4_1_6.shtml)

#### 申请资金账单

```php
    $wechatPay->fundFlowBill($params); 
```

> `$params`参数为微信支付v3 JsApi申请资金账单接口的参数，详情见 [微信支付v3申请资金账单](https://pay.weixin.qq.com/wiki/doc/apiv3_partner/apis/chapter4_1_7.shtml)

#### 下载平台证书

```php
    $wechatPay->downloadCerts(); 
```

> **`注意`** 下载平台证书会清空 `WechatPayV3\Client::CERT_FILE_PATH` 下的所有扩展名为 `WechatPayV3\Client::CERT_EXTENSION` 的平台证书，请务必将平台证书放到单独的文件夹下或另起扩展名称（修改`WechatPayV3\Client::CERT_EXTENSION`值即可）

#### 上传媒体文件

```php
    // 待上传文件本地路径
    $path = '/usr/local/../test.png';
    // 上传地址
    $url = 'https://api.mch.weixin.qq.com/v3/[merchant/media/video_upload|marketing/favor/media/image-upload]'; 
    $wechatPay->upload($path, $url); 
```

### 微信支付异步通知

```php
<?php
use WechatPayV3\Client as WechatPay;
use WechatPayV3\WxPayException;

// 接收通知的接口
function receive () {
    // API V3 Key
    $merchantKey = 'xxxxxxxx'; 

    try {
        WechatPay::notify($merchantKey)->handle(function($data, $handler) {
            // $data 解密后通知数据
            // 处理成功执行
            $handler->success('oprate success');
            // 处理失败执行
            $handler->fail('oprate fail');
        });
    } catch (WxPayException $e) {
        // 错误处理
    }
}
```

### 关于

>     有任何问题或是需求请到 `Issues` 面板提交
>     忙的时候还请见谅
>     有兴趣开发维护的小伙伴加微信

![wx_qr](https://chat.qilianyun.net/static/git/calendar/wx.png)