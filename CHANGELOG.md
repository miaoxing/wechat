## [0.1.4](https://github.com/miaoxing/wechat/compare/v0.1.3...v0.1.4) (2022-03-04)


### Features

* **wechat:** 增加获取用户列表和获取用户基本信息接口 ([c059b2c](https://github.com/miaoxing/wechat/commit/c059b2cc4c20aad3ed8d250b9dd6230a57f789bf))





### Dependencies

* **@miaoxing/app:** upgrade from `0.6.0` to `0.6.1`

## [0.1.3](https://github.com/miaoxing/wechat/compare/v0.1.2...v0.1.3) (2022-02-28)





### Dependencies

* **@miaoxing/app:** upgrade from `0.5.1` to `0.6.0`

## [0.1.2](https://github.com/miaoxing/wechat/compare/v0.1.1...v0.1.2) (2022-02-05)





### Dependencies

* **@miaoxing/dev:** upgrade from `8.0.1` to `8.1.0`
* **@miaoxing/app:** upgrade from `0.5.0` to `0.5.1`

## [0.1.1](https://github.com/miaoxing/wechat/compare/v0.1.0...v0.1.1) (2022-01-12)


### Features

* **wechat:** 增加发送订阅消息接口 ([2fe71bb](https://github.com/miaoxing/wechat/commit/2fe71bb4e82af5714d9990fc0864862575291638))
* **wechat:** 增加生成小程序码 `getWxaCodeUnlimited` 接口 ([e70a383](https://github.com/miaoxing/wechat/commit/e70a383a0d8158ccd9c9cd17bc9aba7b1eace8b5))





### Dependencies

* **@miaoxing/dev:** upgrade from `8.0.0` to `8.0.1`
* **@miaoxing/app:** upgrade from `0.4.0` to `0.5.0`

# 0.1.0 (2021-10-28)


### Code Refactoring

* **Model:** 模型的关联方法加上返回值 ([82339d5](https://github.com/miaoxing/wechat/commit/82339d5dbf230c2145bed2b0799bdb32fcf40272))
* **wechat:** 整理简化接口逻辑 ([e8e9a54](https://github.com/miaoxing/wechat/commit/e8e9a544b334e1ee98dcf7b3952e4039a95d1d95))


### Features

* **wechat:** 允许通过 `__call` 方法调用配置好的接口 ([b6bb584](https://github.com/miaoxing/wechat/commit/b6bb5841fee9cc45bdfb4aed4fd9c1a1d5a24c56))
* **wechat:** 增加插件 `code` 配置 ([c0f92d1](https://github.com/miaoxing/wechat/commit/c0f92d1319b020915bb288afa0f8d30a7a76da75))
* **wechat:** 增加标签相关的方法 ([72e245c](https://github.com/miaoxing/wechat/commit/72e245cbce7cf228faa38764c15bdd8be66e3bac))
* **wechat:** 重构 `WechatApi`，返回值由 Http 改为 Ret，简化逻辑 ([37408e3](https://github.com/miaoxing/wechat/commit/37408e316104973276d9e559e0f5ca8266df5167))


### BREAKING CHANGES

* **wechat:** 整理简化接口逻辑
* **wechat:** 重构 `WechatApi`，返回值由 Http 改为 Ret，更改方法名称
* **Model:** 模型的关联方法加上返回值





### Dependencies

* **@miaoxing/dev:** upgrade from `7.0.1` to `8.0.0`
* **@miaoxing/app:** upgrade from `0.3.3` to `0.4.0`
