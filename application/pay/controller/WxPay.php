<?php

namespace app\pay\controller;

use app\admin\controller\Wxx;
use app\admin\model\DataModel;
use app\pay\model\PayModel;
use app\pay\model\WxPayModel;
use function GuzzleHttp\Psr7\build_query;
use function GuzzleHttp\Psr7\parse_query;
use think\App;
use think\Controller;
use think\Db;
use think\Exception;

class WxPay extends Controller
{
    private $systemConfig;
    private $notifyUrl;

    public function __construct(App $app = null)
    {
        parent::__construct($app);
        $this->systemConfig = getConfig();
        if (empty($this->systemConfig['notifyDomain'])) {
            $this->notifyUrl = url('/Pay/WxPay/Notify', '', false, true);
        } else {
            $this->notifyUrl = $this->systemConfig['notifyDomain'] . '/Pay/WxPay/Notify';
        }
    }

    /**
     * @throws \think\Exception
     */
    public function getWxOpenCode()
    {
        $requestData = input('get.');

        if (empty($requestData['tradeNo']))
            return $this->fetch('/SystemMessage', ['msg' => '请求参数有误，请重新发起订单请求！']);
        if (strlen($requestData['tradeNo']) != 19)
            return $this->fetch('/SystemMessage', ['msg' => '请求参数有误，请重新发起订单请求！']);

        $this->systemConfig['wxpay'] = self::getWxxPayConfig($requestData['tradeNo'], $this->systemConfig);
        if (empty($this->systemConfig['wxpay']))
            return $this->fetch('/SystemMessage', ['msg' => '系统已经冻结所有账号，请联系站点管理员处理！']);

        $wxPayModel = new WxPayModel($this->systemConfig['wxpay'], 'jsapi');
        $wxPayModel->getWxOpenCode(url('/Pay/WxPay/Pay?' . build_query($requestData), '', '', true));
    }

