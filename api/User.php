<?php

/*********************************************************************
     _____   _          __  _____   _____   _       _____   _____
    /  _  \ | |        / / /  _  \ |  _  \ | |     /  _  \ /  ___|
    | | | | | |  __   / /  | | | | | |_| | | |     | | | | | |
    | | | | | | /  | / /   | | | | |  _  { | |     | | | | | |  _
    | |_| | | |/   |/ /    | |_| | | |_| | | |___  | |_| | | |_| |
    \_____/ |___/|___/     \_____/ |_____/ |_____| \_____/ \_____/

    * Copyright (c) 2015-2021 OwOBlog-DGMT.
    * Developer: HanskiJay(Tommy131)
    * Telegram:  https://t.me/HanskiJay
    * E-Mail:    support@owoblog.com
    * GitHub:    https://github.com/Tommy131

**********************************************************************/
declare(strict_types=1);
namespace application\kuai\api;

use application\kuai\KuaiApp as Kuai;
use application\kuai\api\Graphql;
use owoframe\System;

class User
{
    /**
     * 平台识别的关键Key
     *
     * @var array
     */
    private static $list =
    [
        'live' => 'kuaishou.live.web',
        'www'  => 'kuaishou.server.web'
    ];

    /**
     * 默认请求头
     *
     * @var array
     */
    private static $headers =
    [
        'Content-Type'    => 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept'          => '*/*',
        'Accept-Language' => 'zh-CN,zh;q=0.8,zh-TW;q=0.7,zh-HK;q=0.5,en-US;q=0.3,en;q=0.2',
        'Connection'      => 'keep-alive',
        'Sec-Fetch-Dest'  => 'empty',
        'Sec-Fetch-Mode'  => 'cors',
        'Sec-Fetch-Site'  => 'same-site',
        'Pragma'          => 'no-cache',
        'Cache-Control'   => 'no-cache'
    ];

    /**
     * 根据用户名模糊查找用户
     *
     * @param  string     $name
     * @return array|null
     */
    public static function search(string $name) : ?array
    {
        $graphql   = new Graphql;
        $operation = $graphql->getOperation();
        $graphql->setOperationName($operation->getName('searchEID'))->setVariables(['keyword' => $name])->setQuery($operation->getQuery('searchEID'));
        $result    = $graphql->sendQuery(Urls::GRAPHQL_WWW, Kuai::getCookies('www'))->getResult();
        $result    = $result->visionSearchUser ?? null;
        return ($result && !empty($result->users)) ? $result->users : null;
    }

    /**
     * 根据用户名查找用户
     *
     * @param  string     $userId
     * @return array|null
     */
    public static function queryUserId(string $userId) : ?array
    {
        $graphql   = new Graphql;
        $operation = $graphql->getOperation();
        $graphql->setOperationName($operation->getName('principalData'))->setVariables(['userId' => $userId])->setQuery($operation->getQuery('principalData'));
        $result = $graphql->sendQuery(Urls::GRAPHQL_WWW, Kuai::getCookies('www'))->getResult();
        $result = $result->visionProfile->userProfile->profile ?? null;

        if(is_null($result)) {
            return null;
        }
        $_ = [];

        $_['gender']      = $result->gender;
        $_['displayName'] = $result->user_name;
        $_['description'] = $result->user_text;
        $_['description'] = !$_['description'] ? 'N/A' : $_['description'];

        $operation->setPlatform('live');
        $graphql->setOperationName($operation->getName('principalData'))->setVariables(['principalId' => $userId])->setQuery($operation->getQuery('principalData'));
        $result = $graphql->sendQuery(Urls::GRAPHQL_LIVE, Kuai::getCookies('live'))->getResult();
        $result = $result->sensitiveUserInfo ?? null;

        if(is_null($result)) {
            return null;
        }

        $_['kwaiId']        = $result->kwaiId ?? 'N/A';
        $_['originUserId']  = $result->originUserId;
        $_['constellation'] = $result->constellation;
        $_['constellation'] = !$_['constellation'] ? 'N/A' : $_['constellation'];
        $_['cityName']      = $result->cityName ?? 'N/A';

        $_['fansCount']     = $result->counts->fan;
        $_['feedCount']     = $result->counts->photo;
        $_['privateCount']  = $result->counts->private ?? 'N/A';

        return $_;
    }

    /**
     * 获取登录使用的二维码
     *
     * @param  string $platform
     * @param  &      $errorMessage
     * @return boolean
     */
    public static function login(string $platform = 'www', &$errorMessage = '') : bool
    {
        $platform = strtolower($platform);
        if(!isset(self::$list[$platform])) {
            $errorMessage = '不支持的平台!';
            return false;
        }
        $loginUrl    = Urls::QR_CODE_LOGIN . 'start';
        $resultUrl   = Urls::QR_CODE_LOGIN . 'scanResult';
        $acceptUrl   = Urls::QR_CODE_LOGIN . 'acceptResult';

        System::getLogger()->info("Request log in Platform: {$platform}");

        $headers           = self::$headers;
        $headers['Origin'] = "https://{$platform}.kuaishou.com";
        $verifyResultCode  = fn(int $code) => ($code === 1);

        $curl = Kuai::initCurl()->setUrl($loginUrl)->setHeaders($headers)->setPostData(['sid' => self::$list[$platform]])->exec();
        $_    = $curl->decodeWithJson(false);

        if(!is_array($_) || !$verifyResultCode($_['result'])) {
            $errorMessage = '请求错误!';
            return false;
        }
        extract($_);
        $tmpPath    = SAVE_PATH . 'login_temp' . DIRECTORY_SEPARATOR;
        $nameFormat = 'login_QRCode_%s.png';
        if(!is_dir($tmpPath)) mkdir($tmpPath, 755, true);

        // 写入二维码到文件;
        $path = $tmpPath . sprintf($nameFormat, 'www');
        if(is_file($path)) unlink($path);
        file_put_contents($path, base64_decode($imageData));

        if(System::getOS() === 'windows') {
            system('start ' . $path);
        } else {
            System::getLogger()->info("二维码已保存在路径 '{$path}' 中, 请手动打开此文件.");
        }
        System::getLogger()->info("当前Token: §2qrLoginToken=§6{$qrLoginToken}§w|§2qrLoginSignature=§6{$qrLoginSignature}");
        ask('请打开快手客户端扫码, 不要点击确定登录!!! 完成操作请回车 [ENTER]:', 'OK', true, 'warning');
        if(microtime(true) > round($expireTime / 1000, 3)) {
            $errorMessage = '二维码已失效, 请重试.';
            return false;
        }

        $baseData = [
            'channelType'      => $channelType ?? 'UNKNOWN',
            'encryptHeaders'   => $encryptHeaders ?? ''
        ];

        $curl = Kuai::initCurl()->setUrl($resultUrl)->setHeaders($headers)->setPostData($postData = array_merge($baseData, [
            'qrLoginToken'     => $qrLoginToken ?? 'UNKNOWN',
            'qrLoginSignature' => $qrLoginSignature ?? 'UNKNOWN',
        ]))->exec();
        $_ = $curl->decodeWithJson();

        if(!is_object($_) || !$verifyResultCode($_->result)) {
            $errorMessage = (isset($_->result) && ($_->result === 707)) ? '是不是手速太快已经点了授权登录? 请重试.' : '请扫描二维码!';
            return false;
        }

        $eid     = $_->user->eid;
        $version = Kuai::getVersion();
        System::getLogger()->info("欢迎使用KuaishouParser v{$version}! GitHub: https://github.com/Tommy131/KuaishouParser");
        System::getLogger()->info("如果本程序有帮到你, 请给此项目一个Star以鼓励我继续开发 :)\n");
        System::getLogger()->success("登录成功! 欢迎你, §6{$_->user->user_name} §w[§3eid:§7{$eid}§w]§5!\n");

        ask('现在请点击授权登录! 完成操作请回车 [ENTER]:', 'OK', true, 'notice');
        $curl = Kuai::initCurl()->setUrl($acceptUrl)->setHeaders($headers)->setPostData(array_merge($postData, ['sid' => $sid]))->exec();
        $_    = $curl->decodeWithJson();

        if(!is_object($_) || !$verifyResultCode($_->result)) {
            $errorMessage = '请授权登录!';
            return false;
        }

        $curl = Kuai::initCurl()->setUrl(Urls::QR_CODE_CALLBACK)->setHeaders($headers)->setPostData(array_merge($baseData, ['sid' => $sid, 'qrToken' => $_->qrToken]))->exec();

        $_ = 'kuaishou.%s.';
        $_ = sprintf($_, ($platform === 'www') ? 'server' : 'live');
        $web_ph_tag = $_ .'web_ph';
        $web_st_tag = $_ .'web_st';
        $web_at_tag = $_ .'web.at';

        $_ = $curl->decodeWithJson(false);

        $userId     = $_['userId'];
        $web_st     = $_[$web_st_tag];
        $authToken  = $_[$web_at_tag];

        if(!is_array($_) || !$verifyResultCode($_['result'])) {
            $errorMessage = '请求错误!';
            return false;
        }

        // 获取 web_ph;
        if($platform === 'www') {
            $curl = Kuai::initCurl()->setUrl(Urls::WEB_PH_REQUEST)->setHeaders($headers)->returnHeader(true)->setPostData([
                'authToken' => $authToken,
                'sid' => $sid
            ])->exec();
            $cookies = $curl->getCookies();
            $web_ph  = $cookies[$web_ph_tag];
        } else {
            $graphql   = new Graphql;
            $operation = $graphql->getOperation()->setPlatform($platform);
            $object    = $graphql->setOperationName($operation->getName('login'))->setQuery($operation->getQuery('login'));
            $object->setVariables(['userLoginInfo' => [
                'authToken' => $authToken,
                'sid' => $sid
            ]]);
            $object->beforeRequestCallback = function($curl) {
                $curl->returnHeader(true);
            };

            $result = $object->sendQuery(Urls::GRAPHQL_LIVE)->getResult();
            $result = $result->webLogin->result ?? false;
            if($result !== 1) {
                $errorMessage = '登录出错啦, 该账号可能已经登录过了, 请在服务器会话过期之前换一个账号试一下吧~';
                return false;
            }
            $cookies = $object->getCurl()->getCookies();
            $web_st  = $cookies[$web_st_tag];
            $web_ph  = $cookies[$web_ph_tag];
        }

        $commonCookies = [
            'userId'    => $userId,
            $web_st_tag => $web_st,
            $web_ph_tag => $web_ph
        ];

        // 获取did;
        $curl    = Kuai::initCurl()->setUrl("https://{$platform}.kuaishou.com/profile/{$eid}")->setHeaders($headers)->returnHeader(true)->setCookies($commonCookies)->exec();
        $cookies = $curl->getCookies();
        if(empty($cookies)) {
            $errorMessage = 'Cookies获取异常, 请重试.';
            return false;
        }
        $cookies = array_merge($cookies, $commonCookies);

        $cookiesStr = '';
        foreach($cookies as $k => $v) {
            $cookiesStr .= "{$k}={$v}; ";
        }
        $cookiesStr = trim($cookiesStr, '; ');
        Kuai::getConfig()->set("cookies.{$platform}", $cookiesStr);
        Kuai::getConfig()->save();
        System::getLogger()->success("已成功更新本地Cookies! §wPlatform: {$platform}");
        return true;
    }

    /**
     * 注销Cookies行为
     *
     * @param  string $platform
     * @return boolean
     */
    public static function logout(string $platform = 'www') : bool
    {
        $platform = strtolower($platform);
        if(isset(self::$list[$platform])) {
            $curl = Kuai::initCurl()->setUrl(Urls::LOGOUT)->setHeaders(self::$headers)->setPostData(['sid' => self::$list[$platform]])->returnHeader(true)->setCookiesInRaw(Kuai::getCookies($platform))->exec();
            $____ = json_decode($curl->getContent());
            if($____->result === 1) {
                Kuai::getConfig()->set("cookies.{$platform}", '(cookies: string)');
                Kuai::getConfig()->save();
                return true;
            }
        }
        return false;
    }
}