<?= $block->css() ?>
<link rel="stylesheet" href="<?= $asset('plugins/wechat/css/admin/message-editor.css') ?>"/>
<?= $block->end() ?>

<?php require $view->getFile('wechat:wechat/media/tpls.php') ?>

<div class="js-message-editor message-editor">
  <ul class="js-message-editor-nav nav nav-tabs message-editor-nav" role="tablist">
    <li role="presentation" class="active">
      <a href="#message-editor-article" data-type="10" data-type-key="article" aria-controls="message-editor-article" role="tab" data-toggle="tab">
        <i class="fa fa-newspaper-o"></i>
        图文
      </a>
    </li>
    <li role="presentation">
      <a href="#message-editor-text" data-type="1" data-type-key="text" aria-controls="message-editor-text" role="tab" data-toggle="tab">
        <i class="fa fa-pencil"></i>
        文字
      </a>
    </li>
    <li class="hide" role="presentation">
      <a href="#message-editor-image" data-type="2" data-type-key="image" aria-controls="message-editor-image" role="tab" data-toggle="tab">
        <i class="fa fa-picture-o"></i>
        图片
      </a>
    </li>
  </ul>

  <div class="tab-content message-editor-tab-content">
    <div role="tabpanel" class="tab-pane active" id="message-editor-article">
      <div class="js-message-editor-article-unselected display-none row message-editor-article-row">
        <div class="col-sm-6">
          <a href="javascript:;" class="js-article-picker-modal-toggle message-editor-article-action text-muted">
            <span class="message-editor-article-plus">+</span>
            <p>从素材库中选择</p>
          </a>
        </div>

        <div class="col-sm-6">
          <a href="<?= $url('admin/article/new') ?>" class="message-editor-article-action text-muted" target="_blank">
            <span class="message-editor-article-plus">+</span>
            <p>新建图文消息</p>
          </a>
        </div>
      </div>
      <div class="js-message-editor-article-selected display-none">
        <div class="js-article-picker-list message-editor-article-list"></div>
        <div class="message-editor-article-actions">
          <a href="javascript:;" class="js-article-picker-modal-toggle">继续选择</a>
          <a href="javascript:;" class="js-message-editor-article-delete">删除</a>
        </div>
      </div>
    </div>

    <div role="tabpanel" class="tab-pane" id="message-editor-text">
      <textarea class="js-message-editor-text message-editor-text" maxlength="600" cols="30" rows="1"></textarea>
      <div class="message-editor-footer">
        <a class="message-editor-emoticon display-none" href="#"><i class="fa fa-smile-o"></i></a>
        <span id="limiterBox" class="message-editor-char-counter">600 / 600</span>
      </div>
    </div>
    <div role="tabpanel" class="tab-pane" id="message-editor-image">
      图片
    </div>
  </div>

  <input type="hidden" class="js-message-editor-type" value="10">
</div>
