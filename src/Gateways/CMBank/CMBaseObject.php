<?php

/*
 * The file is part of the payment lib.
 *
 * (c) Leo <dayugog@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Payment\Gateways\CMBank;

use Payment\Exceptions\GatewayException;
use Payment\Helpers\ArrayUtil;
use Payment\Helpers\RsaEncrypt;
use Payment\Helpers\StrUtil;
use Payment\Payment;
use Payment\Supports\BaseObject;
use Payment\Supports\HttpRequest;

/**
 * @package Payment\Gateways\CMBank
 * @author  : Leo
 * @email   : dayugog@gmail.com
 * @date    : 2019/11/26 9:52 PM
 * @version : 1.0.0
 * @desc    : 招商银行基础类  /  http://openhome.cmbchina.com/PayNew/pay/home
 **/
abstract class CMBaseObject extends BaseObject
{
    use HttpRequest;

    const REQ_SUC = 'SUC0000'; // 请求成功的标记

    /**
     * @var bool
     */
    protected $isSandbox = false;

    /**
     * @var string
     */
    protected $gatewayUrl = '';

    /**
     * 设置加密方式
     * @var string
     */
    protected $signType = '';

    /**
     * @var string
     */
    protected $merKey = '';

    /**
     * @var string
     */
    protected $publicKey = '';

    /**
     * CMBaseObject constructor.
     * @throws GatewayException
     */
    public function __construct()
    {
        $this->isSandbox = self::$config->get('use_sandbox', false);
        $this->signType = self::$config->get('sign_type', 'SHA-256');

        $rsaPublicKey = self::$config->get('cmb_pub_key', '');
        if ($rsaPublicKey) {
            $this->publicKey = StrUtil::getRsaKeyValue($rsaPublicKey, 'public');
        }
        if (empty($this->publicKey)) {
            throw new GatewayException('please set ali public key', Payment::PARAMS_ERR);
        }
    }

    /**
     * @param string $signStr
     * @return string
     * @throws GatewayException
     */
    protected function makeSign(string $signStr)
    {
        try {
            switch ($this->signType) {
                case 'SHA-256':
                    $signStr .= '&' . $this->merKey;
                    $signStr = StrUtil::characet($signStr, 'UTF-8');
                    $sign    = hash('sha256', $signStr);
                    break;
                default:
                    throw new GatewayException(sprintf('[%s] sign type not support', $this->signType), Payment::PARAMS_ERR);
            }
        } catch (GatewayException $e) {
            throw $e;
        }

        return strtoupper($sign);
    }

    /**
     * 验证签名
     * @param array $data
     * @param string $sign
     * @return bool
     * @throws GatewayException
     */
    protected function verifySign(array $data, string $sign)
    {
        $preStr = json_encode($data, JSON_UNESCAPED_UNICODE);
        try {
            if ($this->signType === 'SHA-256') {// 使用RSA
                $rsa = new RsaEncrypt($this->publicKey);
                return $rsa->rsaVerify($preStr, $sign);
            }
            throw new GatewayException(sprintf('[%s] sign type not support', $this->signType), Payment::PARAMS_ERR);
        } catch (\Exception $e) {
            throw new GatewayException(sprintf('check ali pay sign failed, sign type is [%s]', $this->signType), Payment::SIGN_ERR, $data);
        }
    }

    /**
     * @param array $requestParams
     * @return array
     * @throws GatewayException
     */
    protected function buildParams(array $requestParams = [])
    {
        try {
            $requestData = $this->getRequestParams($requestParams);

            $params = [
                // 公共参数
                'version'  => '1.0',
                'charset'  => 'UTF-8',
                'signType' => $this->signType,
                'reqData'  => $requestData,
            ];

            // 签名
            $params = ArrayUtil::arraySort($params);
            $signStr = ArrayUtil::createLinkString($params);

            $params['sign'] = $this->makeSign($signStr);
        } catch (\Exception $e) {
            throw new GatewayException($e->getMessage(), Payment::PARAMS_ERR);
        }

        return $params;
    }

    /**
     * @param array $requestParams
     * @return mixed
     */
    abstract protected function getRequestParams(array $requestParams);

    /**
     * @param string $gatewayUrl
     */
    public function setGatewayUrl(string $gatewayUrl)
    {
        $this->gatewayUrl = $gatewayUrl;
    }
}
