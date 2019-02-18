<?php $view->layout() ?>

<!-- /.page-header -->
<div class="page-header">
  <div class="pull-right">
    <a class="btn btn-success add-menu" href="javascript:;">添加一级菜单</a>
    <a class="btn btn-default" href="<?= $url('admin/wechat-menu-categories'); ?>">返回列表</a>
  </div>
  <h1>
    菜单管理
  </h1>
</div>

<ol class="text-muted">
  <li>微信最多可以创建 <strong>3</strong> 个一级菜单,每个一级菜单下最多可以创建 <strong>5</strong> 个二级菜单，超出的菜单将不会显示在微信上。</li>
  <li>编辑中的菜单不会马上被用户看到，请放心修改。</li>
  <li>菜单发布后，24小时之内生效。</li>
</ol>

<div class="row menu-row">
  <div class="col-12">
    <!-- PAGE CONTENT BEGINS -->
    <div class="table-responsive">
      <table id="record-table" class="record-table menu-table table table-bordered table-hover">
        <thead>
        <tr>
          <th>菜单名称</th>
          <th>链接到</th>
          <th style="width: 100px">排序</th>
          <th style="width: 100px">启用</th>
          <th style="width: 100px">操作</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
      </table>
    </div>
    <!-- /.table-responsive -->
    <!-- PAGE CONTENT ENDS -->
  </div>
  <!-- /col -->
</div>
<!-- /row -->

<!-- Modal -->
<div class="modal fade" id="act-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">设置点击菜单动作</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <!-- PAGE CONTENT BEGINS -->
        <form class="menu-form form-horizontal" method="post" role="form">
          <input type="hidden" id="id" name="id" value="">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-info submit-menu" data-dismiss="modal">保存并关闭</button>
      </div>
    </div>
    <!-- /.modal-content -->
  </div>
  <!-- /.modal-dialog -->
</div><!-- /.modal -->

<script id="name-control" type="text/html">
  <% if (parentId != '0') { %>
  <span style="color: #ccc">├── </span>
  <% } %>

  <input type="text" value="<%= name %>" class="name" data-id="<%= id %>" data-parent-id="<%= parentId %>">

  <% if (parentId == '0') { %>
  <div class="action-buttons">
    <a href="javascript:;" class="add-menu" title="添加子菜单" data-parent-id="<%= id %>"><i class="fa fa-plus"></i></a>
  </div>
  <% } %>
</script>

<script type="text/html" id="link-to-control">
  <form class="form-horizontal set-link-to" data-data="<%= linkToData %>"></form>
</script>

<script id="enable-control" type="text/html">
  <label>
    <input class="ace enable" value="<%= id %>" type="checkbox" <% if (enable == 1) { %> checked <% } %> >
    <span class="lbl"></span>
  </label>
</script>

<script id="sort-control" type="text/html">
  <div class="action-buttons">
    <a href="javascript:;" class="move-up-menu" title="向上移动" data-id="<%= id %>" data-sort="<%= sort %>" data-parent-id="<%= parentId %>"><i class="fa fa-arrow-up"></i></a>
    <a href="javascript:;" class="move-down-menu" title="向下移动" data-id="<%= id %>" data-sort="<%= sort %>" data-parent-id="<%= parentId %>"><i class="fa fa-arrow-down"></i></a>
  </div>
</script>

<script id="table-actions" type="text/html">
  <div class="action-buttons">
    <a class="text-danger delete-record" href="javascript:;" data-href="<%= $.url('admin/wechat-menu/destroy', {id: id}) %>" title="删除">
      <i class="fa fa-trash-o bigger-130"></i>
    </a>
  </div>
</script>

<?php require $view->getFile('@link-to/link-to/link-to.php') ?>

<?= $block->js() ?>
<script>
  require(['plugins/wechat/js/admin/wechat-menus', 'css!plugins/wechat/css/admin/wechat-menus', 'linkTo', 'form', 'dataTable', 'jquery-deparam'], function (menu, linkTo) {
    menu.index({
      linkTo: linkTo
    });
  });
</script>
<?= $block->end() ?>
