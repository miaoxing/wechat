<?php $view->layout() ?>

<?= $block->css() ?>
<link rel="stylesheet" href="<?= $asset('plugins/wechat/css/admin/wechat-replies.css') ?>"/>
<?= $block->end() ?>

<div class="page-header">
  <div class="float-right">

    <?php if (!$reply->isNew()) { ?>
      <a class="btn btn-danger delete-record" href="javascript:;"
         data-href="<?= $url('admin/wechat-reply/destroy', ['id' => $reply['id']]) ?>">
        删除
      </a>
    <?php } ?>

    <a class="btn btn-secondary" href="<?= $url('admin/wechat-reply/index', ['accountId' => $req['accountId']]) ?>">返回列表</a>
  </div>
  <h1>
    微信管理
    <small>
      <i class="fa fa-angle-double-right"></i>
      回复管理
    </small>
  </h1>
</div>
<!-- /.page-header -->

<div class="row">
  <div class="col-12">
    <!-- PAGE CONTENT BEGINS -->
    <form class="reply-form form-horizontal" method="post" role="form"
          action="<?= $url('admin/wechat-reply/' . $reply->getFormAction()) ?>">

      <?php if (isset($formConfig['showScene']) && true == $formConfig['showScene']) { ?>
        <div class="form-group">
          <label class="col-lg-2 control-label" for="scene">
            场景
          </label>

          <div class="col-lg-4">
            <p class="form-control-plaintext scene"></p>
          </div>
        </div>
      <?php } ?>

      <?php if (isset($formConfig['hideKeywords']) && true == $formConfig['hideKeywords']) { ?>
        <input type="hidden" name="keywords" class="keywords">
      <?php } else { ?>
        <div class="form-group">
          <label class="col-lg-2 control-label" for="keywords">
            <span class="text-warning">*</span>
            关键词
          </label>

          <div class="col-lg-4">
            <?php if (isset($formConfig['showPlainKeywords']) && true == $formConfig['showPlainKeywords']) { ?>
              <p class="form-control-plaintext keywords"></p>
            <?php } else { ?>
              <input type="text" name="keywords" id="keywords" class="form-control">
            <?php } ?>
          </div>
          <label class="col-lg-6 help-text" for="keywords">
            <?php if (isset($formConfig['keywordTips'])) { ?>
              <?= $formConfig['keywordTips'] ?>
            <?php } else { ?>
              多个请使用空格隔开
            <?php } ?>
          </label>
        </div>
      <?php } ?>

      <?php if (isset($formConfig['hideMatchType']) && true == $formConfig['hideMatchType']) { ?>
        <input type="hidden" name="matchType" value="1">
      <?php } else { ?>
        <div class="form-group">
          <label class="col-lg-2 control-label" for="matchType">
            <span class="text-warning">*</span>
            匹配模式
          </label>

          <div class="col-lg-4">
            <label class="radio-inline">
              <input type="radio" name="matchType" value="1" checked> 完全匹配
            </label>
            <label class="radio-inline">
              <input type="radio" name="matchType" value="2"> 部分匹配
            </label>
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

      <div class="form-group text-form-group type-form-group" hidden>
        <label class="col-lg-2 control-label" for="content">
          回复内容
        </label>

        <div class="col-lg-4">
          <textarea id="content" name="content" class="form-control" rows="8" required></textarea>
        </div>

        <label class="col-lg-6 help-text" for="content">
          <?php if (isset($formConfig['contentTips'])) { ?>
            <?= $formConfig['contentTips'] ?>
          <?php } ?>
        </label>

      </div>

      <div class="form-group article-form-group type-form-group" hidden>
        <label class="col-lg-2 control-label" for="content">
          <span class="text-warning">*</span>
          选择图文
        </label>

        <div class="col-lg-4">
          <div class="article-list"></div>
          <a class="btn btn-secondary" href="#article-table-modal" data-toggle="modal">添加</a>
        </div>
      </div>

      <div class="form-group image-form-group type-form-group" hidden>
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

      <input type="hidden" name="accountId" id="accountId">
      <input type="hidden" name="id" id="id">

      <div class="clearfix form-actions form-group">
        <div class="offset-lg-2">
          <button class="btn btn-primary" type="submit">
            <i class="fa fa-check bigger-110"></i>
            提交
          </button>
          &nbsp; &nbsp; &nbsp;
          <a class="btn btn-secondary" href="<?= $url('admin/wechat-reply/index', ['accountId' => $req['accountId']]) ?>">
            <i class="fa fa-undo bigger-110"></i>
            返回列表
          </a>
        </div>
      </div>
    </form>

    <div id="article-table-modal" class="modal fade" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">选择图文</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>

          <div class="modal-body p-0">
            <div class="well border-0 mb-0">
              <form class="form-inline" id="search-form" role="form">
                <div class="form-group">
                  <select class="form-control" name="categoryId" id="categoryId">
                    <option value="">全部栏目</option>
                  </select>
                </div>
                <div class="form-group">
                  <input type="text" class="form-control" name="search" placeholder="请输入标题搜索">
                </div>

                <div class="ml-auto">
                  <a class="btn btn-secondary refresh-articles mr-1" title="刷新" href="javascript:;">
                    <i class="fa fa-refresh"></i>
                  </a>
                  <a class="btn btn-secondary add-article" title="增加" href="<?= $url('admin/article/new') ?>"
                     target="_blank">
                    <i class="fa fa-plus"></i>
                  </a>
                </div>
                <!--<button type="submit" class="btn btn-sm">搜索</button>-->
              </form>
            </div>
            <table class="article-table table table-bordered table-hover">
              <thead>
              <tr>
                <th style="width:50px;"></th>
                <th>标题</th>
              </tr>
              </thead>
              <tbody>
              </tbody>
            </table>
          </div>
        </div>
        <!-- /.modal-content -->
      </div>
      <!-- /.modal-dialog -->
    </div>
    <!-- PAGE CONTENT ENDS -->
  </div>
  <!-- /.col -->
  <!-- /.row -->

  <?php require $view->getFile('@wechat/wechat/media/tpls.php') ?>

  <?= $block->js() ?>
  <script>
    require([
      'plugins/wechat/js/admin/wechat-replies',
      'plugins/admin/js/form',
      'plugins/admin/js/data-table',
      'plugins/app/js/validation',
      'plugins/admin/js/image-upload',
    ], function (reply, form) {
      form.toOptions($('#categoryId'), <?= json_encode(wei()->category()->notDeleted()->withParent('article')->getTreeToArray()) ?>, 'id', 'name');

      reply.initForm({
        data: <?= $reply->toJsonWithArticles() ?>
      });
    });
  </script>
  <?= $block->end() ?>
