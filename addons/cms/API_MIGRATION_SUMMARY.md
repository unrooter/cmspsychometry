# API迁移总结

## 迁移日期
2025-10-23

## 迁移内容

### 后端控制器
**文件**: `/addons/cms/controller/Psychometry.php`

#### 修改内容：
1. **命名空间修改**
   - 原来: `namespace app\api\controller;`
   - 修改为: `namespace addons\cms\controller;`

2. **基类修改**
   - 原来: `use app\common\controller\Api;` 和 `extends Api`
   - 修改为: `use addons\cms\controller\api\Base;` 和 `extends Base`

3. **API路由变化**
   - 原来: `/api/psychometry/*`
   - 修改为: `/addons/cms/psychometry/*`

### 前端文件修改

已更新以下15个HTML文件中的API调用路径：

#### 主要测试页面
1. `show_ceshi.html` - 心理测试详情页（2处修改）
   - `getQuestions` API
   - `getExamResult` API

2. `show_quwei.html` - 趣味测试详情页（2处修改）
   - `getQuestions` API
   - `getExamResult` API

3. `show_title.html` - 标题详情页（1处修改）
   - `getQuestions` API

4. `page_questions.html` - 答题页面（1处修改）
   - `answerInfo` API

5. `page_result_router.html` - 结果路由页（1处修改）
   - `answerInfo` API

#### 结果页面（根目录）
6. `results_mbti.html` - MBTI结果页（1处修改）
7. `results_score.html` - 分数结果页（1处修改）
8. `results_dimension.html` - 维度结果页（1处修改）
9. `results_custom.html` - 自定义结果页（1处修改）
10. `results_multiple.html` - 多维度结果页（1处修改）
11. `results_nine_type.html` - 九型人格结果页（1处修改）

#### 结果页面（results子目录）
12. `results/mbti.html` - MBTI结果页（1处修改）
13. `results/score.html` - 分数结果页（1处修改）
14. `results/dimension.html` - 维度结果页（1处修改）
15. `results/custom.html` - 自定义结果页（1处修改）

### API接口列表

控制器提供的主要接口：

1. **getTestInfo** - 获取测试信息
   - 路由: `/addons/cms/psychometry/getTestInfo`
   - 方法: POST
   - 参数: aid, lang

2. **getQuestions** - 获取试题选项
   - 路由: `/addons/cms/psychometry/getQuestions`
   - 方法: POST
   - 参数: aid, lang

3. **getExamResult** - 提交测试结果获取答案
   - 路由: `/addons/cms/psychometry/getExamResult`
   - 方法: POST
   - 参数: aid, answer_mark, answer_data

4. **answerInfo** - 获取测试结果详情
   - 路由: `/addons/cms/psychometry/answerInfo`
   - 方法: POST
   - 参数: answer_id, lang

5. **getExamAuth** - 获取测试授权
   - 路由: `/addons/cms/psychometry/getExamAuth`
   - 方法: GET
   - 参数: aid

### 测试类型支持

控制器支持以下测试类型：
- **MBTI** (mbti) - MBTI性格测试
- **分数型** (score) - 分数评估测试
- **维度型** (dimension) - 多维度评估测试
- **自定义** (custom) - 自定义选项测试
- **九型人格** (nine_type) - 九型人格测试
- **多选类型** (multiple_type) - 多选类型测试

### 多语言支持

控制器已实现完整的多语言支持：
- 支持语言: zh（中文）, en（英文）, ja（日文）, ko（韩文）
- 语言获取优先级: API参数 > URL参数 > Cookie > 系统默认
- 智能回退机制: 如果指定语言内容不存在，自动回退到中文

### 注意事项

1. **无需登录**: 所有接口都设置了 `$noNeedLogin = ['*']`，允许游客访问
2. **跨域支持**: 部分接口已添加 `Access-Control-Allow-Origin:*` 头
3. **唯一ID生成**: 使用 `generateUniqueId()` 生成测试结果的唯一标识
4. **向后兼容**: 保留了 `back_type` 字段的转换逻辑，确保与旧数据兼容

## 测试建议

迁移完成后，建议测试以下场景：

1. ✅ 访问测试详情页，验证题目加载
2. ✅ 提交测试答案，验证结果生成
3. ✅ 查看测试结果，验证各种类型的结果展示
4. ✅ 切换语言，验证多语言内容显示
5. ✅ 游客访问，验证无需登录即可测试

## 回滚方案

如需回滚，执行以下操作：

1. 恢复后端文件命名空间：
   - 改回 `namespace app\api\controller;`
   - 改回 `extends Api`

2. 批量替换前端API路径：
   ```bash
   # 将 /addons/cms/psychometry/ 替换回 /api/psychometry/
   ```

## 相关文件

- 后端控制器: `/addons/cms/controller/Psychometry.php`
- 前端视图目录: `/addons/cms/view/default/`
- 基类文件: `/addons/cms/controller/api/Base.php`
