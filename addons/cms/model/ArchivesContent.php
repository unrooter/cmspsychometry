<?php

namespace addons\cms\model;

use think\Model;

/**
 * 文章多语言内容模型
 */
class ArchivesContent extends Model
{
    // 表名
    protected $name = 'cms_archives_content';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp';

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'lang_text'
    ];
    
    /**
     * 获取语言列表
     */
    public function getLangList()
    {
        return [
            'zh' => '中文',
            'en' => 'English', 
            'ja' => '日本語',
            'ko' => '한국어'
        ];
    }

    /**
     * 获取语言文本
     */
    public function getLangTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['lang']) ? $data['lang'] : '');
        $list = $this->getLangList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    /**
     * 关联文章模型
     */
    public function archives()
    {
        return $this->belongsTo('Archives', 'archives_id', 'id');
    }

    /**
     * 获取多语言内容，支持回退到中文
     * @param int $archives_id 文章ID
     * @param string $lang 语言代码
     * @return array|null
     */
    public static function getMultilangContent($archives_id, $lang)
    {
        // 先尝试获取指定语言的内容
        $content = self::where('archives_id', $archives_id)
            ->where('lang', $lang)
            ->find();
        
        // 如果没有找到，且不是中文，则回退到中文
        if (!$content && $lang !== 'zh') {
            $content = self::where('archives_id', $archives_id)
                ->where('lang', 'zh')
                ->find();
        }
        
        return $content ? $content->toArray() : null;
    }

    /**
     * 获取文章的所有语言版本
     * @param int $archives_id 文章ID
     * @return array
     */
    public static function getAllLangVersions($archives_id)
    {
        return self::where('archives_id', $archives_id)
            ->column('lang', 'lang');
    }

    /**
     * 检查是否存在指定语言版本
     * @param int $archives_id 文章ID
     * @param string $lang 语言代码
     * @return bool
     */
    public static function hasLangVersion($archives_id, $lang)
    {
        return self::where('archives_id', $archives_id)
            ->where('lang', $lang)
            ->count() > 0;
    }
}
