<?php
/*
 *       _____   _          __  _____   _____   _       _____   _____
 *     /  _  \ | |        / / /  _  \ |  _  \ | |     /  _  \ /  ___|
 *     | | | | | |  __   / /  | | | | | |_| | | |     | | | | | |
 *     | | | | | | /  | / /   | | | | |  _  { | |     | | | | | |   _
 *     | |_| | | |/   |/ /    | |_| | | |_| | | |___  | |_| | | |_| |
 *     \_____/ |___/|___/     \_____/ |_____/ |_____| \_____/ \_____/
 *
 * Copyright (c) 2023 by OwOTeam-DGMT (OwOBlog).
 * @Author       : HanskiJay
 * @Date         : 2023-02-22 16:14:31
 * @LastEditors  : HanskiJay
 * @LastEditTime : 2023-02-23 20:29:15
 * @E-Mail       : support@owoblog.com
 * @Telegram     : https://t.me/HanskiJay
 * @GitHub       : https://github.com/Tommy131
 */
declare(strict_types=1);
namespace module\kuai\query;



use Error;
use module\kuai\KuaishouParser as Kuai;
use owoframe\http\Curl;

class AccountQuery
{
    use QueryTrait;

    public const EMPTY = -1;

    /**
     * 开始请求登录二维码的API
     */
    public const START = 0;

    /**
     * 二维码扫描结果API
     */
    public const SCAN_RESULT = 1;

    /**
     * 接受结果API
     */
    public const ACCEPT_RESULT = 2;

    /**
     * 扫码请求后回调
     */
    public const CALLBACK = 3;

    /**
     * 请求登录API
     */
    public const LOGIN = 4;

    /**
     * 请求注销 / 登出账号API
     */
    public const LOGOUT = 5;

    /**
     * 请求注销 / 登出账号API
     */
    public const LOGOUT_ID = 6;

    /**
     * 获取Cookie参数 `DID`
     */
    public const GET_DID = 7;

    /**
     * 请求的API集合
     */
    public const APIS =
    [
        self::EMPTY         => '',
        self::START         => API::ACCOUNT_QR_CODE_SCAN . 'start',
        self::SCAN_RESULT   => API::ACCOUNT_QR_CODE_SCAN . 'scanResult',
        self::ACCEPT_RESULT => API::ACCOUNT_QR_CODE_SCAN . 'acceptResult',
        self::CALLBACK      => API::ACCOUNT_QR_CODE_CALLBACK,
        self::LOGIN         => API::ACCOUNT_LOGIN,
        self::LOGOUT        => API::ACCOUNT_LOGOUT,
        self::LOGOUT_ID     => API::ACCOUNT_LOGOUT_ID,
        self::GET_DID       => 'https://{platform}.kuaishou.com/profile/{eid}'
    ];

    /**
     * 登录平台
     *
     * @var integer
     */
    protected $platform = API::TAG_LIVE;

    /**
     * 请求头
     *
     * @var array
     */
    protected $header = API::DEFAULT_HEADER;

    /**
     * 请求的Cookies
     *
     * @var array
     */
    protected $cookies = [];


    /**
     * 构造方法
     *
     * @param  Kuai    $module
     * @param  integer $platform
     */
    public function __construct(Kuai $module, int $platform = API::TAG_LIVE)
    {
        $this->setModule($module);
        $this->setPlatform($platform);
    }

    /**
     * 返回平台类型
     *
     * @return integer
     */
    public function getPlatform() : int
    {
        return $this->platform ?? API::TAG_LIVE;
    }

    /**
     * 设置登录平台
     *
     * @param  integer    $platform
     * @return AccountQuery
     */
    public function setPlatform(int $platform) : AccountQuery
    {
        if(!API::hasPlatform($platform)) {
            $platform = API::TAG_LIVE;
        }
        $this->platform = $platform;
        return $this;
    }

    /**
     * 设置来源站点
     *
     * @param  string|null $_
     * @return AccountQuery
     */
    public function setOrigin(?string $_ = null) : AccountQuery
    {
        if(!$_) {
            $_ = 'https://' . API::shortPlatformName($this->platform) . '.kuaishou.com';
        }
        $this->header['Origin']  = $_;
        $this->header['Referer'] = $_;
        return $this;
    }

