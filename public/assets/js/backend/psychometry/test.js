define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'psychometry/test/index' + location.search,
                    add_url: 'psychometry/test/add',
                    edit_url: 'psychometry/test/edit',
                    del_url: 'psychometry/test/del',
                    multi_url: 'psychometry/test/multi',
                    import_url: 'psychometry/test/import',
                    table: 'psychometry_test',
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
                        {field: 'archives_id', title: __('Archives_id')},
                        {field: 'test_title_zh', title: __('测试标题'), formatter: function(value, row, index) {
                            if (value && value !== '暂无中文标题') {
                                return '<span class="text-primary" title="' + value + '">' + value + '</span>';
                            }
                            return '<span class="text-muted">暂无中文标题</span>';
                        }},
                        {field: 'test_type', title: __('Test_type'), searchList: {"mbti":__('Mbti'),"score":__('Score'),"dimension":__('Dimension'),"custom":__('Custom'),"nine_type":__('Nine_type'),"multiple_type":__('Multiple_type')}, formatter: Table.api.formatter.normal},
                        {field: 'back_type', title: __('Back_type'), formatter: function(value, row, index) {
                            return value == 1 ? '<span class="label label-success">是</span>' : '<span class="label label-default">否</span>';
                        }},
                        {field: 'show_type', title: __('Show_type'), operate: 'LIKE', formatter: function(value, row, index) {
                            var typeMap = {
                                'none': '<span class="label label-default">不显示</span>',
                                'immediate': '<span class="label label-success">立即显示</span>',
                                'delay': '<span class="label label-warning">延迟显示</span>'
                            };
                            return typeMap[value] || value;
                        }},
                        {field: 'question_count', title: __('Question_count')},
                        {field: 'base_score', title: __('Base_score')},
                        {field: 'test_config', title: __('Test_config'), formatter: function(value, row, index) {
                            if (value) {
                                var obj;
                                if (typeof value === 'object') {
                                    obj = value;
                                } else if (typeof value === 'string') {
                                    try {
                                        obj = JSON.parse(value);
                                    } catch (e) {
                                        return value;
                                    }
                                }
                                
                                if (obj) {
                                    var html = '<div style="max-width: 200px; font-size: 12px;">';
                                    
                                    // 只显示测试类型
                                    if (obj.test_type) {
                                        var typeMap = {
                                            'mbti': 'MBTI性格测试',
                                            'score': '分数型测试',
                                            'dimension': '维度型测试',
                                            'custom': '自定义测试',
                                            'nine_type': '九型人格测试',
                                            'multiple_type': '多类型测试'
                                        };
                                        html += '<div><span class="label label-primary">' + (typeMap[obj.test_type] || obj.test_type) + '</span></div>';
                                    }
                                    
                                    // 只显示题目数量（如果与基础分数不同）
                                    if (obj.question_count && obj.question_count !== obj.base_score) {
                                        html += '<div style="margin-top: 3px;"><span class="label label-info">' + obj.question_count + '题</span></div>';
                                    }
                                    
                                    html += '</div>';
                                    return html;
                                }
                            }
                            return '-';
                        }},
                        {field: 'scoring_rules', title: __('Scoring_rules'), formatter: function(value, row, index) {
                            if (value) {
                                var obj;
                                if (typeof value === 'object') {
                                    obj = value;
                                } else if (typeof value === 'string') {
                                    try {
                                        obj = JSON.parse(value);
                                    } catch (e) {
                                        return value;
                                    }
                                }
                                
                                if (obj) {
                                    var html = '<div style="max-width: 200px; font-size: 12px;">';
                                    
                                    // 只显示计分类型
                                    if (obj.type) {
                                        var typeMap = {
                                            'mbti': 'MBTI维度计分',
                                            'score': '分数计分',
                                            'dimension': '维度计分',
                                            'custom': '自定义计分'
                                        };
                                        html += '<div><span class="label label-success">' + (typeMap[obj.type] || obj.type) + '</span></div>';
                                    }
                                    
                                    // 只显示维度数量，不显示具体维度
                                    if (obj.dimensions && Array.isArray(obj.dimensions)) {
                                        html += '<div style="margin-top: 3px;"><span class="label label-info">' + obj.dimensions.length + '个维度</span></div>';
                                    }
                                    
                                    html += '</div>';
                                    return html;
                                }
                            }
                            return '-';
                        }},
                        {field: 'result_rules', title: __('Result_rules'), formatter: function(value, row, index) {
                            if (value) {
                                var obj;
                                if (typeof value === 'object') {
                                    obj = value;
                                } else if (typeof value === 'string') {
                                    try {
                                        obj = JSON.parse(value);
                                    } catch (e) {
                                        return value;
                                    }
                                }
                                
                                if (obj) {
                                    var html = '<div style="max-width: 200px; font-size: 12px;">';
                                    
                                    // 只显示结果类型
                                    if (obj.result_type) {
                                        var typeMap = {
                                            'type': '类型结果',
                                            'score': '分数结果',
                                            'dimension': '维度结果',
                                            'custom': '自定义结果'
                                        };
                                        html += '<div><span class="label label-primary">' + (typeMap[obj.result_type] || obj.result_type) + '</span></div>';
                                    }
                                    
                                    html += '</div>';
                                    return html;
                                }
                            }
                            return '-';
                        }},
                        {field: 'status', title: __('Status'), formatter: function(value, row, index) {
                            return value == 1 ? '<span class="label label-success">启用</span>' : '<span class="label label-danger">禁用</span>';
                        }},
                        {field: 'sort_order', title: __('Sort_order')},
                        {field: 'question_count', title: __('题目数量'), formatter: function(value, row, index) {
                            var count = value || 0;
                            var color = count > 0 ? 'label-info' : 'label-default';
                            return '<span class="label ' + color + '">' + count + '题</span>';
                        }},
                        {field: 'answer_count', title: __('解析数量'), formatter: function(value, row, index) {
                            var count = value || 0;
                            var color = count > 0 ? 'label-success' : 'label-default';
                            return '<span class="label ' + color + '">' + count + '个解析</span>';
                        }},
                        {field: 'multilang', title: __('多语言'), formatter: function(value, row, index) {
                            var langs = value || '';
                            if (langs) {
                                var langArray = langs.split(',');
                                var html = '';
                                langArray.forEach(function(lang) {
                                    var color = lang === 'zh' ? 'label-primary' : 'label-success';
                                    var text = lang === 'zh' ? '中文' : 'English';
                                    html += '<span class="label ' + color + '" style="margin-right: 3px;">' + text + '</span>';
                                });
                                return html;
                            }
                            return '<span class="label label-default">无</span>';
                        }},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: function(value, row, index) {
                            var html = Table.api.formatter.operate.call(this, value, row, index);
                            
                            // 添加查看题目按钮
                            if (row.question_count > 0) {
                                html += ' <a class="btn btn-success btn-xs btn-view-questions" data-id="' + row.id + '" title="查看题目"><i class="fa fa-list"></i> 题目</a>';
                            }
                            
                            // 添加查看解析按钮
                            if (row.answer_count > 0) {
                                html += ' <a class="btn btn-info btn-xs btn-view-answers" data-id="' + row.id + '" title="查看解析"><i class="fa fa-comment"></i> 解析</a>';
                            }
                            
                            // 添加多语言管理按钮
                            html += ' <a class="btn btn-warning btn-xs btn-multilang" data-id="' + row.id + '" title="多语言管理"><i class="fa fa-globe"></i> 多语言</a>';
                            
                            return html;
                        }}
                    ]
                ]
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
            
            // 绑定自定义按钮事件
            $(document).on('click', '.btn-view-questions', function(e) {
                e.preventDefault();
                var testId = $(this).data('id');
                Fast.api.open('psychometry/question/index?test_id=' + testId, '查看题目', {
                    area: ['90%', '90%']
                });
            });
            
            $(document).on('click', '.btn-view-answers', function(e) {
                e.preventDefault();
                var testId = $(this).data('id');
                Fast.api.open('psychometry/answer/index?test_id=' + testId, '查看解析', {
                    area: ['90%', '90%']
                });
            });
            
            $(document).on('click', '.btn-multilang', function(e) {
                e.preventDefault();
                var testId = $(this).data('id');
                Fast.api.open('psychometry/test/multilang?ids=' + testId, '多语言管理', {
                    area: ['80%', '80%']
                });
            });
        },
        add: function () {
            Controller.api.bindevent();
            Controller.api.initJsonFormHandlers();
        },
        edit: function () {
            Controller.api.bindevent();
            Controller.api.initJsonFormHandlers();
        },
        multilang: function () {
            Controller.api.bindevent();
            
            // 处理多语言表单提交
            $('#multilang-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                
                $.post('{:url("psychometry.test/multilang_save")}', formData, function(data) {
                    if (data.code === 1) {
                        Toastr.success(data.msg);
                        setTimeout(function() {
                            parent.location.reload();
                        }, 1500);
                    } else {
                        Toastr.error(data.msg);
                    }
                }, 'json');
            });
        },
        api: {
        bindevent: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        
        // 初始化JSON表单处理器
        initJsonFormHandlers: function() {
            // 测试配置处理器
            this.initTestConfigHandlers();
            // 计分规则处理器
            this.initScoringRulesHandlers();
            // 结果规则处理器
            this.initResultRulesHandlers();
            
            // 初始化默认值
            this.initDefaultValues();
        },
        
        // 初始化默认值
        initDefaultValues: function() {
            // 如果隐藏字段为空，设置默认值
            if (!$('#c-test_config').val()) {
                this.updateTestConfig();
            }
            if (!$('#c-scoring_rules').val()) {
                this.updateScoringRules();
            }
            if (!$('#c-result_rules').val()) {
                this.updateResultRules();
            }
        },
        
        // 测试配置处理器
        initTestConfigHandlers: function() {
            var self = this;
            
            // 监听测试配置字段变化
            $('#test-duration, #question-timeout, #test-instructions').on('change input', function() {
                self.updateTestConfig();
            });
            
            $('input[name="allow_back"], input[name="random_questions"], input[name="show_progress"]').on('change', function() {
                self.updateTestConfig();
            });
        },
        
        // 计分规则处理器
        initScoringRulesHandlers: function() {
            var self = this;
            
            // 监听计分规则字段变化
            $('#scoring-method, #min-score, #max-score, #pass-score').on('change input', function() {
                self.updateScoringRules();
            });
            
            $('input[name="show_score"]').on('change', function() {
                self.updateScoringRules();
            });
        },
        
        // 结果规则处理器
        initResultRulesHandlers: function() {
            var self = this;
            
            // 监听结果规则字段变化
            $('#result-type, #result-title, #result-description').on('change input', function() {
                self.updateResultRules();
            });
            
            $('input[name="show_suggestions"], input[name="allow_share"]').on('change', function() {
                self.updateResultRules();
            });
        },
        
        // 更新测试配置JSON
        updateTestConfig: function() {
            var config = {
                duration: parseInt($('#test-duration').val()) || 0,
                question_timeout: parseInt($('#question-timeout').val()) || 0,
                allow_back: $('input[name="allow_back"]:checked').val() == '1' ? 1 : 0,
                random_questions: $('input[name="random_questions"]:checked').val() == '1' ? 1 : 0,
                show_progress: $('input[name="show_progress"]:checked').val() == '1' ? 1 : 0,
                instructions: $('#test-instructions').val() || ''
            };
            
            $('#c-test_config').val(JSON.stringify(config));
        },
        
        // 更新计分规则JSON
        updateScoringRules: function() {
            var rules = {
                method: $('#scoring-method').val() || 'sum',
                min_score: parseInt($('#min-score').val()) || 0,
                max_score: parseInt($('#max-score').val()) || 100,
                pass_score: parseInt($('#pass-score').val()) || 60,
                show_score: $('input[name="show_score"]:checked').val() == '1' ? 1 : 0
            };
            
            $('#c-scoring_rules').val(JSON.stringify(rules));
        },
        
        // 更新结果规则JSON
        updateResultRules: function() {
            var rules = {
                type: $('#result-type').val() || 'type',
                title: $('#result-title').val() || '',
                description: $('#result-description').val() || '',
                show_suggestions: $('input[name="show_suggestions"]:checked').val() == '1' ? 1 : 0,
                allow_share: $('input[name="allow_share"]:checked').val() == '1' ? 1 : 0
            };
            
            $('#c-result_rules').val(JSON.stringify(rules));
        }
        }
    };
    return Controller;
});
