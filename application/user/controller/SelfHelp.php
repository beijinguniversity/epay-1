<?php

namespace app\user\controller;

use think\Controller;
use think\Db;
use tools\Geetest;

class SelfHelp extends Controller
{
    public function searchOrderTemplate()
    {
        return $this->fetch('/SelfHelp/SearchOrder');
    }

    /**
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function postOrderInfo()
    {
        $tradeNo = input('post.tradeNo/s');
        if (empty($tradeNo))
            return json(['status' => 0, 'msg' => '订单号码不能为空']);

        $config = getConfig();
        $data   = [
            'client_type' => $this->request->isMobile() ? 'h5' : 'web',
            'ip_address'  => $this->request->ip()
        ];
        $gtSDK  = new Geetest($config['geetestCaptchaID'], $config['geetestPrivateKey']);
        if (session('gtServerStatus')) {
            $result = $gtSDK->success_validate(input('post.geetest_challenge'), input('post.geetest_validate'), input('post.geetest_seccode'), $data);
        } else {
            $result = $gtSDK->fail_validate(input('post.geetest_challenge'), input('post.geetest_validate'), input('post.geetest_seccode'));
        }
        if (!$result)
            return json(['status' => 0, 'msg' => '还没有通过人机验证']);

        //防止cc
        $connectMysql = [
            'mysql://root:root@127.0.0.1:3306/epay#utf8mb4'
        ];
        $connectStr   = null;
        $searchResult = null;
        foreach ($connectMysql as $value) {

            $connectStr   = $value;
            $searchResult = Db::connect($value)->table('epay_order')->where('tradeNo=:tradeNo or tradeNoOut=:tradeNo1')->bind(['tradeNo' => $tradeNo, 'tradeNo1' => $tradeNo])->limit(1)->select();
            if (!empty($searchResult))
                break;
        }
        if (empty($searchResult))
            return json(['status' => 0, 'msg' => '订单不存在,请重试']);
        $uid      = $searchResult[0]['uid'];
        $userInfo = Db::connect($connectStr)->table('epay_user')->where('id', $uid)->field('qq,domain')->limit(1)->select();
        if (empty($userInfo))
            $userInfo[] = ['qq' => '商户不存在', 'doamin' => '商户不存在'];
        unset($searchResult[0]['isShield']);
        unset($searchResult[0]['ipv4']);
        unset($searchResult[0]['return_url']);
        unset($searchResult[0]['notify_url']);
        unset($searchResult[0]['uid']);
        unset($searchResult[0]['id']);

        $returnData = $searchResult[0];

        $returnData['webUrl'] = $userInfo[0]['domain'];
        $returnData['chatID'] = $userInfo[0]['qq'];

        return json([
            'status' => 1,
            'data'   => $returnData
        ]);
    }
}