define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'psychometry/answer_content/index' + location.search,
                    add_url: 'psychometry/answer_content/add',
                    edit_url: 'psychometry/answer_content/edit',
                    del_url: 'psychometry/answer_content/del',
                    multi_url: 'psychometry/answer_content/multi',
                    import_url: 'psychometry/answer_content/import',
                    table: 'psychometry_answer_content',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'answer_id', title: __('Answer_id')},
                        {field: 'lang', title: __('Lang'), searchList: {"zh":__('Zh'),"en":__('En'),"ja":__('Ja'),"ko":__('Ko')}, formatter: Table.api.formatter.normal},
                        {field: 'title', title: __('Title'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'cover', title: __('Cover'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'created_at', title: __('Created_at')},
                        {field: 'updated_at', title: __('Updated_at')},
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
