define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'psychometry/question/index' + location.search,
                    add_url: 'psychometry/question/add',
                    edit_url: 'psychometry/question/edit',
                    del_url: 'psychometry/question/del',
                    multi_url: 'psychometry/question/multi',
                    import_url: 'psychometry/question/import',
                    table: 'question',
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
                    if (value && value.length > 0) {
                        return '<span class="text-primary" title="' + value + '">' + value + '</span>';
                    }
                    return '<span class="text-muted">未关联测试</span>';
                }},
                {field: 'question_text', title: __('题目内容'), operate: 'LIKE', formatter: function(value, row, index) {
                    if (value && value.length > 0) {
                        var displayText = value.length > 50 ? value.substring(0, 50) + '...' : value;
                        return '<span title="' + value + '">' + displayText + '</span>';
                    }
                    return '<span class="text-muted">无内容</span>';
                }},
                        {field: 'question_type', title: __('题目类型'), searchList: {"single":__('单选题'),"multiple":__('多选题'),"text":__('文本题'),"image":__('图片题'),"video":__('视频题'),"sort":__('排序题'),"matrix":__('矩阵题'),"slider":__('滑块题')}, formatter: function(value, row, index) {
                            var typeMap = {
                                'single': '<span class="label label-primary">单选题</span>',
                                'multiple': '<span class="label label-success">多选题</span>',
                                'text': '<span class="label label-info">文本题</span>',
                                'image': '<span class="label label-warning">图片题</span>',
                                'video': '<span class="label label-danger">视频题</span>',
                                'sort': '<span class="label label-default">排序题</span>',
                                'matrix': '<span class="label label-primary">矩阵题</span>',
                                'slider': '<span class="label label-success">滑块题</span>'
                            };
                            return typeMap[value] || '<span class="label label-default">未知</span>';
                        }},
                        {field: 'multilang', title: __('多语言'), formatter: function(value, row, index) {
                            if (value && value.length > 0) {
                                var langs = value.split(',');
                                var html = '';
                                langs.forEach(function(lang) {
                                    if (lang === 'zh') {
                                        html += '<span class="label label-primary" style="margin-right: 3px;">中文</span>';
                                    } else if (lang === 'en') {
                                        html += '<span class="label label-success" style="margin-right: 3px;">English</span>';
                                    }
                                });
                                    return html;
                            }
                            return '<span class="label label-default">无</span>';
                        }},
                        {field: 'content_count', title: __('内容数量'), formatter: function(value, row, index) {
                            if (value && value > 0) {
                                return '<span class="badge badge-success">' + value + '</span>';
                            }
                            return '<span class="badge badge-default">0</span>';
                        }},
                        {field: 'option_count', title: __('选项数量'), formatter: function(value, row, index) {
                            if (value && value > 0) {
                                return '<span class="badge badge-info">' + value + '</span>';
                            }
                            return '<span class="badge badge-default">0</span>';
                        }},
                        {field: 'sort_order', title: __('排序'), sortable: true},
                        {field: 'status', title: __('状态'), searchList: {"1":__('启用'),"0":__('关闭')}, formatter: function(value, row, index) {
                            if (value == 1) {
                                return '<span class="label label-success">启用</span>';
                            } else {
                                return '<span class="label label-danger">关闭</span>';
                            }
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
            
            // 绑定多语言按钮事件
            $(document).on('click', '.btn-multilang', function(e) {
                e.preventDefault();
                var questionId = $(this).data('id');
                Fast.api.open('psychometry/question/multilang?ids=' + questionId, '题目多语言管理', {
                    area: ['80%', '80%']
                });
            });
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            var optionIndex = {zh: 0, en: 0};
            
            // 初始化题目内容和选项
            initQuestionContent();
            initOptions();
            
            // 监听题目类型变化
            $('#c-question_type').on('change', function() {
                toggleOptionsPanel();
            });
            
            // 初始化时根据题目类型显示/隐藏选项面板
            toggleOptionsPanel();
            
            // 添加选项按钮
            $(document).on('click', '.add-option-btn', function() {
                var lang = $(this).data('lang') || 'zh';
                addOption(lang);
            });
            
            // 删除选项按钮事件
            $(document).on('click', '.remove-option', function() {
                var $item = $(this).closest('.option-item');
                var lang = $item.data('lang');
                $item.remove();
                updateOptionKeys(lang);
                syncToHiddenFields();
            });
            
            // 选项内容变化事件
            $(document).on('change', '.option-text, .option-score, .option-target', function() {
                syncToHiddenFields();
            });
            
            // 表单提交前同步数据
            $('#edit-form').on('submit', function(e) {
                console.log('表单提交前同步数据');
                syncQuestionContent();
                syncToHiddenFields();
            });
            
            // 根据题目类型显示/隐藏选项面板
            function toggleOptionsPanel() {
                var questionType = $('#c-question_type').val();
                var needOptions = ['single', 'multiple', 'image', 'video', 'sort', 'matrix'];
                
                if (needOptions.indexOf(questionType) !== -1) {
                    $('#options-panel').show();
                } else {
                    $('#options-panel').hide();
                }
            }
            
            // 初始化题目内容（多语言）
            function initQuestionContent() {
                var questionId = $('#c-id').val();
                if (!questionId) return;
                
                $.get(Fast.api.fixurl('psychometry/question/get_question_content'), {question_id: questionId}, function(data) {
                    if (data.code === 1 && data.data) {
                        var contents = data.data;
                        
                        // 加载中文内容
                        if (contents.zh) {
                            $('#content-zh-text').val(contents.zh.question_text || '');
                            $('#content-zh-media').val(contents.zh.question_media || '');
                            $('#content-zh-hint').val(contents.zh.question_hint || '');
                        }
                        
                        // 加载英文内容
                        if (contents.en) {
                            $('#content-en-text').val(contents.en.question_text || '');
                            $('#content-en-media').val(contents.en.question_media || '');
                            $('#content-en-hint').val(contents.en.question_hint || '');
                        }
                    }
                }, 'json');
            }
            
            // 同步题目内容到后端
            function syncQuestionContent() {
                var questionId = $('#c-id').val();
                if (!questionId) return;
                
                var contentData = {
                    question_id: questionId,
                    contents: {
                        zh: {
                            question_text: $('#content-zh-text').val(),
                            question_media: $('#content-zh-media').val(),
                            question_hint: $('#content-zh-hint').val()
                        },
                        en: {
                            question_text: $('#content-en-text').val(),
                            question_media: $('#content-en-media').val(),
                            question_hint: $('#content-en-hint').val()
                        }
                    }
                };
                
                // 异步保存，不阻塞表单提交
                $.post(Fast.api.fixurl('psychometry/question/save_question_content'), contentData, function(data) {
                    console.log('题目内容保存结果:', data);
                });
            }
            
            // 初始化选项数据
            function initOptions() {
                var questionId = $('#c-id').val();
                console.log('初始化选项，题目ID:', questionId);
                
                if (questionId) {
                    $.get(Fast.api.fixurl('psychometry/question/get_options'), {question_id: questionId}, function(data) {
                        console.log('获取选项数据:', data);
                        if (data.code === 1) {
                            var options = data.data;
                            
                            // 清空现有选项
                            $('#options-list-zh, #options-list-en').empty();
                            
                            // 加载中文选项
                            if (options.zh && options.zh.length > 0) {
                                console.log('加载中文选项:', options.zh);
                                options.zh.forEach(function(option, index) {
                                    addOption('zh', option.option_text || '', option.score || 1, option.option_key || '', option.target_dimension || '');
                                });
                            }
                            
                            // 加载英文选项
                            if (options.en && options.en.length > 0) {
                                console.log('加载英文选项:', options.en);
                                options.en.forEach(function(option, index) {
                                    addOption('en', option.option_text || '', option.score || 1, option.option_key || '', option.target_dimension || '');
                                });
                            }
                            
                            // 如果没有选项，添加默认选项
                            if ((!options.zh || options.zh.length === 0) && (!options.en || options.en.length === 0)) {
                                console.log('没有选项数据，添加默认选项');
                                addOption('zh');
                            }
                        } else {
                            console.log('获取选项失败:', data.msg);
                            addOption('zh');
                        }
                    }, 'json').fail(function(xhr, status, error) {
                        console.log('AJAX请求失败:', error);
                        // 如果加载失败，添加默认选项
                        addOption('zh');
                    });
                } else {
                    console.log('新建题目，添加默认选项');
                    // 新建题目，添加默认选项
                    addOption('zh');
                }
            }
            
            // 添加选项
            function addOption(lang, text = '', score = 1, key = '', targetDimension = '') {
                console.log('添加选项函数被调用:', {lang, text, score, key, targetDimension});
                optionIndex[lang]++;
                var optionKey = key || String.fromCharCode(64 + optionIndex[lang]); // A, B, C, D...
                var langText = lang === 'zh' ? '中文' : 'English';
                var placeholder = lang === 'zh' ? '请输入选项内容' : 'Enter option content';
                var deleteText = lang === 'zh' ? '删除' : 'Delete';
                var scoreLabel = lang === 'zh' ? '分值' : 'Score';
                var dimensionLabel = lang === 'zh' ? '目标维度' : 'Target Dimension';
                var dimensionPlaceholder = lang === 'zh' ? '如：E, I, S, N等' : 'e.g., E, I, S, N';
                console.log('生成的选项键:', optionKey);
                
                var optionHtml = `
                    <div class="option-item" data-key="${optionKey}" data-lang="${lang}">
                        <div class="option-header">
                            <span class="option-key">${lang === 'zh' ? '选项' : 'Option'} ${optionKey}</span>
                            <button type="button" class="btn btn-xs btn-danger remove-option">
                                <i class="fa fa-trash"></i> ${deleteText}
                            </button>
                        </div>
                        <div class="option-content">
                            <input type="text" class="form-control option-text" placeholder="${placeholder}" value="${text}">
                        </div>
                        <div class="option-rules">
                            <div class="rule-item">
                                <span class="rule-label">${scoreLabel}:</span>
                                <input type="number" class="form-control rule-input option-score" value="${score}" min="0" step="0.1">
                            </div>
                            <div class="rule-item">
                                <span class="rule-label">${dimensionLabel}:</span>
                                <input type="text" class="form-control rule-input option-target" placeholder="${dimensionPlaceholder}" value="${targetDimension}">
                            </div>
                        </div>
                    </div>
                `;
                $('#options-list-' + lang).append(optionHtml);
                updateOptionKeys(lang);
                syncToHiddenFields();
            }
            
            // 更新选项键
            function updateOptionKeys(lang) {
                var $items = $('#options-list-' + lang + ' .option-item');
                $items.each(function(index) {
                    var newKey = String.fromCharCode(65 + index); // A, B, C, D...
                    $(this).attr('data-key', newKey);
                    $(this).find('.option-key').text('选项 ' + newKey + ' (' + (lang === 'zh' ? '中文' : 'English') + ')');
                });
            }
            
            // 同步到隐藏字段
            function syncToHiddenFields() {
                var options = [];
                var scoringRules = [];
                
                // 收集中文选项
                $('#options-list-zh .option-item').each(function() {
                    var $item = $(this);
                    var key = $item.attr('data-key');
                    var text = $item.find('.option-text').val();
                    var score = parseFloat($item.find('.option-score').val()) || 1;
                    var target = $item.find('.option-target').val();
                    
                    if (text.trim()) {
                        options.push({
                            key: key,
                            value: text,
                            lang: 'zh',
                            score: score
                        });
                        
                        if (target.trim()) {
                            scoringRules.push({
                                value: score,
                                action: 'add_score',
                                target: target,
                                condition: 'option_key = "' + key + '"'
                            });
                        }
                    }
                });
                
                // 收集英文选项
                $('#options-list-en .option-item').each(function() {
                    var $item = $(this);
                    var key = $item.attr('data-key');
                    var text = $item.find('.option-text').val();
                    var score = parseFloat($item.find('.option-score').val()) || 1;
                    var target = $item.find('.option-target').val();
                    
                    if (text.trim()) {
                        options.push({
                            key: key,
                            value: text,
                            lang: 'en',
                            score: score
                        });
                        
                        if (target.trim()) {
                            scoringRules.push({
                                value: score,
                                action: 'add_score',
                                target: target,
                                condition: 'option_key = "' + key + '"'
                            });
                        }
                    }
                });
                
                // 更新隐藏字段
                $('#c-options_config').val(JSON.stringify(options));
                $('#c-scoring_rules').val(JSON.stringify(scoringRules));
                
                // 调试输出
                console.log('同步的选项数据:', options);
                console.log('同步的评分规则:', scoringRules);
            }

            Controller.api.bindevent();
        },
        
        multilang: function () {
            Controller.api.bindevent();
        },
        
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {
                thumb: function (value, row, index) {
                    if(typeof (value) == 'string' && value.length > 0){
                        return '<a href="' + value + '" target="_blank"><img src="' + value +'" alt="" style="max-height:90px;max-width:120px"></a>';
                    }else{
                        return "";
                    }
                },
                url: function (value, row, index) {
                    return '<a href="' + value + '" target="_blank" class="label bg-green">' + value + '</a>';
                },
            }
        }
    };

    return Controller;
});

function answer_value(obj) {
    // var data_name = $(obj).attr('name');
    // answer_name = '[data-name="'+data_name+'"]'
    // var answer_obj = $(answer_name);
    // var answer_value = $(obj).val();
    // answer_obj.attr('value',answer_value);
    // console.log(answer_name);
}