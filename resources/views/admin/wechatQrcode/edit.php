<?php $view->layout() ?>

<?= $block->css() ?>
<link rel="stylesheet" href="<?= $asset('plugins/wechat/css/admin/wechat-replies.css') ?>"/>
<?= $block->end() ?>

<div class="page-header">
  <a class="btn btn-default pull-right"
    href="<?= $url('admin/wechat-qrcode/index', ['accountId' => $req['accountId']]) ?>">返回列表</a>

  <h1>
    二维码管理
  </h1>
</div>
<!-- /.page-header -->

<div class="row">
  <div class="col-xs-12">
    <!-- PAGE detail BEGINS -->
    <form class="wechat-qrcode-form form-horizontal js-wechat-qrcode-form" method="post" role="form"
      action="<?= $url('admin/wechat-qrcode/' . $qrcode->getFormAction()) ?>">
      <div class="form-group">
        <label class="col-lg-2 control-label" for="sceneId">
          <span class="text-warning">*</span>
          场景编号
        </label>

        <div class="col-lg-4">
          <input type="text" class="form-control" name="sceneId" id="sceneId" data-rule-required="true">
        </div>

        <label class="col-lg-4 help-text" for="sceneId">
          永久二维码的场景编号,最多10万个
          <a href="http://mp.weixin.qq.com/wiki/18/167e7d94df85d8389df6c94a7a8f78ba.html" target="_blank">
            <i class="fa fa-external-link smaller-80"></i>
          </a>
        </label>
      </div>

      <div class="form-group">
        <label class="col-lg-2 control-label" for="name">
          <span class="text-warning">*</span>
          名称
        </label>

        <div class="col-lg-4">
          <input type="text" class="form-control" name="name" id="name" data-rule-required="true">
        </div>
      </div>

      <?php if (wei()->plugin->isInstalled('user-tag')) { ?>
        <div class="form-group">
          <label class="col-lg-2 control-label" for="add-tag-ids">
            打上标签
          </label>

          <div class="col-lg-4">
            <input class="js-tag-ids form-control" id="add-tag-ids" name="addTagIds"/>
          </div>
        </div>
      <?php } ?>

      <div class="form-group">
        <label class="col-lg-2 control-label" for="matchType">
          <span class="text-warning">*</span>
          回复类型
        </label>

        <div class="col-lg-4">
          <label class="radio-inline">
            <input type="radio" value="text" name="type"> 文本
          </label>
          <label class="radio-inline">
            <input type="radio" value="article" name="type"> 图文
          </label>
          <label class="radio-inline">
            <input type="radio" value="image" name="type"> 图片
          </label>
        </div>
      </div>

      <div class="form-group text-form-group type-form-group display-none">
        <label class="col-lg-2 control-label" for="content">
          回复内容
        </label>

        <div class="col-lg-4">
          <textarea id="content" name="content" class="form-control" rows="8"></textarea>
        </div>
      </div>

      <div class="form-group article-form-group type-form-group display-none">
        <label class="col-lg-2 control-label" for="content">
          选择图文
        </label>

        <div class="col-lg-4">
          <div class="article-list"></div>
          <a class="btn btn-default" href="#article-table-modal" data-toggle="modal">添加</a>
        </div>
      </div>

      <div class="form-group image-form-group type-form-group display-none">
        <label class="col-lg-2 control-label" for="replies-image-url">
          回复图片
        </label>

        <div class="col-lg-4">
          <input class="js-replies-image-url" type="text" id="replies-image-url" name="replies[image][url]" required>
        </div>

        <label class="col-lg-6 help-text" for="replies-image-url">
          图片2M以内，支持bmp/png/jpeg/jpg/gif格式
        </label>
      </div>

      <input type="hidden" name="id" id="id">
      <input type="hidden" name="accountId" id="accountId">

      <div class="clearfix form-actions form-group">
        <div class="col-lg-offset-2">
          <button class="btn btn-primary" type="submit">
            <i class="fa fa-check bigger-110"></i>
            提交
          </button>

          &nbsp; &nbsp; &nbsp;
          <a class="btn btn-default" href="<?= $url('admin/wechat-qrcode/index', ['accountId' => $req['accountId']]) ?>">
            <i class="fa fa-undo"></i>
            返回列表
          </a>
        </div>
      </div>
    </form>
  </div>
  <!-- PAGE detail ENDS -->
</div><!-- /.col -->
<!-- /.row -->
<?php require $view->getFile('@wechat/admin/wechatQrcode/article-select-modal.php') ?>
<?php require $view->getFile('@wechat/wechat/media/tpls.php') ?>

<?= $block->js() ?>
<script>
  require(['plugins/wechat/js/admin/wechat-replies', 'form',
    'jquery-deparam', 'dataTable', 'template', 'validator',
    'comps/select2/select2.min',
    'css!comps/select2/select2',
    'css!comps/select2-bootstrap-css/select2-bootstrap',
    'plugins/admin/js/image-upload'
  ], function (reply, form) {
    var data = <?= json_encode($data, JSON_UNESCAPED_SLASHES); ?>;
    reply.initForm({
      data: data,
      returnUrl: $.url('admin/wechat-qrcode/index'),
      form: $('.js-wechat-qrcode-form')
    });

    $('.js-tag-ids').select2({
      multiple: true,
      closeOnSelect: false,
      data: <?= json_encode($tags) ?>
    });
  });
</script>
<?= $block->end() ?>
