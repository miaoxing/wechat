<?php

namespace MiaoxingTest\Wechat\Controller;

use Wei\WeChatApp;

class WechatTest extends \Miaoxing\Plugin\Test\BaseControllerTestCase
{
    public function setUp()
    {
        parent::setUp();
        wei()->app->setNamespace('test');

        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'authed' => false,
            'applicationId' => 'wxbad0b45542aa0b5e',
            'token' => 'weixin',
            'encodingAesKey' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG',
        ]);

        $reply = wei()->weChatReply()->findOrInitById('default');
        $reply->destroy();

        $reply = wei()->weChatReply()->findOrInitById('default');
        $reply->save([
            'keywords' => '默认回复',
            'matchType' => '1',
            'type' => 'text',
            'content' => '123456',
            'accountId' => $account['id'],
        ]);

        $reply = wei()->weChatReply()->findOrInitById('9596675');
        $reply->save([
            'keywords' => '12345',
            'matchType' => '1',
            'type' => 'text',
            'content' => '54321',
            'accountId' => $account['id'],
        ]);
    }

    public function testTransferCustomer()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'transferCustomer' => 1,
        ]);
        $response = wei()->tester->weChatReply('<xml>
                        <ToUserName><![CDATA[toUser]]></ToUserName>
                        <FromUserName><![CDATA[fromUser]]></FromUserName>
                        <CreateTime>12345678</CreateTime>
                        <MsgType><![CDATA[text]]></MsgType>
                        <Content><![CDATA[你好]]></Content>
                        </xml>');

        $this->assertContains('<MsgType><![CDATA[transfer_customer_service]]></MsgType>', $response);

        $account->setData([
            'transferCustomer' => 0,
        ]);
        $response = wei()->tester->weChatReply('<xml>
                        <ToUserName><![CDATA[toUser]]></ToUserName>
                        <FromUserName><![CDATA[fromUser]]></FromUserName>
                        <CreateTime>12345678</CreateTime>
                        <MsgType><![CDATA[text]]></MsgType>
                        <Content><![CDATA[你好]]></Content>
                        </xml>');
        $this->assertContains('<MsgType><![CDATA[text]]></MsgType>', $response);
    }

    public function testVerifyToken()
    {
        $randEchoStr = 'test' . mt_rand(1, 100000);
        wei()->weChatApp = new \Wei\WeChatApp([
            'wei' => $this->wei,
            'query' => [
                'echostr' => $randEchoStr,
                'signature' => 'c181f86196a54f1813399ddb4c36ae34af043415',
                'timestamp' => '1366032735',
                'nonce' => '136587223',
            ],
            'token' => 'weixin',
            'postData' => '',
        ]);

        $res = $this->dispatch('wechat', 'reply');
        $this->assertEquals($randEchoStr, $res->getContent());
    }

    public function testInvalidToken()
    {
        $ret = wei()->tester()
            ->controller('wechat')
            ->action('reply')
            ->request(['accountId' => '768861673'])
            ->json()
            ->exec()
            ->response();

        $this->assertEquals(-1, $ret['code']);
        $this->assertEquals('Token不正确', $ret['message']);
    }

    public function testEncryptReply()
    {
        //排序
        $timestamp = '1414243737';
        $nonce = '1792106704';
        $msg_signature = '6147984331daf7a1a9eed6e0ec3ba69055256154';
        $signature = '35703636de2f9df2a77a662b68e521ce17c34db4';

        wei()->weChatApp = new \Wei\WeChatApp([
            'wei' => $this->wei,
            'query' => [
                'encrypt_type' => 'aes',
                'msg_signature' => $msg_signature,
                'signature' => $signature,
                'timestamp' => $timestamp,
                'nonce' => $nonce,
            ],
            'token' => 'weixin',
            'appId' => 'wxbad0b45542aa0b5e',
            'encodingAesKey' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG',
            'postData' => '<xml>
    <ToUserName><![CDATA[gh_680bdefc8c5d]]></ToUserName>
    <Encrypt><![CDATA[MNn4+jJ/VsFh2gUyKAaOJArwEVYCvVmyN0iXzNarP3O6vXzK62ft1/KG2/XPZ4y5bPWU/jfIfQxODRQ7sLkUsrDRqsWimuhIT8Eq+w4E/28m+XDAQKEOjWTQIOp1p6kNsIV1DdC3B+AtcKcKSNAeJDr7x7GHLx5DZYK09qQsYDOjP6R5NqebFjKt/NpEl/GU3gWFwG8LCtRNuIYdK5axbFSfmXbh5CZ6Bk5wSwj5fu5aS90cMAgUhGsxrxZTY562QR6c+3ydXxb+GHI5w+qA+eqJjrQqR7u5hS+1x5sEsA7vS+bZ5LYAR3+PZ243avQkGllQ+rg7a6TeSGDxxhvLw+mxxinyk88BNHkJnyK//hM1k9PuvuLAASdaud4vzRQlAmnYOslZl8CN7gjCjV41skUTZv3wwGPxvEqtm/nf5fQ=]]></Encrypt>
</xml>',
        ]);

        $res = $this->dispatch('wechat', 'reply');
        $useErrors = libxml_use_internal_errors(true);
        $attrs = simplexml_load_string($res->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_use_internal_errors($useErrors);
        $map = array_map('strval', (array) $attrs);

        //测试后台有默认回复消息
        $this->assertTrue(isset($map['Encrypt']));
        $this->assertTrue(isset($map['MsgSignature']));
        $this->assertTrue(isset($map['TimeStamp']));
        $this->assertTrue(isset($map['Nonce']));
    }

    public function testSubscribe()
    {
        wei()->tester->weChatReply('<xml>
                    <ToUserName><![CDATA[toUser]]></ToUserName>
                    <FromUserName><![CDATA[fromUser]]></FromUserName>
                    <CreateTime>1366131865</CreateTime>
                    <MsgType><![CDATA[event]]></MsgType>
                    <Event><![CDATA[subscribe]]></Event>
                    <EventKey><![CDATA[]]></EventKey>
                 </xml>');

        $user = wei()->user()->find(['wechatOpenId' => 'fromUser']);

        $this->assertTrue((bool) $user['isValid']);
    }

    public function testSubscribeWithQrcodeId()
    {
        $user = wei()->user()->save([
            'wechatOpenId' => wei()->seq(),
        ]);
        $sceneId = wei()->seq();

        wei()->tester->wechatReply('<xml><ToUserName><![CDATA[ToUserName]]></ToUserName>
<FromUserName><![CDATA[' . $user['wechatOpenId'] . ']]></FromUserName>
<CreateTime>1394729846</CreateTime>
<MsgType><![CDATA[event]]></MsgType>
<Event><![CDATA[subscribe]]></Event>
<EventKey><![CDATA[qrscene_' . $sceneId . ']]></EventKey>
</xml>');

        $user->reload();
        $this->assertEquals($sceneId, $user['source']);
    }

    public function testUnsubscribe()
    {
        wei()->tester->weChatReply('<xml>
                    <ToUserName><![CDATA[toUser]]></ToUserName>
                    <FromUserName><![CDATA[fromUser]]></FromUserName>
                    <CreateTime>1366131823</CreateTime>
                    <MsgType><![CDATA[event]]></MsgType>
                    <Event><![CDATA[unsubscribe]]></Event>
                    <EventKey><![CDATA[]]></EventKey>
                </xml>');

        $user = wei()->user()->find(['wechatOpenId' => 'fromUser']);
        $this->assertFalse((bool) $user['isValid']);
    }

    public function testReSubscribe()
    {
        $user = wei()->user()->findOrCreate(['wechatOpenId' => 'fromUser']);
        $user->save(['isValid' => false]);

        wei()->tester->weChatReply('<xml>
                    <ToUserName><![CDATA[toUser]]></ToUserName>
                    <FromUserName><![CDATA[fromUser]]></FromUserName>
                    <CreateTime>1366131865</CreateTime>
                    <MsgType><![CDATA[event]]></MsgType>
                    <Event><![CDATA[subscribe]]></Event>
                    <EventKey><![CDATA[]]></EventKey>
                 </xml>');

        $user = wei()->user()->find(['wechatOpenId' => 'fromUser']);
        $this->assertTrue((bool) $user['isValid']);
    }

    public function testComponentVerifyTicket()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->saveData([
            'token' => 'weixin',
            'applicationId' => 'wx9eeba14a01d3807f',
            'encodingAesKey' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG',
            'verifyTicket' => 'xx',
        ]);

        wei()->weChatApp = new \Wei\WeChatApp([
            'wei' => $this->wei,
            'token' => 'weixin',
            'appId' => 'wx9eeba14a01d3807f',
            'encodingAesKey' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG',
            'query' => [
                'encrypt_type' => 'aes',
                'msg_signature' => '4d6454ce7140ae24431fe54c8f66b9eee1f69723',
                'signature' => 'fc3adbefb75bdc045aa376b2606073186e0abe9b',
                'timestamp' => '1445308253',
                'nonce' => '2062414594',
            ],
            'postData' => '<xml>
    <AppId><![CDATA[wx9eeba14a01d3807f]]></AppId>
    <Encrypt><![CDATA[wlHfmisYl+ktVgWbVpaTU8hCMsWCa6xuenc7seyAMl/2ZN+jjPU8dtZFl4QO6lpde8ltYPLXxd2PqfKGkZPbuhnok9hhmnBjFGD53Q4k8f1c71FES5/C0btL+6Vf7gl3EgT1hhjSHl5tylx3zo1/FpdF5qeoq0X6vdcXwWyZKg0RUugu2BoxBrtQTnSpvIQn4r1UuzID2Hu7R8Z9SMN5544tTLgaQ3GhsdLjoGJoRC1HxwvCo2QiPGawUxz+lL2Xp6J6r1hA8cnKwAUuDUBz7eJ4SWdDNhAdDI9PuKZm23/bSW2x+KQdjx+qQwJ9Qif+rVdHKOE4cObmrtLgaH8v3r57zRnrrUoeM1M8mAT19nafJHqkQrD28VWk20XEK2Ct096CCnHGUdU9VpA5o+ngMiTze3vRFHGB0I7p4q6IE9WIdCYhb+j7qKx3/IJxNILoVBrmLvXAwuRdPJbYXl1mKQ==]]></Encrypt>
</xml>',
        ]);

        $this->dispatch('wechat', 'reply');

        $account->reload();
        $this->assertEquals('ticket@@@1S8ekCXqBTTCMFLI9ekk2jh8u3NyelwnQoc_Rbpr3X0gKlGiYKhlQrERhGOrsGJ3OcQnWuszOI7iLwe-KaMIVA', $account['verifyTicket']);
    }

    public function testUnauthorized()
    {
        // 设置为已授权
        $testAccount = wei()->wechatAccount()->findOrInit(['applicationId' => 'wx570bc396a51b8ff8']);
        $testAccount->save(['authed' => true]);

        $account = wei()->wechatAccount->getCurrentAccount();
        $account->saveData([
            'token' => 'weixin',
            'applicationId' => 'wx9eeba14a01d3807f',
            'encodingAesKey' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG',
            'verifyTicket' => 'xx',
        ]);

        wei()->weChatApp = new \Wei\WeChatApp([
            'wei' => $this->wei,
            'token' => 'weixin',
            'appId' => 'wx9eeba14a01d3807f',
            'encodingAesKey' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG',
            'query' => [
                'encrypt_type' => 'aes',
                'msg_signature' => '320ef701f94a3b9a0e5dd7fc6cd6c326663091c8',
                'signature' => 'fc3adbefb75bdc045aa376b2606073186e0abe9b',
                'timestamp' => '1445308253',
                'nonce' => '2062414594',
            ],
            'postData' => '<xml>
    <AppId><![CDATA[wx9eeba14a01d3807f]]></AppId>
    <Encrypt><![CDATA[oPHWw0USRVbHH1e3FH2h+fwRAjz0i34NGo8SIuKqcyrqZGm1Sjf6Q20mRgm7eYC7LaOUIK7PnnmvnMBbeMTLzLYoul+Nqxz0eJnMIQPtcY6xjk0aw9Cq7QTN16xmeld3gjuV5EEQHfc0eREQu+LZ70/KydHtCBBYGBFKz6dc8IVnb72TaaXchoZoQ0UQAK91YGfepAZzNUeSmP5mp/tMyv9I0Akh8OcMLeP3uDEJDjlKZyof90bNLeit5MFw1I+2mEF9bbOOR7MuPX5KGEgInphhLNG/Q1B7az54UlR86T4cVF5/ChSNjLhQwyEfpO+9uqrmt0QkrJ5rXlHmwRGJtzwuZc0x9n4M38/KCLt93//ZEPu4rpSnJEHfkF+LAXV0]]></Encrypt>
</xml>',
        ]);

        $this->dispatch('wechat', 'reply');

        $testAccount->reload();
        $this->assertEquals(0, $testAccount['authed']);
    }

    public function testUnauthorizedButAppIdNotFound()
    {
        // 清空缓存的控制器对象
        wei()->app->setOption('controllerInstances', []);

        $account = wei()->wechatAccount->getCurrentAccount();
        $account->saveData([
            'token' => 'weixin',
            'applicationId' => 'wx9eeba14a01d3807f',
            'encodingAesKey' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG',
            'verifyTicket' => 'xx',
        ]);

        wei()->weChatApp = new \Wei\WeChatApp([
            'wei' => $this->wei,
            'token' => 'weixin',
            'appId' => 'wx9eeba14a01d3807f',
            'encodingAesKey' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG',
            'query' => [
                'encrypt_type' => 'aes',
                'msg_signature' => '7717c240943705caedfef472a45dd2a6087e813c',
                'signature' => 'fc3adbefb75bdc045aa376b2606073186e0abe9b',
                'timestamp' => '1445308253',
                'nonce' => '2062414594',
            ],
            'postData' => '<xml>
    <AppId><![CDATA[wx9eeba14a01d3807f]]></AppId>
    <Encrypt><![CDATA[fcKi9fWnawR7Wz0rs4O2zJB/IDgjuySi8CLolfdNQwsVGCfU8KZLZmbXx4Rs7PZ40ZoQoW2PPxMM4Z9Hg3Bv20cbwmdj8P0qvO8NxYwhtw8LRVDfS07UarqzxmFELNq1YvH9Pt+QPnJ68frJdP7oCIdLjspm5LgF/5w0shH6IZgvsdxnrq2sgN4s63T5RoeW68UPfQI5+TmqNyoeLOd3rChHkZv4NjG6FqResybBQ+UCiF+b9HYhFaCxjcsSEvhUq7ynEuLI5GpPTabBIqh/FZnsSscibow2kYgYzTYkFHWLqAPiPyXdg4Ori+VoKcdRuEIiGsI9ECaJjC23PphjonaagCgEj2CX2dLBMU3dkNCZkPocT8EnB/S/yEqtQ0wc]]></Encrypt>
</xml>',
        ]);

        $mock = $this->getServiceMock('logger', ['info']);

        $mock->expects($this->at(0))
            ->method('info')
            ->with('Wechat reply request');

        $mock->expects($this->at(1))
            ->method('info')
            ->with('收到第三方平台通知');

        $mock->expects($this->at(2))
            ->method('info')
            ->with('取消授权但AppId不存在', [
                'AppId' => 'wx9eeba14a01d3807f',
                'AuthorizerAppid' => 'wx1234567890123456',
                'CreateTime' => '1445303136',
                'InfoType' => 'unauthorized',
            ]);

        $this->dispatch('wechat', 'reply');
    }

    /**
     * 测试启用多客服后,如果匹配关键字,会返回相应的内容
     */
    public function testTransferCustomerWithKeywordReturn()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'transferCustomer' => 1,
        ]);
        $response = wei()->tester->weChatReply('<xml>
                        <ToUserName><![CDATA[toUser]]></ToUserName>
                        <FromUserName><![CDATA[fromUser]]></FromUserName>
                        <CreateTime>12345678</CreateTime>
                        <MsgType><![CDATA[text]]></MsgType>
                        <Content><![CDATA[12345]]></Content>
                        </xml>');

        $this->assertContains('54321', $response);
        $account->setData([
            'transferCustomer' => 0,
        ]);
    }

    /**
     * 测试启用多客服后,如果点击菜单，匹配到关键字，,会返回相应的内容
     */
    public function testTransferCustomerWithKeywordEventReturn()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'transferCustomer' => 1,
        ]);

        $response = wei()->tester->weChatReply('<xml>
            <ToUserName><![CDATA[toUser]]></ToUserName>
            <FromUserName><![CDATA[fromUser]]></FromUserName>
            <CreateTime>1457407361</CreateTime>
            <MsgType><![CDATA[event]]></MsgType>
            <Event><![CDATA[CLICK]]></Event>
            <EventKey><![CDATA[12345]]></EventKey>
            </xml>');

        $this->assertContains('54321', $response);

        $account->setData([
            'transferCustomer' => 0,
        ]);
    }

    protected function tearDown()
    {
        parent::tearDown();
        wei()->remove('weChatApp');
    }
}
