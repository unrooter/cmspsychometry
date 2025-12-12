define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    $(document).on("fa.event.appendfieldlist", ".btn-append", function(){
        Form.events.selectpicker($(".fieldlist"));
        checkType();
    });
    $('[name="row[type]"]').on('change',function (e) {
        checkType();
    })
    $(function () {
        $("body").delegate(".scoreset","input",function(){
            setscore()
        });


    });

    function checkType() {
        var type = $('[name="row[type]"]').val();
        if(type == 1){
            $('.number').removeAttr('readonly');
            $('.btn-select-questions').addClass('hide');
        }else{
            $('.btn-select-questions').removeClass('hide');
            $('.number').attr('readonly',true);
        }
    }

    function setscore() {
        var score = getscore();
        if(isNaN(score)){
            score = 0;
        }
        $('[name="row[score]"]').val(score);
    }


    function getscore(length) {
        var score = 0;
        $('.mark').each(function () {

            score+=$(this).val() * $(this).parent().prev().children('.number').val();
        });

        return score;
    }
    var Controller = {

        index: function () {

            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'psychometry/exams/index' + location.search,
                    add_url: 'psychometry/exams/add',
                    edit_url: 'psychometry/exams/edit',
                    del_url: 'psychometry/exams/del',
                    multi_url: 'psychometry/exams/multi',
                    getquestion_url: 'psychometry/exams/getquestion',
                    table: 'exams',
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
                        {
                            field: 'buttons',
                            width: "120px",
                            title: __('预览'),
                            operate:false,
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'questions',
                                    text: __('预览'),
                                    title: __('测试题预览'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-eye',
                                    url: 'psychometry/exams/getquestion/type/{type}'
                                },],
                            formatter: Table.api.formatter.buttons
                        },
                        {field: 'id', title: __('Id')},
                        {field: 'subject.subject_name', title: __('所属主题')},
                        {field: 'exam_name', title: __('Exam_name')},
                        {field: 'c_url', title: __('c_url')},
                        {field: 'file_name', title: __('file_name')},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'type', title: __('返回数据类型'), searchList: {"0":__('MBTI'),"1":__('分数'),"2":__('内容及分数'),"3":__('单项匹配')}, formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [{
                                name: 'review',
                                text: '移至cms',
                                icon: '',
                                classname: 'btn btn-xs btn-warning btn-dialog',
                                url: 'psychometry/exams/transfer?ids={id}'
                            }]
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            table.on('post-body.bs.table', function (e, settings, json, xhr) {
                $(".btn-editone").data("area", ["100%","100%"]);
            });

        },
        recyclebin: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    'dragsort_url': ''
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: 'psychometry/exams/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {
                            field: 'deletetime',
                            title: __('Deletetime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate',
                            width: '130px',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'Restore',
                                    text: __('Restore'),
                                    classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                                    icon: 'fa fa-rotate-left',
                                    url: 'psychometry/exams/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'psychometry/exams/destroy',
                                    refresh: true
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function (form) {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        transfer: function (form) {
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

function checkbox_checked(obj) {
    var checkbox_dom = obj;
    if(checkbox_dom.prop("checked")){
        checkbox_dom.removeAttr('checked');
    }
    else
    {
        checkbox_dom.prop("checked","checked");

    }
}

function refresh(name) {
    var data = {};
    var textarea = $("textarea[name='" + name + "']");
    var container = textarea.closest("dl");
    var template = container.data("template");
    $.each($("input,select,textarea", container).serializeArray(), function (i, j) {
        var reg = /\[(\w+)\]\[(\w+)\]$/g;
        var match = reg.exec(j.name);
        if (!match)
            return true;
        match[1] = "x" + parseInt(match[1]);
        if (typeof data[match[1]] == 'undefined') {
            data[match[1]] = {};
        }
        data[match[1]][match[2]] = j.value;
    });
    var result = template ? [] : {};
    $.each(data, function (i, j) {
        if (j) {
            if (!template) {
                if (j.key != '') {
                    result[j.key] = j.value;
                }
            } else {
                result.push(j);
            }
        }
    });
    textarea.val(JSON.stringify(result));
};

function changeSet(obj,select_name) {

    $('[name="'+select_name+'[number]"]').val(0);
    $('[name="'+select_name+'[question_ids]"]').val('');
}
