define(['plugins/app/js/bootbox/bootbox'], function (bootbox) {
  var menu = {};

  menu.linkTo = {};

  menu.prompt = function (data) {
    bootbox.prompt({
      title: '请输入菜单名称',
      value: '',
      callback: function (name) {
        // 取消或关闭
        if (name === null) {
          return null;
        }

        // 数据校验
        if (name == '') {
          $.err('请输入菜单名称');
          $('.bootbox-input').focus();
          return false;
        }

        var ret;
        $.ajax({
          async: false,
          url: $.queryUrl('admin/wechat-menu/create'),
          data: $.extend({name: name}, data),
          dataType: 'json'
        })
          .done(function (result) {
            ret = result.code > 0;
            $.msg(result);
            if (ret) {
              $('table.menu-table').dataTable().reload();
            }
          });
        return ret;
      }
    });
  };

  menu.move = function (handler, direction) {
    var $handler = $(handler);
    var row = $handler.parents('tr');

    var handlerParentId = $handler.data('parentId');

    // 找出所有向上或向下的tr节点
    var siblings = row[direction + 'All']();

    // 逐个节点检查,遇到同一级别节点时,两者替换
    var changed = false;
    $.each(siblings, function (index, sibling) {
      var compareMenu = $(sibling).find('.move-up-menu');
      var id = compareMenu.data('id');
      var parentId = compareMenu.data('parent-id');

      // 找到同一级同一父节点
      if (parentId == handlerParentId) {
        $.ajax({
          url: $.url('admin/wechat-menu/bulkUpdate'),
          type: 'post',
          dataType: 'json',
          traditional: false,
          data: {
            menus: [{
              id: $handler.data('id'),
              sort: compareMenu.data('sort')
            }, {
              id: compareMenu.data('id'),
              sort: $handler.data('sort')
            }]
          }
        }).done(function (result) {
          $.msg(result);
          $('table.menu-table').dataTable().reload();
        });
        changed = true;
        return false;
      }

      // 找到其他父节点
      if (handlerParentId != 0 && handlerParentId != id) {
        // 如果是向上,找父节点中的子节点的最大+1
        // 如果是向下,找父节点中的子节点的最小-1

        var pos = direction == 'next' ? 'first' : 'last';
        var sort = direction == 'next' ? -1 : +1;

        var findParentId;

        // 遇到其他父节点
        if (parentId == '0') {
          findParentId = id;
          // 遇到非父节点
        } else {
          findParentId = parentId;
        }

        var siblingHandler = $('table.menu-table').find('.move-up-menu[data-parent-id="' + findParentId + '"]:' + pos);

        if (siblingHandler.length != 0) {
          sort = siblingHandler.data('sort') + sort;
        } else {
          sort = 1;
        }

        $.post(
          $.url('admin/wechat-menu/update'),
          {id: $handler.data('id'), sort: sort, parentId: findParentId},
          function (result) {
            $.msg(result);
            $('table.menu-table').dataTable().reload();
          },
          'json'
        );

        changed = true;
        return false;
      }

      return true;
    });

    if (changed == false) {
      $.err('菜单已经在最顶部或底部,无法移动');
    }
  };

  // 菜单列表
  //========
  menu.index = function (options) {
    $.extend(menu, options);

    this.initTable();
    this.initEvent();
  };

  menu.actNames = {
    click: '触发关键字',
    view: '跳转到网页',
    oauth2BaseView: '跳转到OpenID授权网页',
    oauth2UserInfoView: '跳转到用户信息授权网页'
  };

  menu.initTable = function () {
    var recordTable = $('table.menu-table').dataTable({
      dom: "t<'row hide'<'col-sm-6'ir><'col-sm-6'pl>>",
      ajax: {
        url: $.queryUrl('admin/wechat-menu/index?_format=json')
      },
      columns: [
        {
          data: 'name',
          sClass: 'text-left',
          render: function (data, type, full) {
            return template.render('name-control', full);
          }
        },
        {
          data: 'linkTo',
          render: function (data, type, full) {
            if (full.hasChild == false) {
              full.linkToData = JSON.stringify(data);
              return template.render('link-to-control', full);
            } else {
              return '';
            }
          }
        },
        {
          data: 'sort',
          render: function (data, type, full) {
            return template.render('sort-control', full);
          }
        },
        {
          data: 'enable',
          render: function (data, type, full) {
            return template.render('enable-control', full);
          }
        },
        {
          data: 'id',
          sClass: 'text-center',
          render: function (data, type, full) {
            return template.render('table-actions', full)
          }
        }
      ],
      drawCallback: function () {
        // 初始化linkTo选择器
        this.find('.set-link-to').each(function () {
          var $this = $(this);
          var rowData = recordTable.fnGetData($this.parents('tr:first')[0]);
          $this.linkTo({
            data: rowData.linkTo,
            linkText: '设置',
            hide: {
              tel: true,
              browser: true
            },
            update: function (data) {
              $.ajax({
                url: $.url('admin/wechat-menu/update'),
                type: 'post',
                dataType: 'json',
                data: {
                  id: rowData.id,
                  linkTo: data
                }
              }).done(function (ret) {
                $.msg(ret);
              });
            }
          });
        });
      }
    });

    recordTable.deletable();
  };

  menu.initEvent = function () {
    var menuTable = $('table.menu-table').dataTable();

    // 启用/禁用菜单
    menuTable.on('change', '.enable', function () {
      $.post($.url('admin/wechat-menu/update', {
        id: $(this).val(),
        enable: +$(this).prop('checked')
      }), function (result) {
        $.msg(result);
      }, 'json');
    });

    // 更新名称
    menuTable.on('change', '.name', function () {
      $.post($.url('admin/wechat-menu/update', {
        id: $(this).data('id'),
        name: $(this).val()
      }));
    });

    // 添加菜单
    $('body').on('click', '.add-menu', function () {
      menu.prompt({
        parentId: $(this).data('parentId')
      });
    });

    // 从微信加载菜单
    $('.load-menu-from-wechat').click(function () {
      $.confirm('从微信加载菜单,本地菜单将会被清空,确认加载?', function (result) {
        if (!result) {
          return;
        }

        $.post($.queryUrl('admin/wechat-menu/loadFromWeChat'), function (result) {
          $.msg(result);
          $('table.menu-table').dataTable().reload();
        }, 'json');
      });
    });

    menuTable.on('click', '.move-up-menu', function () {
      menu.move(this, 'prev');
    });

    menuTable.on('click', '.move-down-menu', function () {
      menu.move(this, 'next');
    });
  };

  return menu;
});
