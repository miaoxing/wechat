<?php $view->layout() ?>

<div class="page-header">
  <h1>
    公众号管理
  </h1>
</div>
<!-- /.page-header -->

<div class="row">
  <div class="col-12">
    <!-- PAGE CONTENT BEGINS -->
    <form class="js-account-form form-horizontal" method="post" role="form">
      <fieldset>
        <legend class="text-muted text-xl">基本信息</legend>
        <div class="form-group">
          <label class="col-lg-2 control-label" for="type">
            类型
          </label>

          <div class="col-lg-4">
            <label class="radio-inline">
              <input type="radio" name="type" value="1"> 订阅号
            </label>
            <label class="radio-inline">
              <input type="radio" name="type" value="2"> 服务号
            </label>
            <label class="radio-inline">
              <input type="radio" name="type" value="3"> 小程序
            </label>

          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="verified">
            认证
          </label>

          <div class="col-lg-4">
            <label class="radio-inline">
              <input type="radio" class="verified" name="verified" value="1"> 已认证
            </label>
            <label class="radio-inline">
              <input type="radio" class="verified" name="verified" value="0"> 未认证
            </label>
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="sourceId">
            原始ID
          </label>

          <div class="col-lg-4">
            <input type="text" class="form-control" name="sourceId" id="sourceId">
          </div>
          <div class="col-lg-6 help-text">
            如:gh_fb5e7eb6ad05
            <a href="https://jingyan.baidu.com/article/f54ae2fc084bc81e92b8492d.html"
              title="如何获取公众号原始ID" target="_blank">
              <span class="help-button get-source-id-tooltip" style="cursor: pointer">?</span>
            </a>
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="weChatId">
            微信号
          </label>

          <div class="col-lg-4">
            <input type="text" class="form-control" name="weChatId" id="weChatId">
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="nickName">
            公众号昵称
          </label>

          <div class="col-lg-4">
            <input type="text" class="form-control" name="nickName" id="nickName">
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="headImg">
            公众号头像
          </label>

          <div class="col-lg-4">
            <input class="js-head-img" type="text" id="headImg" name="headImg">
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend class="text-muted text-xl">
          开发者凭据
          <small class="text-xs">（选填,用于配置自定义菜单,服务号发送消息等）</small>
        </legend>
        <div class="form-group">
          <label class="col-lg-2 control-label" for="applicationId">
            AppId
          </label>

          <div class="col-lg-4">
            <input type="text" class="form-control" name="applicationId" id="applicationId">
          </div>
          <div class="col-lg-6 help-text">
            <a href="http://jingyan.baidu.com/article/6525d4b12af618ac7c2e9468.html" target="_blank"
              title="如何获取AppId和AppSecret">
              <span class="help-button get-source-id-tooltip" style="cursor: pointer">?</span>
            </a>
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="applicationSecret">
            AppSecret
          </label>

          <div class="col-lg-4">
            <input type="text" class="form-control" name="applicationSecret" id="applicationSecret">
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="token">
            Token
          </label>

          <div class="col-lg-4">
            <input type="text" class="form-control" name="token" id="token">
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="encodingAesKey">
            EncodingAESKey
          </label>

          <div class="col-lg-4">
            <input type="text" class="form-control" name="encodingAesKey" id="encodingAesKey">
          </div>
        </div>

        <?php if (wei()->wechatAccount->enableTransferCustomerService) { ?>
          <div class="form-group">
            <label class="col-lg-2 control-label" for="transferCustomer">
              转发未匹配消息给客服
            </label>

            <div class="col-lg-4">
              <label class="radio-inline">
                <input type="radio" name="transferCustomer" value="1"> 开启
              </label>
              <label class="radio-inline">
                <input type="radio" name="transferCustomer" value="0"> 关闭
              </label>

            </div>
          </div>
        <?php } ?>
      </fieldset>

      <input type="hidden" name="id" id="id">

      <div class="clearfix form-actions form-group">
        <div class="offset-lg-2">
          <button class="btn btn-primary" type="submit">
            <i class="fa fa-check bigger-110"></i>
            提交
          </button>
          &nbsp; &nbsp; &nbsp;
          <a class="btn btn-secondary" href="<?= $url('admin/wechat-account') ?>">
            <i class="fa fa-undo bigger-110"></i>
            返回
          </a>
        </div>
      </div>
    </form>
  </div>
  <!-- PAGE CONTENT ENDS -->
</div><!-- /.col -->
<!-- /.row -->

<?= $block->js() ?>
<script>
  require(['plugins/admin/js/form', 'ueditor', 'plugins/app/js/validation', 'plugins/admin/js/image-upload'], function () {
    $('.js-account-form')
      .loadJSON(<?= $account->toJson() ?>)
      .loadParams()
      .ajaxForm({
        url: '<?= $url('admin/wechat-account/update') ?>',
        dataType: 'json',
        beforeSubmit: function (arr, $form, options) {
          return $form.valid();
        },
        success: function (ret) {
          $.msg(ret, function () {
            if (ret.code === 1) {
              window.location = $.url('admin/wechat-account');
            }
          });
        }
      })
      .validate();

    $('.js-head-img').imageUpload();
  });
</script>
<?= $block->end() ?>
