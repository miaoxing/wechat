<?php $view->layout() ?>

<?= $block->css() ?>
<link rel="stylesheet" href="<?= $asset('assets/admin/stat.css') ?>"/>
<?= $block->end() ?>

<!-- /.page-header -->
<div class="page-header">
  <div class="pull-right">
    <a class="btn btn-default" href="<?= $url('admin/wechat-qrcode/index') ?>">返回列表</a>
  </div>
  <h1>
    二维码数据统计
  </h1>
</div>

<div class="row">
  <div class="col-12">

    <div class="well well-sm bigger-110">
      <form class="form-inline" id="search-form">
        <div class="form-group">
          <label class="control-label" for="startDate">日期范围</label>
          <input type="text" class="form-control text-center input-date" id="startTime" name="startTime" value="<?= $wei->e->attr($startDate) ?>">
          ~
          <input type="text" class="form-control text-center input-date" id="endTime" name="endTime" value="<?= $wei->e->attr($endDate) ?>">
        </div>
        <input type="hidden" value="<?= $req['sceneId'] ?>" name="sceneId" id="sceneId" >
        <div class="form-group">
          <button type="submit" class="btn btn-primary">查询</button>
        </div>
      </form>
    </div>

    <h5 class="stat-title">趋势图</h5>

    <ul class="js-chart-tabs nav tab-underline">
      <li role="presentation" class="nav-item active">
        <a href="#validCount" class="nav-link" aria-controls="validCount" role="tab" data-toggle="tab">积累关注数</a>
      </li>
      <li class="nav-item">
        <a href="#totalHeadCount" class="nav-link" aria-controls="totalHeadCount" role="tab" data-toggle="tab">总关注人数</a>
      </li>
      <li class="nav-item">
        <a href="#cancelHeadCount" class="nav-link" aria-controls="cancelHeadCount" role="tab" data-toggle="tab">总取消人数</a>
      </li>
      <li class="nav-item">
        <a href="#totalCount" class="nav-link" aria-controls="totalCount" role="tab" data-toggle="tab">总关注次数</a>
      </li>
      <li class="nav-item">
        <a href="#cancelCount" class="nav-link" aria-controls="cancelCount" role="tab" data-toggle="tab">总取消次数</a>
      </li>
    </ul>

    <div class="tab-content m-t border-0">
      <div role="tabpanel" class="tab-pane text-center active" id="read">
        加载中...
      </div>
      <div role="tabpanel" class="tab-pane" id="validCount"></div>
      <div role="tabpanel" class="tab-pane" id="totalHeadCount"></div>
      <div role="tabpanel" class="tab-pane" id="cancelHeadCount"></div>
      <div role="tabpanel" class="tab-pane" id="totalCount"></div>
      <div role="tabpanel" class="tab-pane" id="cancelCount"></div>
    </div>

    <hr>

    <h5 class="stat-title">详细数据</h5>

    <table class="js-stat-table table table-center">
      <thead>
      <tr>
        <th rowspan="2">时间</th>
        <th colspan="2">积累关注数</th>
        <th colspan="2">总关注人数</th>
        <th colspan="2">总取消人数</th>
        <th colspan="2">总关注次数</th>
        <th colspan="2">总取消次数</th>
      </tr>
      <tr>
        <th>增加</th>
        <th>总数</th>
        <th>增加</th>
        <th>总数</th>
        <th>增加</th>
        <th>总数</th>
        <th>增加</th>
        <th>总数</th>
        <th>增加</th>
        <th>总数</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($details as $detail) : ?>
        <tr>
          <td><?= $detail['statDate'] ?></td>
          <td><?= $detail['addValidCount'] ?></td>
          <td><?= $detail['allValidCount'] ?></td>

          <td><?= $detail['addTotalHeadCount'] ?></td>
          <td><?= $detail['allTotalHeadCount'] ?></td>
          <td><?= $detail['addCancelHeadCount'] ?></td>
          <td><?= $detail['allCancelHeadCount'] ?></td>

          <td><?= $detail['addTotalCount'] ?></td>
          <td><?= $detail['allTotalCount'] ?></td>
          <td><?= $detail['addCancelCount'] ?></td>
          <td><?= $detail['allCancelCount'] ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>

  </div>
  <!-- /col -->
</div>
<!-- /row -->

<?= $block->js() ?>
<script>
  require(['highcharts', 'jquery-deparam','form', 'dataTable', 'jquery-ui-datepicker-zh-CN'], function () {
    $('#search-form').loadParams();
    // 渲染趋势图
    // 1. 初始化公用的数据
    var chart = {
      chart: {
        type: 'line',
        height: 300
      },
      title: false,
      xAxis: {
        categories: <?= json_encode($statDates) ?>
      },
      yAxis: {
        min: null,
        title: false
      },
      plotOptions: {
        line: {
          dataLabels: false
        }
      },
      series: []
    };

    // 2. 逐个构造图表数据,方便调整
    var charts = {};

    charts.validCount = $.extend({}, chart, {
      series: [{
        name: '积累关注增加数',
        data: <?= json_encode($charts['addValidCount']) ?>
      }, {
        name: '积累关注总数',
        data: <?= json_encode($charts['allValidCount']) ?>
      }]
    });

    charts.totalHeadCount = $.extend({}, chart, {
      series: [{
        name: '总关注增加人数',
        data: <?= json_encode($charts['addTotalHeadCount']) ?>
      }, {
        name: '总关注人数',
        data: <?= json_encode($charts['allTotalHeadCount']) ?>
      }]
    });

    charts.cancelHeadCount = $.extend({}, chart, {
      series: [{
        name: '取消关注增加人数',
        data: <?= json_encode($charts['addCancelHeadCount']) ?>
      }, {
        name: '取消关注人数',
        data: <?= json_encode($charts['allCancelHeadCount']) ?>
      }]
    });

    charts.totalCount = $.extend({}, chart, {
      series: [{
        name: '总关注增加次数',
        data: <?= json_encode($charts['addTotalCount']) ?>
      }, {
        name: '总关注次数',
        data: <?= json_encode($charts['allTotalCount']) ?>
      }]
    });

    charts.cancelCount = $.extend({}, chart, {
      series: [{
        name: '取消关注增加次数',
        data: <?= json_encode($charts['addCancelCount']) ?>
      }, {
        name: '取消关注次数',
        data: <?= json_encode($charts['allCancelCount']) ?>
      }]
    });

    // 3. 点击tab显示图表数据
    $('.js-chart-tabs a').on('shown.bs.tab', function (e) {
      var target = $(this).attr('href');
      $(target).highcharts(charts[target.substr(1)]);
    });
    $('#read').highcharts(charts.validCount);

    // 渲染底部表格
    $('.js-stat-table').dataTable({
      ajax: null,
      processing: false,
      serverSide: false,
      columnDefs: [{
        targets: ['_all'],
        sortable: true
      }]
    });

    // 日期选择
    $('.input-date').datepicker();
  });
</script>
<?= $block->end() ?>