    /**
     * 请求API站点
     *
     * @param  integer $type
     * @param  boolean $update
     * @return Curl
     */
    private function request(int $type, bool $update = true) : Curl
    {
        if(!isset(self::APIS[$type])) {
            throw new Error('无法解析请求目标的API!');
        }
        return $this->curl(null, $update)->setUrl(self::APIS[$type])->setHeaders($this->header)->setCookies($this->cookies);
    }

    /**
     * 写入二维码到文件
     *
     * @param  string  $stream
     * @return AccountQuery
     */
    private function putQrCode(string $stream) : AccountQuery
    {
        $path = Kuai::defaultStoragePath('tmp_login_QRCode.png');
        if(file_put_contents($path, base64_decode($stream)) !== false) {
            if(\owo\get_os() === OS_WINDOWS) {
                system('start ' . $path);
            } else {
                $this->getLogger()->info(Kuai::LOG_PREFIX . "二维码已保存在路径 '{$path}' 中, 请手动打开此文件.");
            }
        } else {
            $this->getLogger()->error(Kuai::LOG_PREFIX . '二维码保存失败!');
        }
        return $this;
    }

    /**
     * 验证请求结果是否有效
     *
     * @param  object|null $obj
     * @param  integer $code
     * @return boolean
     */
    public static function verifyResult(?object $obj, int $code = 1) : bool
    {
        return is_object($obj) && ((isset($obj->result) && ($obj->result === $code)) || (isset($obj->data->result) && ($obj->data->result === $code)));
    }