    /**
     * @return mixed|\think\response\Redirect
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getPay()
    {
        $tradeNo  = input('get.tradeNo/s');
        $siteName = htmlentities(base64_decode(input('get.siteName', '易支付')));
//        $sign     = input('get.sign/s');
//        if(md5($tradeNo.'huaji')!=$sign)
//            return $this->fetch('/SystemMessage', ['msg' => '签名有误！']);
        if (empty($siteName))
            $siteName = '易支付';
        if (empty($tradeNo))
            return $this->fetch('/SystemMessage', ['msg' => '交易ID有误！']);
        if (strlen($tradeNo) != 19) {
            $tradeNo = substr($tradeNo, 0, 19);
        }
        //这里负责纠正一些人错误复制访问链接导致失败
        $result = Db::table('epay_order')->where('tradeNo=:tradeNo', ['tradeNo' => $tradeNo])
            ->field('uid,money,productName,status,type,createTime')->limit(1)->select();
        if (empty($result))
            return $this->fetch('/SystemMessage', ['msg' => '交易ID无效！']);
        if ($result[0]['type'] != 1)
            return $this->fetch('/SystemMessage', ['msg' => '支付方式有误！']);
        if ($result[0]['status'])
            return redirect(buildCallBackUrl($tradeNo, 'return'));

        if ($this->systemConfig['wxpay']['apiType'] == 1)
            return $this->fetch('/SystemMessage', ['msg' => '该订单尚不支持原生支付！']);
        else {
            $this->systemConfig['wxpay'] = self::getWxxPayConfig($tradeNo, $this->systemConfig);
            if (empty($this->systemConfig['wxpay']))
                return $this->fetch('/SystemMessage', ['msg' => '系统已经冻结所有账号，请联系站点管理员处理！']);
        }

        if (empty($this->systemConfig['wxpay']['sub_mch_id']))
            return $this->fetch('/SystemMessage', ['msg' => '微信支付下单失败！<br>[系统配置异常] 尚未进行用户身份审核，请发送相关个人资料到相关人员处理。']);

        $productNameShowMode = intval(getPayUserAttr($result[0]['uid'], 'productNameShowMode'));
        $productName         = empty($this->systemConfig['defaultProductName']) ? '这个是默认商品名称' : $this->systemConfig['defaultProductName'];
        if ($productNameShowMode == 1) {
            $tempData    = getPayUserAttr($result[0]['uid'], 'productName');
            $productName = empty($tempData) ? '商户尚未设置默认商品名称' : $tempData;
        } else if ($productNameShowMode == 2) {
            $productName = $result[0]['productName'];
        }

        //$productName = $this->systemConfig['defaultProductName'] . '-' . md5($tradeNo);

        $tradeData                = $result[0];
        $tradeData['tradeNo']     = $tradeNo;
        $tradeData['productName'] = $productName;
        //build trade data
        $isWxBrowser = strpos($this->request->header('user-agent'), 'MicroMessenger') !== false;
        //is wx browser
        $wxPayMode = empty($this->systemConfig['wxpay']['apiMode']) ? 0 : intval($this->systemConfig['wxpay']['apiMode']);
        //get wx pay mode 0 js h5共存 1 仅h5支付 2 仅JsApi支付
        if ($isWxBrowser) {
            if ($wxPayMode == 1)
                return '<h1 style="margin-top: 50%;text-align: center;font-size: 18px;font-weight: 600;">请使用手机浏览器访问问页面，暂不支持微信内打开</h1>';
            //如果为仅H5支付 返回不支持微信打开
            if (empty($this->systemConfig['wxpay']['jsApiAppSecret']))
                return '<h1 style="margin-top: 50%;text-align: center;font-size: 18px;font-weight: 600;">支付配置参数有误,请联系站点管理员处理</h1>';

            $wxOpenCode = input('get.code/s');
            //wx open code
            if (empty($wxOpenCode)) {
                return redirect(url('/Pay/WxPay/WxOpenCode?tradeNo=' . input('get.tradeNo/s') . '&siteName=' . input('get.siteName/s'), '', false, true));
            }
            $wxPayModel = new WxPayModel($this->systemConfig['wxpay'], 'jsapi');
            //init pay model
            $requestResult = $wxPayModel->sendPayRequest($tradeData, 'JSAPI', $this->notifyUrl, $wxOpenCode, $this->systemConfig['wxpay']['sub_mch_id']);
            //手机微信内置浏览器支付 共存支付或 jsapi支付 拉起支付
            PayModel::setOrderAttr($tradeNo, 'wxTradeMode', 'jsapi');
        } else {
            if ($this->request->isMobile()) {
                $requestResult['code_url']    = shortenUrl(url('/Pay/WxPay/WxOpenCode?tradeNo=' . input('get.tradeNo/s'), '', false, true));
                $requestResult['return_code'] = 'SUCCESS';
                $requestResult['result_code'] = 'SUCCESS';
                PayModel::setOrderAttr($tradeNo, 'wxTradeMode', 'jsapi');
            } else {
                $wxPayModel    = new WxPayModel($this->systemConfig['wxpay'], 'h5');
                $requestResult = $wxPayModel->sendPayRequest($tradeData, 'NATIVE', $this->notifyUrl, '', $this->systemConfig['wxpay']['sub_mch_id']);
                //PC端微信支付
                PayModel::setOrderAttr($tradeNo, 'wxTradeMode', 'h5');
            }
        }

        if ($requestResult['return_code'] != 'SUCCESS')
            return $this->fetch('/SystemMessage', ['msg' => '微信支付下单失败！<br>[' . $requestResult['return_code'] . '] ' . $requestResult['return_msg']]);
        if ($requestResult['result_code'] != 'SUCCESS')
            return $this->fetch('/SystemMessage', ['msg' => '微信支付下单失败！<br>[' . $requestResult['err_code'] . '] ' . $requestResult['err_code_des']]);
        if ($requestResult['return_code'] == 'SUCCESS') {
            if ($isWxBrowser) {
                $wxPayModel = new WxPayModel($this->systemConfig['wxpay'], 'jsapi');
                //init pay model
                return $this->fetch('/WxPayJsTemplate', [
                    'jsApiParam'     => $wxPayModel->buildJsApiParam($requestResult),
                    'tradeNo'        => $tradeNo,
                    'cancelCallback' => buildCallBackUrl($tradeNo, 'return')
                ]);
            } else if ($this->request->isMobile()) {
                if ($wxPayMode != 2 && isset($requestResult['mweb_url'])) {
                    $returnUrl  = url('/Pay/WxPay/WapReturn?tradeNo=' . $tradeNo, '', false, true);
                    $requestUrl = $requestResult['mweb_url'] . '&redirect_url=' . urlencode($returnUrl);
                    $parseUrl   = parse_url($requestUrl);
                    $parseQuery = parse_query($parseUrl['query']);
                    $formHtml   = buildRequestForm($parseUrl['scheme'] . '://' . $parseUrl['host'] . $parseUrl['path'], $parseQuery, 'get');
                    //core code
                    return $formHtml;
                }
                $userData = Db::query('SELECT epay_user.qq FROM epay_user RIGHT JOIN epay_order ON epay_order.uid = epay_user.id WHERE epay_order.tradeNo = :tradeNo', ['tradeNo' => $tradeNo]);
                if (empty($userData))
                    $userQQ = '310512312';
                else
                    $userQQ = $userData[0]['qq'];
                return $this->fetch('/WxPayJsH5Template', [
                    'codeUrl' => $requestResult['code_url'],
                    'money'   => $result[0]['money'] / 100,
                    'tradeNo' => $tradeNo,
                    'qq'      => $userQQ
                ]);
            } else {
                return $this->fetch('/WxPayPcTemplate', [
                    'siteName'    => $siteName,
                    'productName' => $result[0]['productName'],
                    'money'       => $result[0]['money'] / 100,
                    'tradeNo'     => $tradeNo,
                    'addTime'     => $result[0]['createTime'],
                    'codeUrl'     => $requestResult['code_url']
                ]);
            }
        }
        return $this->fetch('/SystemMessage', ['msg' => '微信支付下单失败！<br>[' . $requestResult['err_code'] . '] ' . $requestResult['err_code_des']]);
    }

    /**
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getWapReturn()
    {
        $tradeNo = input('get.tradeNo');
        if (empty($tradeNo))
            return $this->fetch('/SystemMessage', ['msg' => '订单ID无效！']);
        $result = Db::table('epay_order')->where('tradeNo=:tradeNo', ['tradeNo' => $tradeNo])->field('id')->limit(1)->select();
        if (empty($result))
            return $this->fetch('/SystemMessage', ['msg' => '订单ID无效！']);
        return $this->fetch('/WxPayReturnADTemplate', ['tradeNo' => $tradeNo]);
    }

    /**
     * @return mixed
     */
    public function getWapResult()
    {
        return $this->fetch('/WxPaySuccessTemplate');
    }

