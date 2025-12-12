define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'psychometry/answer/index' + location.search,
                    add_url: 'psychometry/answer/add',
                    edit_url: 'psychometry/answer/edit',
                    del_url: 'psychometry/answer/del',
                    multi_url: 'psychometry/answer/multi',
                    import_url: 'psychometry/answer/import',
                    table: 'psychometry_answer',
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
                        {field: 'test_title', title: __('所属测试'), operate: 'LIKE', formatter: function(value, row, index) {
                            if (value) {
                                return '<span class="text-primary">' + value + '</span>';
                            }
                            return '<span class="text-muted">未关联</span>';
                        }},
                        {field: 'answer_title', title: __('答案标题'), operate: 'LIKE', formatter: function(value, row, index) {
                            if (value && value !== '未设置标题') {
                                return '<strong>' + value + '</strong>';
                            }
                            return '<span class="text-muted">未设置标题</span>';
                        }},
                        {field: 'result_type', title: __('结果配置'), searchList: {"type":__('类型结果'),"score":__('分数结果'),"dimension":__('维度结果'),"custom":__('自定义结果')}, formatter: function(value, row, index) {
                            var html = '';
                            
                            try {
                                // 根据结果类型显示完整的配置信息
                                switch(value) {
                                    case 'type':
                                        // 类型结果：类型标签 + 标识
                                        html = '<span class="label label-info">类型</span> ';
                                        if (row.result_key && row.result_key.trim()) {
                                            html += '<span class="label label-default">' + row.result_key + '</span>';
                                        } else {
                                            html += '<span class="text-muted">未设置标识</span>';
                                        }
                                        break;
                                        
                                    case 'score':
                                        // 分数结果：类型标签 + 分数范围
                                        html = '<span class="label label-warning">分数</span> ';
                                        if (row.result_config_text) {
                                            try {
                                                var config = JSON.parse(row.result_config_text);
                                                if (config.min_score !== undefined && config.max_score !== undefined) {
                                                    html += '<strong>' + config.min_score + ' ~ ' + config.max_score + ' 分</strong>';
                                                } else {
                                                    html += '<span class="text-muted">未设置范围</span>';
                                                }
                                            } catch (e) {
                                                html += '<span class="text-muted">未设置范围</span>';
                                            }
                                        } else {
                                            html += '<span class="text-muted">未设置范围</span>';
                                        }
                                        break;
                                        
                                    case 'dimension':
                                        // 维度结果：类型标签 + 维度值
                                        html = '<span class="label label-primary">维度</span> ';
                                        if (row.result_key && row.result_key.trim()) {
                                            html += '<span class="label label-default">' + row.result_key + '</span>';
                                        } else {
                                            html += '<span class="text-muted">未设置维度</span>';
                                        }
                                        break;
                                        
                                    case 'custom':
                                        // 自定义结果：解析并显示关键信息
                                        html = '<span class="label label-success">自定义</span> ';
                                        if (row.result_config_text && row.result_config_text.length > 0) {
                                            try {
                                                var customConfig = JSON.parse(row.result_config_text);
                                                var configParts = [];
                                                
                                                // 提取关键配置信息
                                                if (customConfig.match_type) {
                                                    var matchTypeMap = {
                                                        'score': '分数匹配',
                                                        'option': '选项匹配',
                                                        'dimension': '维度匹配',
                                                        'formula': '公式计算'
                                                    };
                                                    configParts.push('<span class="text-info">' + (matchTypeMap[customConfig.match_type] || customConfig.match_type) + '</span>');
                                                }
                                                
                                                if (customConfig.min_score !== undefined && customConfig.max_score !== undefined) {
                                                    configParts.push('<span class="text-muted">' + customConfig.min_score + '~' + customConfig.max_score + '分</span>');
                                                }
                                                
                                                if (customConfig.formula) {
                                                    configParts.push('<span class="text-warning"><i class="fa fa-calculator"></i> 公式</span>');
                                                }
                                                
                                                if (customConfig.conditions && Array.isArray(customConfig.conditions) && customConfig.conditions.length > 0) {
                                                    configParts.push('<span class="text-primary">' + customConfig.conditions.length + '个条件</span>');
                                                }
                                                
                                                if (configParts.length > 0) {
                                                    html += configParts.join(' <span class="text-muted">|</span> ');
                                                } else {
                                                    // 如果没有识别到关键字段，显示JSON摘要
                                                    var keys = Object.keys(customConfig);
                                                    if (keys.length > 0) {
                                                        html += '<span class="text-muted"><i class="fa fa-code"></i> ' + keys.join(', ') + '</span>';
                                                    } else {
                                                        html += '<span class="text-muted">空配置</span>';
                                                    }
                                                }
                                                
                                                // 添加查看详情的提示
                                                html += ' <a href="javascript:;" class="text-muted" title="' + row.result_config_text.replace(/"/g, '&quot;') + '"><i class="fa fa-eye"></i></a>';
                                                
                                            } catch (e) {
                                                // JSON解析失败，显示原始内容
                                                var displayText = row.result_config_text.length > 25 ? row.result_config_text.substring(0, 25) + '...' : row.result_config_text;
                                                html += '<span class="text-danger" title="' + row.result_config_text + '"><i class="fa fa-exclamation-triangle"></i> ' + displayText + '</span>';
                                            }
                                        } else {
                                            html += '<span class="text-muted">未配置</span>';
                                        }
                                        break;
                                        
                                    default:
                                        html = '<span class="label label-default">未知</span>';
                                }
                            } catch (e) {
                                html = '<span class="label label-default">' + value + '</span> <span class="text-danger">配置错误</span>';
                            }
                            
                            return html;
                        }},
                        {field: 'multilang', title: __('多语言内容'), formatter: function(value, row, index) {
                            var html = '';
                            if (value && value.length > 0) {
                                var langs = value.split(',');
                                langs.forEach(function(lang) {
                                    if (lang === 'zh') {
                                        html += '<span class="label label-primary" style="margin-right: 3px;">中文</span>';
                                    } else if (lang === 'en') {
                                        html += '<span class="label label-success" style="margin-right: 3px;">English</span>';
                                    }
                                });
                            } else {
                                html = '<span class="text-muted">未添加内容</span>';
                            }
                            return html;
                        }},
                        {field: 'status', title: __('状态'), searchList: {"0":__('关闭'),"1":__('启用')}, formatter: function(value, row, index) {
                            return value == 1 ? '<span class="label label-success">启用</span>' : '<span class="label label-danger">关闭</span>';
                        }},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: function(value, row, index) {
                            var html = Table.api.formatter.operate.call(this, value, row, index);
                            
                            // 添加多语言管理按钮
                            html += ' <a class="btn btn-warning btn-xs btn-multilang" data-id="' + row.id + '" title="多语言管理"><i class="fa fa-globe"></i> 多语言</a>';
                            
                            return html;
                        }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            
            // 绑定多语言管理按钮事件
            $(document).on('click', '.btn-multilang', function(e) {
                e.preventDefault();
                var answerId = $(this).data('id');
                Fast.api.open('psychometry/answer/multilang?ids=' + answerId, '多语言管理', {
                    area: ['80%', '80%']
                });
            });
        },
        add: function () {
            $("input[name='row[type]']").change(function(){
                if($("input[name='row[type]']:checked").val() == 1){
                    $(".type1").show();
                    $(".type0").hide();
                    $(".type2").hide();
                }else if($("input[name='row[type]']:checked").val() == 2){
                    $(".type2").show();
                    $(".type0").show();
                    $(".type1").hide();
                }else if($("input[name='row[type]']:checked").val() == 0){
                    $(".type0").show();
                    $(".type1").hide();
                    $(".type2").hide();
                }else if($("input[name='row[type]']:checked").val() == 3){
                    $(".type0").show();
                    $(".type1").show();
                    $(".type2").hide();
                }
            });
            Controller.api.bindevent();
        },
        edit: function () {
            // 根据result_type显示对应的配置面板
            function showResultTypePanel(resultType) {
                $('.result-type-panel').hide();
                
                switch(resultType) {
                    case 'type':
                        $('#panel-result-type').fadeIn(300);
                        break;
                    case 'score':
                        $('#panel-result-score').fadeIn(300);
                        break;
                    case 'dimension':
                        $('#panel-result-dimension').fadeIn(300);
                        break;
                    case 'custom':
                        $('#panel-result-custom').fadeIn(300);
                        break;
                }
            }
            
            // 初始化：从result_config加载已有的配置值
            function loadConfigValues() {
                try {
                    var resultConfig = JSON.parse($('#c-result_config').val() || '{}');
                    console.log('已保存的配置:', resultConfig);
                    
                    // 加载分数范围配置
                    if (resultConfig.min_score !== undefined) {
                        console.log('加载最低分:', resultConfig.min_score);
                        $('#config-min-score').val(resultConfig.min_score);
                    }
                    if (resultConfig.max_score !== undefined) {
                        console.log('加载最高分:', resultConfig.max_score);
                        $('#config-max-score').val(resultConfig.max_score);
                    }
                    
                    console.log('所有result_config字段:', Object.keys(resultConfig));
                    console.log('完整result_config内容:', resultConfig);
                } catch (e) {
                    console.error('加载配置失败:', e);
                }
            }
            
            // 同步：保存配置到JSON
            function syncConfigToJSON() {
                var resultConfig = {};
                var resultType = $('input[name="row[result_type]"]:checked').val();
                
                // 根据result_type收集对应的值
                switch(resultType) {
                    case 'type':
                        // 类型结果：result_key字段单独处理，不存入result_config
                        break;
                    case 'score':
                        // 分数结果：分数范围
                        var minScore = $('#config-min-score').val();
                        var maxScore = $('#config-max-score').val();
                        if (minScore || maxScore) {
                            resultConfig.min_score = parseFloat(minScore) || 0;
                            resultConfig.max_score = parseFloat(maxScore) || 100;
                        }
                        break;
                    case 'dimension':
                        // 维度结果：result_key字段单独处理，不存入result_config
                        break;
                    case 'custom':
                        // 自定义结果：JSON配置
                        var customConfig = $('#config-custom').val();
                        if (customConfig && customConfig.trim()) {
                            try {
                                resultConfig = JSON.parse(customConfig);
                            } catch (e) {
                                console.error('自定义配置JSON格式错误:', e);
                            }
                        }
                        break;
                }
                
                // 更新隐藏字段
                $('#c-result_config').val(JSON.stringify(resultConfig));
                console.log('配置已保存:', resultConfig);
            }
            
            // 监听result_type变化
            $('input[name="row[result_type]"]').on('change', function() {
                var resultType = $(this).val();
                console.log('结果类型切换到:', resultType);
                showResultTypePanel(resultType);
                syncConfigToJSON();
            });
            
            // 初始化：从result_type加载对应面板
            var currentResultType = $('input[name="row[result_type]"]:checked').val();
            if (!currentResultType) {
                currentResultType = 'type'; // 默认类型
            }
            console.log('当前结果类型:', currentResultType);
            
            // 先加载配置值
            loadConfigValues();
            
            // 然后显示对应面板
            showResultTypePanel(currentResultType);
            
            // 监听各种输入变化
            $('#config-min-score, #config-max-score, #config-custom').on('change', function() {
                syncConfigToJSON();
            });
            
            // 表单提交前同步
            $('#edit-form').on('submit', function() {
                syncConfigToJSON();
            });
            
            // 编辑页面的多语言管理按钮
            $(document).on('click', '.btn-multilang-edit', function(e) {
                e.preventDefault();
                var answerId = $(this).data('id');
                Fast.api.open('psychometry/answer/multilang?ids=' + answerId, '多语言管理', {
                    area: ['80%', '80%']
                });
            });
            
            // 绑定表单事件
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
