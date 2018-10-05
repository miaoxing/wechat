<?php $view->layout() ?>

<!-- /.page-header -->
<div class="page-header">
  <div class="pull-right">
    <a class="btn btn-success" href="<?= $url('admin/wechat-qrcode/new', ['accountId' => $req['accountId']]) ?>">添加二维码</a>
  </div>
  <h1>
    二维码管理
  </h1>
</div>

<div class="row">
  <div class="col-xs-12">
    <!-- PAGE CONTENT BEGINS -->
    <div class="table-responsive">
      <div class="well form-well">
        <form class="form-inline" id="search-form" role="form">

          <div class="form-group">
            <select class="form-control" name="type">
              <?= wei()->weChatQrcode->getTypeToOption(); ?>
            </select>
          </div>

          <div class="form-group">
            <input type="text" class="form-control" style="width: 220px" name="search" placeholder="请输入用户姓名搜索">
          </div>

        </form>
      </div>

      <table id="record-table" class="js-record-table table table-bordered table-hover table-center">
        <thead>
        <tr>
          <th style="width:40px;"></th>
          <th style="width: 80px">场景编号</th>
          <th>名称</th>
          <th>用户</th>
          <th>扫描奖励</th>
          <th>总关注次数</th>
          <th>总取消次数</th>
          <th>总关注人数</th>
          <th>总取消人数</th>
          <th>积累关注数</th>
          <th style="width: 120px">操作</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
      </table>
      <div class="well form-well">
        <label>
          <input class="js-table-check-all ace" type="checkbox">
          <span class="lbl"> 全选 </span>
        </label>
        <a class="js-batch-download btn btn-info" href="javascript:;">导出二维码</a>
      </div>
    </div>

    <!-- /.table-responsive -->
    <!-- PAGE CONTENT ENDS -->
  </div>
  <!-- /col -->
</div>
<!-- /row -->

<script id="award-enabled-col-tpl" type="text/html">
  <label><input type="checkbox" class="ace table-input" data-id="<%= id %>" value="1" name="enabled" <% if (enabled == "1") { %>checked<% } %>><span class="lbl"></span></label>
</script>

<script id="table-actions-tpl" type="text/html">
  <div class="action-buttons">
    <a href="javascript:;" class="js-qrcode-show" data-scene-id="<%= sceneId %>" title="查看二维码"
      data-name="<%= name %>">
      <i class="fa fa-qrcode bigger-130"></i>
    </a>
    <a href="<%= $.url('admin/wechat-qrcode/showDetail', {sceneId: sceneId}) %>" target="_blank" title="查看">
      <i class="fa fa-search-plus bigger-130"></i>
    </a>
    <a href="<%= $.url('admin/wechat-qrcode/edit', {accountId: accountId, id: id}) %>" title="编辑">
      <i class="fa fa-edit bigger-130"></i>
    </a>
    <a class="text-danger delete-record" href="javascript:;" data-href="<%= $.url('admin/wechat-qrcode/destroy', {id: id}) %>" title="删除">
      <i class="fa fa-trash-o bigger-130"></i>
    </a>
  </div>
</script>

<?php require $view->getFile('@wechat/admin/wechatQrcode/qrcodeModal.php') ?>
<?php require $view->getFile('@wechat/admin/wechatQrcode/batchDownload.php') ?>
<?php require $view->getFile('@user/admin/user/richInfo.php') ?>

<?= $block->js() ?>
<script>
  require(['dataTable', 'form', 'jquery-deparam', 'template'], function () {
    var recordTable = $('#record-table').dataTable({
      ajax: {
        url: $.queryUrl('admin/wechat-qrcode/index?_format=json')
      },
      columns: [
        {
          data: 'sceneId',
          sClass: 'text-center',
          render: function (data) {
            return '<label><input type="checkbox" class="js-table-checkbox ace" value="' + data + '"><span class="lbl"></span></label>'
          }
        },
        {
          data: 'sceneId'
        },
        {
          data: 'name'
        },
        {
          data: 'user',
          render: function (data, type, full) {
            if(!data) {
              return '-';
            }
            return template.render('user-info-tpl', data);
          }
        },
        {
          data: 'award.contents',
          render: function (data, type, full) {
            return data.join('<br>') || '无';
          }
        },
        {
          data: 'totalCount'
        },
        {
          data: 'cancelCount'
        },
        {
          data: 'totalHeadCount'
        },
        {
          data: 'cancelHeadCount'
        },
        {
          data: 'validCount'
        },
        {
          data: 'id',
          render: function (data, type, full) {
            return template.render('table-actions-tpl', full);
          }
        }
      ],
      fnDrawCallback: function () {
        $(this).trigger('draw');
      }
    });

    //筛选
    $('#search-form').loadParams().update(function () {
      recordTable.search($(this).serializeArray(), false);
    });

    recordTable.deletable();

    // 启用/禁用奖励
    recordTable.on('change', '.table-input', function () {
      var data = {};
      data['id'] = $(this).data('id');
      if ($(this).attr('type') == 'text') {
        data[$(this).attr('name')] = $(this).val();
      } else {
        data[$(this).attr('name')] = +$(this).is(':checked');
      }

      $.ajax({
        url: $.url('admin/award/update'),
        data: data,
        dataType: 'json',
        success: function (result) {
          $.msg(result);
          recordTable.reload();
        }
      });
    });
  });
</script>
<?= $block->end() ?>
