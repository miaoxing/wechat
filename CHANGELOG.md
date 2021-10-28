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
