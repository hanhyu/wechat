<?php

/*
 * This file is part of the overtrue/wechat.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace EasyWeChat\Tests\Foundation\Core;

use EasyWeChat\Tests\TestCase;
use Mockery as m;

class AccessTokenAtrributesTest extends TestCase
{
    protected function getMockHttp($tokenJsonKey = 'access_token')
    {
        return m::mock('EasyWeChat\Applications\Base\Core\Http[parseJSON,get]', function ($mock) use ($tokenJsonKey) {
            $mock->shouldReceive('parseJSON')->andReturnUsing(function ($requests) use ($tokenJsonKey) {
                return array_merge([
                    $tokenJsonKey => 'thisIsAToken',
                    'expires_in' => 7200,
                ], $requests);
            });
            $mock->shouldReceive('get')->andReturnUsing(function ($endpoint, $params) {
                return compact('endpoint', 'params');
            });
        });
    }

    protected function getMockCache()
    {
        return m::mock('Doctrine\Common\Cache\Cache', function ($mock) {
            $mock->shouldReceive('fetch')->andReturn('thisIsAToken');
        });
    }

    public function getOfficialAccount(...$args)
    {
        $instance = new \EasyWeChat\Applications\OfficialAccount\Core\AccessToken(...$args);

        return $instance->setHttp($this->getMockHttp())->setCache($this->getMockCache());
    }

    public function getMiniProgram(...$args)
    {
        $instance = new \EasyWeChat\Applications\MiniProgram\AccessToken(...$args);

        return $instance->setHttp($this->getMockHttp())->setCache($this->getMockCache());
    }

    public function getOpenPlatform(...$args)
    {
        $instance = new \EasyWeChat\Applications\OpenPlatform\Core\AccessToken(...$args);

        $verifyTicket = new \EasyWeChat\Applications\OpenPlatform\Core\VerifyTicket('appid', new \Doctrine\Common\Cache\ArrayCache());
        $verifyTicket->setTicket('ticket@foobar');

        return $instance->setHttp($this->getMockHttp('component_access_token'))->setCache($this->getMockCache())->setVerifyTicket($verifyTicket);
    }

    public function testClientIdAndClientSecret()
    {
        $officialAccount = $this->getOfficialAccount('app-id', 'app-secret');

        $this->assertSame('app-id', $officialAccount->getClientId());
        $this->assertSame('app-secret', $officialAccount->getClientSecret());

        $miniProgram = $this->getMiniProgram('mini-app-id', 'mini-secret');

        $this->assertSame('mini-app-id', $miniProgram->getClientId());
        $this->assertSame('mini-secret', $miniProgram->getClientSecret());

        $openPlatform = $this->getOpenPlatform('open-app-id', 'open-secret');

        $this->assertSame('open-app-id', $openPlatform->getClientId());
        $this->assertSame('open-secret', $openPlatform->getClientSecret());
    }

    public function testGetQueryName()
    {
        $officialAccount = $this->getOfficialAccount('app-id', 'app-secret');
        $miniProgram = $this->getMiniProgram('mini-app-id', 'mini-secret');
        $openPlatform = $this->getOpenPlatform('open-app-id', 'open-secret');

        $this->assertSame('access_token', $officialAccount->getQueryName());
        $this->assertSame('access_token', $miniProgram->getQueryName());
        $this->assertSame('component_access_token', $openPlatform->getQueryName());
    }

    public function testGetQueryFields()
    {
        $officialAccount = $this->getOfficialAccount('app-id', 'app-secret');
        $miniProgram = $this->getMiniProgram('mini-app-id', 'mini-secret');
        $openPlatform = $this->getOpenPlatform('open-app-id', 'open-secret');

        $this->assertSame(['access_token' => 'thisIsAToken'], $officialAccount->getQueryFields());
        $this->assertSame(['access_token' => 'thisIsAToken'], $miniProgram->getQueryFields());
        $this->assertSame(['component_access_token' => 'thisIsAToken'], $openPlatform->getQueryFields());
    }

    public function testGetCacheKey()
    {
        $officialAccount = $this->getOfficialAccount('app-id', 'app-secret');
        $miniProgram = $this->getMiniProgram('mini-app-id', 'mini-secret');
        $openPlatform = $this->getOpenPlatform('open-app-id', 'open-secret');

        $this->assertSame('easywechat.common.access_token.app-id', $officialAccount->getCacheKey());
        $this->assertSame('easywechat.common.mini.program.access_token.mini-app-id', $miniProgram->getCacheKey());
        $this->assertSame('easywechat.open_platform.component_access_token.open-app-id', $openPlatform->getCacheKey());
    }

    public function testGetTokenFromServer()
    {
        $officialAccountResult = $this->getOfficialAccount('app-id', 'app-secret')->getTokenFromServer();
        $miniProgramResult = $this->getMiniProgram('mini-app-id', 'mini-secret')->getTokenFromServer();
        $openPlatformResult = $this->getOpenPlatform('open-app-id', 'open-secret')->getTokenFromServer();

        $this->assertSame('https://api.weixin.qq.com/cgi-bin/token', $officialAccountResult['endpoint']);
        $this->assertSame([
                'appid' => 'app-id',
                'secret' => 'app-secret',
                'grant_type' => 'client_credential',
            ], $officialAccountResult['params']
        );

        $this->assertSame('https://api.weixin.qq.com/cgi-bin/token', $miniProgramResult['endpoint']);
        $this->assertSame([
                'appid' => 'mini-app-id',
                'secret' => 'mini-secret',
                'grant_type' => 'client_credential',
            ], $miniProgramResult['params']
        );

        $this->assertSame('https://api.weixin.qq.com/cgi-bin/component/api_component_token', $openPlatformResult['endpoint']);
        $this->assertSame([
                'component_appid' => 'open-app-id',
                'component_appsecret' => 'open-secret',
                'component_verify_ticket' => 'ticket@foobar',
            ], $openPlatformResult['params']
        );
    }
}
