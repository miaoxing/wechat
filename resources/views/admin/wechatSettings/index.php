<?php $view->layout() ?>

<div class="page-header">
  <h1>
    功能设置
  </h1>
</div>

<div class="row">
  <div class="col-xs-12">
    <form action="<?= $url('admin/wechat-settings/update') ?>" class="js-setting-form form-horizontal" method="post" role="form">

      <div class="form-group">
        <label class="col-lg-2 control-label" for="shareImage">
          首页分享图
        </label>

        <div class="col-lg-4">
          <div class="input-group">
            <input type="text" class="js-share-image form-control" id="shareImage" name="settings[wechat.shareImage]">
            <span class="input-group-btn">
                <button class="btn btn-white" type="button">
                  <i class="fa fa-picture-o"></i>
                  选择图片
                </button>
            </span>
          </div>
        </div>

        <label class="col-lg-6 help-text" for="shareImage">
          尺寸300*300以上,长宽1:1
        </label>
      </div>

      <div class="clearfix form-actions form-group">
        <div class="col-lg-offset-2">
          <button class="btn btn-primary" type="submit">
            <i class="fa fa-check bigger-110"></i>
            提交
          </button>
        </div>
      </div>
    </form>
  </div>
  <!-- PAGE CONTENT ENDS -->
</div><!-- /.col -->
<!-- /.row -->

<?= $block('js') ?>
<script>
  require(['form', 'ueditor', 'validator'], function () {
    $('.js-setting-form')
      .loadJSON(<?= json_encode([
      'js-share-image' => $shareImage,
]) ?>)
      .ajaxForm({
        dataType: 'json',
        beforeSubmit: function(arr, $form, options) {
          return $form.valid();
        }
      })
      .validate();

    $('.js-share-image').imageInput();
  });
</script>
<?= $block->end() ?>