    /**
     * 微信退款统一异步回调
     */
    public function postRefundNotify()
    {
        $requestData = file_get_contents('php://input');
        //get post xml
        $requestData = xmlToArray($requestData);
        //数据转换
        if ($requestData['return_code'] != 'SUCCESS') {
            trace('[微信退款回调异常] 异常提示 => ' . $requestData['return_msg'], 'error');
            return xml([
                'return_code' => 'FAIL',
                'return_msg'  => '微信补单异常提示已记录待处理'
            ]);
        }
        //异常处理
        $appID       = $requestData['appid'];
        $requestInfo = $requestData['req_info'];
        if (empty($appID) || empty($requestInfo))
            return xml([
                'return_code' => 'FAIL',
                'return_msg'  => '请求参数错误，请重试'
            ]);
        //请求参数错误
        $selectAccountInfo = Db::table('epay_wxx_account_list')->where('appID=:appID', ['appID' => $appID])->limit(1)->field('id,desc')->select();
        if (empty($selectAccountInfo))
            return xml([
                'return_code' => 'FAIL',
                'return_msg'  => '查询无此商户号'
            ]);
        try {
            $wxxApiV1Model = Wxx::getWxxApiModel($selectAccountInfo[0]['id']);
            $xmlData       = $wxxApiV1Model->getDecrypt($requestInfo);
            if ($xmlData === false) {
                trace('[微信退款回调异常] 数据解密错误。', 'warning');
                return xml([
                    'return_code' => 'FAIL',
                    'return_msg'  => '解密数据错误'
                ]);
            }
            $xmlData = xmlToArray($xmlData);
            if ($xmlData['refund_status'] == 'SUCCESS') {
                Db::table('epay_order')->where('tradeNo=:tradeNo', ['tradeNo' => $xmlData['out_trade_no']])->limit(1)->update([
                    'status' => 4
                ]);
            } else {
                trace('[微信退款回调异常] 退款状态错误 请登录相关后台自查 name => ' . $selectAccountInfo[0]['desc'] . ' tradeNo => ' . $xmlData['out_trade_no']);
            }
            return xml(['return_code' => 'SUCCESS']);
        } catch (Exception $e) {
            trace('[微信退款回调异常] 初始化微信模块时异常 msg => ' . $e->getMessage(), 'error');
            return xml([
                'return_code' => 'FAIL',
                'return_msg'  => '初始化模块异常，已记录待处理'
            ]);
        }
    }

