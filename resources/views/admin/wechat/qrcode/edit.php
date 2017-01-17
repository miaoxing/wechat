<?php $view->layout() ?>

<?= $block('css') ?>
<link rel="stylesheet" href="<?= $asset('plugins/wechat/css/admin/wechat-replies.css') ?>"/>
<?= $block->end() ?>

<div class="page-header">
  <a class="btn btn-default pull-right"
    href="<?= $url('admin/wechat/qrcode/index', ['accountId' => $req['accountId']]) ?>">返回列表</a>

  <h1>
    二维码管理
  </h1>
</div>
<!-- /.page-header -->

<div class="row">
  <div class="col-xs-12">
    <!-- PAGE detail BEGINS -->
    <form class="wechat-qrcode-form form-horizontal js-wechat-qrcode-form" method="post" role="form"
      action="<?= $url('admin/wechat/qrcode/' . $qrcode->getFormAction()) ?>">
      <div class="form-group">
        <label class="col-lg-2 control-label" for="sceneId">
          <span class="text-warning">*</span>
          场景编号
        </label>

        <div class="col-lg-4">
          <input type="text" class="form-control" name="sceneId" id="sceneId" data-rule-required="true"
            data-rule-number="true" data-rule-min="1" data-rule-max="100000">
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

      <div class="form-group">
        <label class="col-lg-2 control-label">
          扫描奖励
        </label>

        <div class="col-lg-10 award-editor">

        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-2 control-label" for="awardRule">
          奖励规则
        </label>

        <div class="col-lg-10">
          <div class="radio">
            <label>
              <input class="awardRule" type="radio" name="awardRule" id="awardRule" value="0">
              扫描就可以获得奖励
            </label>
          </div>
          <div class="radio">
            <label>
              <input class="awardRule" type="radio" name="awardRule" id="awardRule2" value="1">
              只有首次关注才可以获得奖励,重新关注不可获得奖励
            </label>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="col-lg-2 control-label" for="content">
          选择图文
        </label>

        <div class="col-lg-4">
          <div class="article-list"></div>
          <a class="btn btn-white" href="#article-table-modal" data-toggle="modal">添加</a>
        </div>
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
          <a class="btn btn-default" href="<?= $url('admin/wechat/qrcode/index', ['accountId' => $req['accountId']]) ?>">
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
<?php require $view->getFile('wechat:admin/wechat/qrcode/article-select-modal.php') ?>
<?php require $this->getFile('award:admin/award/editor.php') ?>
<?php require $view->getFile('wechat:wechat/media/tpls.php') ?>

<?= $block('js') ?>
<script>
  require(['plugins/wechat/js/admin/wechat-replies', 'form', 'assets/apps/admin/award/editor',
    'jquery-deparam', 'dataTable', 'template', 'validator'
  ], function (reply, form, awardEditor) {
    var data = <?= json_encode($data, JSON_UNESCAPED_SLASHES); ?>;
    reply.initForm({
      data: data,
      returnUrl: $.url('admin/wechat/qrcode/index'),
      form: $('.js-wechat-qrcode-form')
    });

    // 初始化奖励编辑器
    var award = <?= $qrcode->getAward()->toJson() ?>;
    awardEditor.init({
      data: award.awards
    });

  });
</script>
<?= $block->end() ?>
