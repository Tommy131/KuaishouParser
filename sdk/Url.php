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
namespace application\kuai\sdk;

interface Url
{
	/**
	 * ~站点 'live.kuaishou.com' 的数据接口
	 *
	 * @var string
	 */
	public const GRAPHQL_LIVE = 'https://live.kuaishou.com/live_graphql';

	/**
	 * ~站点 'www.kuaishou.com' 的数据接口
	 *
	 * @var string
	 */
	public const GRAPHQL_WWW = 'https://www.kuaishou.com/graphql';

	/**
	 * ~分享ID的数据请求接口
	 *
	 * @var string
	 */
	public const ARTICLE_DATA = 'https://v.m.chenzhongtech.com/rest/wd/photo/info';

	/**
	 * ~二维码登录接口基本地址
	 *
	 * @var string
	 */
	public const QR_CODE_LOGIN = 'https://id.kuaishou.com/rest/c/infra/ks/qr/';

	/**
	 * ~二维码登录回调接口
	 *
	 * @var string
	 */
	public const QR_CODE_CALLBACK = 'https://id.kuaishou.com/pass/kuaishou/login/qr/callback';

	/**
	 * ~获取 `web_ph` 值的请求接口 (需要基于登录成功的状态下)
	 *
	 * @var string
	 */
	public const WEB_PH_REQUEST = 'https://www.kuaishou.com/rest/infra/sts';

	/**
	 * ~账号登出请求地址 (需要携带Cookies)
	 *
	 * @var string
	 */
	public const LOGOUT = 'https://id.kuaishou.com/pass/kuaishou/login/logout';
}