    /**
     * 微信统一回调地址
     * @return \think\response\Xml
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function postNotify()
    {
        $requestData = file_get_contents('php://input');
        //get post xml
        $requestData = xmlToArray($requestData);
        //数据转换
        if (empty($requestData['sign']))
            return xml(['return_code' => 'FAIL', 'return_msg' => '签名不能为空']);
        $sign = $requestData['sign'];

        if (empty($requestData['sub_mch_id']))
            $requestData['sub_mch_id'] = '';

        $outTradeNo = $requestData['out_trade_no'];

        $wxPayMode = PayModel::getOrderAttr($outTradeNo, 'wxTradeMode');
        if ($wxPayMode == '')
            $wxPayMode = 'h5';
        //兼容老版本 承接新版本
        if (!empty($requestData['sub_mch_id'])) {
            $this->systemConfig['wxpay'] = self::getWxxPayConfig($outTradeNo, $this->systemConfig);
        }
        $wxPayModel = new WxPayModel($this->systemConfig['wxpay'], $wxPayMode);
        if ($wxPayModel->signParam($requestData) != $sign)
            return xml(['return_code' => 'FAIL', 'return_msg' => '签名效验有误']);
        //check sign
        if (empty($requestData['return_code']) || empty($requestData['result_code']))
            return xml(['return_code' => 'FAIL', 'return_msg' => '订单状态无效']);
        if ($requestData['return_code'] != 'SUCCESS' && $requestData['result_code'] != 'SUCCESS')
            return xml(['return_code' => 'FAIL', 'return_msg' => '订单状态无效']);
        //check order status
        if (!$wxPayModel->checkWxPayStatus($requestData['transaction_id'], 'transaction_id', $requestData['sub_mch_id']))
            return xml(['return_code' => 'FAIL', 'return_msg' => '订单付款状态效验失败']);
        //check order pay status


        $result = Db::table('epay_order')->where('tradeNo=:tradeNo', ['tradeNo' => $requestData['out_trade_no']])
            ->field('status')->limit(1)->select();
        if (empty($result))
            return xml(['return_code' => 'FAIL', 'return_msg' => '订单无效']);
        if ($result[0]['status'])
            return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK']);
        //订单已经付款成功

        if (!empty($requestData['sub_mch_id'])) {
            Db::table('epay_wxx_apply_list')->where('subMchID', $requestData['sub_mch_id'])->limit(1)->update([
                'lastPayTime' => getDateTime()
            ]);
        }

        Db::table('epay_order')->where('tradeNo=:tradeNo', ['tradeNo' => $requestData['out_trade_no']])->limit(1)->update([
            'status'  => 1,
            'endTime' => getDateTime()
        ]);
        //更新订单状态
        processOrder($requestData['out_trade_no']);
        //统一处理订单
        return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK']);
    }

    /**
     * @param string $tradeNo
     * @param array $systemConfig
     * @param bool $isReservedMoneyModel
     * @return mixed
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public static function getWxxPayConfig(string $tradeNo, array $systemConfig, bool $isReservedMoneyModel = false)
    {
        $orderInfo = Db::table('epay_order')->where('tradeNo', $tradeNo)->field('uid')->limit(1)->select();
        if (empty($orderInfo))
            return [];
        $getPayConfig = PayModel::getOrderAttr($tradeNo, 'payConfig');
        if (!empty($getPayConfig)) {
            $getPayConfig = json_decode($getPayConfig, true);
            return self::buildWxxPayConfig($getPayConfig['accountID'], $getPayConfig['subMchID'], $systemConfig);
        }
        //存在预先配置
        $uid = $orderInfo[0]['uid'];

        if ($isReservedMoneyModel) {
            $isCollectiveAccount = true;
        } else {
            $isCollectiveAccount = Db::table('epay_wxx_apply_info_relate')->where('uid', $uid)->limit(1)->field('id')->select();
            $isCollectiveAccount = empty($isCollectiveAccount);
            //判断是否为集体号
        }

        if (!$isCollectiveAccount) {
            $userAccountList = Db::table('epay_wxx_apply_info')->limit(1)
                ->leftJoin('epay_wxx_apply_list', 'epay_wxx_apply_list.applyInfoID = epay_wxx_apply_info.id')
                ->leftJoin('epay_wxx_apply_info_relate', '`epay_wxx_apply_info_relate`.applyInfoID = `epay_wxx_apply_info`.`id`')
                ->field('epay_wxx_apply_list.accountID,epay_wxx_apply_info.idCardName,epay_wxx_apply_list.subMchID,epay_wxx_apply_list.tempMoney,epay_wxx_apply_list.rounds')->where([
                    'epay_wxx_apply_info_relate.uid' => $uid,
                    'epay_wxx_apply_info.type'       => 2,
                    'epay_wxx_apply_list.status'     => 2
                ])->order('epay_wxx_apply_list.rounds asc,epay_wxx_apply_list.tempMoney asc')->select();
            if (empty($userAccountList))
                return [];

//            {
//                $reservedMoneyTarget = intval(getPayUserAttr($uid, 'reservedMoney'));
//                //目标预留金额
//                if (!empty($reservedMoneyTarget)) {
//                    $todayTotalReservedMoney = DataModel::getData($uid . '_reservedMoney_total', getDateTime(true));
//                    if (!$todayTotalReservedMoney[0])
//                        $todayTotalReservedMoney = 0;
//                    else
//                        $todayTotalReservedMoney = floatval($todayTotalReservedMoney[1]) / 100;
//                    //获取金额已经预留金额
//
//                    if ($todayTotalReservedMoney < $reservedMoneyTarget) {
//                        $randRules = ['a' => 40, 'b' => 60];
//                        //b => 独立号 a => 集体号
//                        if (getRand($randRules) == 'a') {
//                            PayModel::removeOrderAttr($tradeNo, 'rateMoney');
//                            return self::getWxxPayConfig($tradeNo, $systemConfig, true);
//                            //已经达到预留标准
//                        }
//                        //尚未达到预留金额标准
//                    }
//                }
//            }

            //独立号 判断是否需要压款部分逻辑

            PayModel::setOrderAttr($tradeNo, 'payConfig', json_encode(['accountID' => $userAccountList[0]['accountID'], 'subMchID' => $userAccountList[0]['subMchID'], 'configType' => 2]));
            return self::buildWxxPayConfig($userAccountList[0]['accountID'], $userAccountList[0]['subMchID'], $systemConfig);
            //独立号
        } else {
            $userAccountList = Db::table('epay_wxx_apply_info')->limit(1)
                ->leftJoin('epay_wxx_apply_list', 'epay_wxx_apply_list.applyInfoID = epay_wxx_apply_info.id')
                ->field('epay_wxx_apply_list.accountID,epay_wxx_apply_info.idCardName,epay_wxx_apply_list.subMchID')->where([
                    'epay_wxx_apply_info.type'   => 1,
                    'epay_wxx_apply_list.status' => 2
                ])->order('epay_wxx_apply_list.rounds asc,epay_wxx_apply_list.tempMoney asc')->select();
            //集体号
            if (empty($userAccountList))
                return [];
            $data = ['accountID' => $userAccountList[0]['accountID'], 'subMchID' => $userAccountList[0]['subMchID'], 'configType' => 1];
            if ($isReservedMoneyModel) {
                $data['isReservedMoneyModel'] = true;
            }
            PayModel::setOrderAttr($tradeNo, 'payConfig', json_encode($data));
            return self::buildWxxPayConfig($userAccountList[0]['accountID'], $userAccountList[0]['subMchID'], $systemConfig);
        }
    }

    /**
     * @param int $accountID
     * @param int $subMchID
     * @param array $systemConfig
     * @return mixed
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function buildWxxPayConfig(int $accountID, int $subMchID, array $systemConfig)
    {
        $selectResult = Db::table('epay_wxx_account_list')->where('id', $accountID)->field('appID,mchID,appKey,appSecret')->limit(1)->select();
        if (empty($selectResult))
            throw new Exception('数据结构异常');
        $returnData = $systemConfig['wxpay'];

        $returnData['key']   = $selectResult[0]['appKey'];
        $returnData['appid'] = $selectResult[0]['appID'];
        $returnData['mchid'] = $selectResult[0]['mchID'];

        $returnData['jsApiAppid']     = $selectResult[0]['appID'];
        $returnData['jsApiMchid']     = $selectResult[0]['mchID'];
        $returnData['jsApiKey']       = $selectResult[0]['appKey'];
        $returnData['jsApiAppSecret'] = $selectResult[0]['appSecret'];

        $returnData['sub_mch_id'] = $subMchID;
        return $returnData;
    }
}
