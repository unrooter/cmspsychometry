define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'psychometry/user_question/index' + location.search,
                    add_url: 'psychometry/user_question/add',
                    edit_url: 'psychometry/user_question/edit',
                    del_url: 'psychometry/user_question/del',
                    multi_url: 'psychometry/user_question/multi',
                    import_url: 'psychometry/user_question/import',
                    table: 'psychometry_user_question',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'question.title', title: __('所属问题')},
                        {field: 'answer.title', title: __('答案选择')},
                        {field: 'user_id', title: __('用户id')},
                        {field: 'user.nickname', title: __('用户昵称')},
                        {field: 'create_time', title: __('创建时间'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