    /**
     * 请求登录站点模拟扫描二维码操作
     *
     * @param  mixed   $errorMessage
     * @return boolean
     */
    public function login(&$errorMessage = null) : bool
    {
        $platform = API::shortPlatformName($this->platform);
        $this->setOrigin();

        // ~ 请求获取DID
        $curl = $this->request(self::EMPTY)
        ->setUrl(str_replace(['{platform}', '{eid}'], [$platform, 'newscctv'], self::APIS[self::GET_DID]))
        ->returnHeader(true)
        ->exec();

        $this->cookies = $curl->getCookies();
        if(empty($this->cookies)) {
            $errorMessage = '[0x000] Cookies获取异常, 请重试.';
            return false;
        }

        // ~ 请求创建 / 获取二维码
        $curl = $this->request(self::START)
        ->setPostData(['sid' => API::platform($this->platform)])
        ->exec();
        $_ = $curl->decodeWithJson();

        // 判断是否存在请求结果
        if(!self::verifyResult($_)) {
            $errorMessage = '[0x001] 请求失败, 请稍后重试!';
            return false;
        }
        $sid = $_->sid;

        // 展开数组并且解析数据
        $this->putQrCode($_->imageData);
        $this->getLogger()->info(Kuai::LOG_PREFIX . "当前Token: §2qrLoginToken=§6{$_->qrLoginToken}§w|§2qrLoginSignature=§6{$_->qrLoginSignature}");

        // 处理扫描二维码请求
        \owo\ask(Kuai::LOG_PREFIX . '请打开快手客户端扫描二维码, §b§3不要点击确定登录§r§w!!! 完成操作请回车 [ENTER]:');
        if(microtime(true) > round($_->expireTime / 1000, 3)) {
            $errorMessage = '[0x002] 二维码已失效, 请重试.';
            return false;
        }

        // 创建初始数据
        $baseData = [
            'channelType'    => $_->channelType ?? 'UNKNOWN',
            'encryptHeaders' => $_->encryptHeaders ?? ''
        ];

        // ~ 请求扫描结果
        $curl = $this->request(self::SCAN_RESULT)
        ->setPostData($postData = array_merge($baseData, [
            'qrLoginToken'     => $_->qrLoginToken ?? 'UNKNOWN',
            'qrLoginSignature' => $_->qrLoginSignature ?? 'UNKNOWN',
        ]))->exec();
        $_ = $curl->decodeWithJson();

        // 判断是否存在请求结果
        if(!self::verifyResult($_)) {
            $errorMessage = (isset($_->result) && ($_->result === 707)) ? '是不是手速太快已经点了授权登录? 请重试.' : '请扫描二维码!';
            return false;
        }

        // 发送登录成功欢迎信息
        $eid      = $_->user->eid;
        $userName = $_->user->user_name;
        $version  = Kuai::VERSION;
        \owo\output(PHP_EOL);
        $this->getLogger()->info(Kuai::LOG_PREFIX . "欢迎使用 KuaishouParser v{$version}!");
        $this->getLogger()->info(Kuai::LOG_PREFIX . 'GitHub: https://github.com/Tommy131/Kuai');
        $this->getLogger()->info(Kuai::LOG_PREFIX . "如果本程序有帮到你, 请给此项目一个Star以鼓励我继续开发 :)");
        $this->getLogger()->success(Kuai::LOG_PREFIX . "扫码成功! 欢迎你, §6{$userName} §w[§3eid:§7{$eid}§w]§5!");
        \owo\output(PHP_EOL);
        \owo\ask(Kuai::LOG_PREFIX . '现在请点击授权登录! 完成操作请回车 [ENTER]:');

        // ~ 接受扫码登录请求
        $curl = $this->request(self::ACCEPT_RESULT)
        ->setPostData(array_merge($postData, ['sid' => $sid]))
        ->exec();
        $_ = $curl->decodeWithJson();

        // 判断是否存在请求结果
        if(!self::verifyResult($_)) {
            $errorMessage = '[0x003] 请授权登录!';
            return false;
        }

        // ~ 请求后回调
        $curl = $this->request(self::CALLBACK)
        ->setPostData(array_merge($baseData, ['qrToken' => $_->qrToken, 'sid' => $sid]))
        ->exec();
        $_ = $curl->decodeWithJson();

        // 判断是否存在请求结果
        if(!self::verifyResult($_)) {
            $errorMessage = '[0x004] 请求失败, 请稍后重试!';
            return false;
        }

        // 创建临时Cookie
        $_tmpData   = (array) $_;
        $_tag       = sprintf('kuaishou.%s.', ($platform === 'www') ? 'server' : 'live');
        $web_st_tag = $_tag .'web_st';
        $web_at_tag = $_tag .'web.at';
        $web_ph_tag = $_tag .'web_ph';
        $userId     = $_tmpData['userId'];
        $authToken  = $_tmpData[$web_at_tag];
        // $web_st = $_tmpData[$web_st_tag];

        $this->cookies['userId'] = $userId;
        // $this->cookies[$web_st_tag] = $web_st;

        // ~ 请求登录
        $curl = $this->request(self::LOGIN)
        // ->setUrl(self::APIS[self::LOGIN] . "authToken={$authToken}&sid={$sid}")
        ->setPostData([
            'userLoginInfo' =>
            [
                'authToken' => $authToken,
                'sid'       => $sid
            ]
        ], true)
        ->setContentType('application/json')
        ->returnHeader()
        ->exec();
        $_ = $curl->decodeWithJson();
        // var_dump($_);

        // 判断是否存在请求结果
        if(!self::verifyResult($_)) {
            $errorMessage = '[0x005] 登录出错!';
            return false;
        }

        $_ = $curl->getCookies();
        $this->cookies = array_merge($this->cookies,
        [
            'userId'    => $userId,
            $web_st_tag => $_[$web_st_tag],
            $web_ph_tag => $_[$web_ph_tag]
        ]);

        // 生成Cookie串并保存到本地
        $cookiesStr = '';
        foreach($this->cookies as $k => $v) {
            $cookiesStr .= "{$k}={$v}; ";
        }
        $cookiesStr = trim($cookiesStr, '; ');
        $this->module->config()->set('cookies.' . $platform, $cookiesStr);
        $this->module->config()->save();
        $this->getLogger()->success("已成功更新本地Cookies! §wPlatform: {$platform}");
        return true;
    }

    /**
     * 登出 / 注销账号
     *
     * @return boolean
     */
    public function logout() : bool
    {
        $platform = API::shortPlatformName($this->platform);
        $sid      = ['sid' => API::PLATFORMS[$platform]];
        $cookie   = $this->module->cookie($this->platform);

        // 从平台登出
        $curl = $this->request(self::LOGOUT)
        ->setPostData($sid)
        ->returnHeader(true)
        ->setCookieRaw($cookie)
        ->exec();
        $_ = $curl->decodeWithJson();

        // 从令牌中心登出
        $curl = $this->request(self::LOGOUT_ID)
        ->setPostData($sid)
        ->returnHeader(true)
        ->setCookieRaw($cookie)
        ->exec();
        $__ = $curl->decodeWithJson();

        if(self::verifyResult($_) && self::verifyResult($__)) {
            $this->module->config()->set('cookies.' . $platform, '');
            $this->module->config()->save();
            $this->getLogger()->success("已退出账号! §wPlatform: {$platform}");
            return true;
        }
        $this->getLogger()->error("退出账号失败! §wPlatform: {$platform}");
        return false;
    }
}
?>