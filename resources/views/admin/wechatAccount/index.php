<?php $view->layout() ?>

<style type="text/css">
  .account-head-img {
    width: 80px;
    height: 80px;
  }
</style>

<div class="page-header">
  <h1>
    公众号管理
  </h1>
</div>
<!-- /.page-header -->

<div class="row">
  <div class="col-xs-12">
    <!-- PAGE CONTENT BEGINS -->
    <form class="js-account-form form-horizontal" method="post" role="form">
      <fieldset>
        <legend class="grey bigger-130">基本信息</legend>
        <div class="form-group">
          <label class="col-lg-2 control-label" for="type">
            类型
          </label>

          <div class="col-lg-4">
            <p class="form-control-static">
              <?= $account->getTypeName() ?>
            </p>
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="sourceId">
            原始ID
          </label>

          <div class="col-lg-4">
            <p class="form-control-static" id="sourceId"></p>
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="nickName">
            昵称
          </label>

          <div class="col-lg-4">
            <p class="form-control-static" id="nickName"></p>
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="headImg">
            头像
          </label>

          <div class="col-lg-4">
            <p class="form-control-static">
              <img class="account-head-img" src="<?= $account['headImg'] ?>">
            </p>
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend class="grey bigger-130">
          开发者凭据
          <small class="smaller-70">（用于配置自定义菜单,服务号发送消息等）</small>
        </legend>
        <div class="form-group">
          <label class="col-lg-2 control-label" for="applicationId">
            AppID(应用ID)
          </label>

          <div class="col-lg-4">
            <p class="form-control-static" id="applicationId"></p>
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="applicationSecret">
            AppSecret(应用密钥)
          </label>

          <div class="col-lg-4">
            <p class="form-control-static" id="applicationSecret"></p>
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend class="grey bigger-130">
          消息接口配置
          <small class="smaller-70">
            （用于接收用户信息）
            <a href="http://kf.qq.com/faq/120911VrYVrA1307306biMFz.html" target="_blank" title="如何配置消息接口">
              <span class="help-button help-button-xs get-source-id-tooltip">?</span>
            </a>
          </small>
        </legend>
        <div class="form-group">
          <label class="col-lg-2 control-label" for="appId">
            URL(服务器地址)
          </label>

          <div class="col-lg-4">
            <p class="form-control-static" id="url"><?= $url->full('wechat-replies') ?></p>
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label" for="token">
            Token(令牌)
          </label>

          <div class="col-lg-4">
            <p class="form-control-static" id="token"></p>
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label">
            EncodingAESKey(消息加解密密钥)
          </label>

          <div class="col-lg-4">
            <p class="form-control-static"><?= $e($account['encodingAesKey']) ?: '-' ?></p>
          </div>
        </div>

        <div class="form-group">
          <label class="col-lg-2 control-label">
            多客服
          </label>

          <div class="col-lg-4">
            <p class="form-control-static"><?= $e($account['transferCustomer']) ? '开启' : '关闭' ?></p>
          </div>
        </div>
      </fieldset>

      <input type="hidden" name="id" id="id">

      <div class="clearfix form-actions form-group">
        <div class="col-lg-offset-2">
          <a class="btn btn-primary" href="<?= $url('admin/wechat-account/edit') ?>">
            <i class="fa fa-edit bigger-110"></i>
            编辑
          </a>
          &nbsp; &nbsp; &nbsp;

          <?php if ($curUser->isSuperAdmin()) : ?>
            <a class="btn btn-success" href="<?= $url('admin/wechat-component/auth') ?>">
              <i class="fa fa-wechat bigger-110"></i>
              <?= $account['authed'] ? '重新授权' : '微信公众号授权' ?>
            </a>
          <?php endif ?>
        </div>
      </div>
    </form>
  </div>
  <!-- PAGE CONTENT ENDS -->
</div><!-- /.col -->
<!-- /.row -->

<?= $block('js') ?>
<script>
  require(['form', 'ueditor', 'jquery-deparam', 'validator'], function () {
    $('.js-account-form').loadJSON(<?= $account->toJson() ?>);
  });
</script>
<?= $block->end() ?>
