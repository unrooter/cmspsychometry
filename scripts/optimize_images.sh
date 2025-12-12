#!/bin/bash

# =============================================================================
# 图片优化脚本
# 功能：压缩 JPEG/PNG 图片，生成 WebP 格式，创建响应式尺寸
# =============================================================================

set -e

# 配置
UPLOAD_DIR="../public/uploads"
QUALITY=85
WEBP_QUALITY=80

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查依赖
check_dependencies() {
    echo -e "${YELLOW}检查依赖工具...${NC}"
    
    local missing_tools=()
    
    if ! command -v jpegoptim &> /dev/null; then
        missing_tools+=("jpegoptim")
    fi
    
    if ! command -v optipng &> /dev/null; then
        missing_tools+=("optipng")
    fi
    
    if ! command -v cwebp &> /dev/null; then
        missing_tools+=("cwebp (webp)")
    fi
    
    if ! command -v convert &> /dev/null; then
        missing_tools+=("convert (imagemagick)")
    fi
    
    if [ ${#missing_tools[@]} -ne 0 ]; then
        echo -e "${RED}缺少以下工具：${missing_tools[*]}${NC}"
        echo ""
        echo "安装方法："
        echo "  macOS:   brew install jpegoptim optipng webp imagemagick"
        echo "  Ubuntu:  sudo apt-get install jpegoptim optipng webp imagemagick"
        echo "  CentOS:  sudo yum install jpegoptim optipng libwebp-tools ImageMagick"
        exit 1
    fi
    
    echo -e "${GREEN}✓ 所有依赖工具已安装${NC}"
}

# 压缩 JPEG 图片
optimize_jpeg() {
    local file=$1
    local original_size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")
    
    echo "  压缩 JPEG: $(basename "$file")"
    jpegoptim --max=$QUALITY --strip-all --preserve --quiet "$file"
    
    local new_size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")
    local saved=$((original_size - new_size))
    local percent=$((saved * 100 / original_size))
    
    if [ $saved -gt 0 ]; then
        echo -e "    ${GREEN}节省: $(numfmt --to=iec $saved) ($percent%)${NC}"
    fi
}

# 压缩 PNG 图片
optimize_png() {
    local file=$1
    local original_size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")
    
    echo "  压缩 PNG: $(basename "$file")"
    optipng -o3 -preserve -quiet "$file"
    
    local new_size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")
    local saved=$((original_size - new_size))
    
    if [ $saved -gt 0 ]; then
        local percent=$((saved * 100 / original_size))
        echo -e "    ${GREEN}节省: $(numfmt --to=iec $saved) ($percent%)${NC}"
    fi
}

# 生成 WebP 格式
generate_webp() {
    local file=$1
    local webp_file="${file%.*}.webp"
    
    if [ -f "$webp_file" ] && [ "$webp_file" -nt "$file" ]; then
        return
    fi
    
    echo "  生成 WebP: $(basename "$webp_file")"
    cwebp -q $WEBP_QUALITY "$file" -o "$webp_file" -quiet
    
    local original_size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")
    local webp_size=$(stat -f%z "$webp_file" 2>/dev/null || stat -c%s "$webp_file")
    local saved=$((original_size - webp_size))
    
    if [ $saved -gt 0 ]; then
        local percent=$((saved * 100 / original_size))
        echo -e "    ${GREEN}WebP 节省: $(numfmt --to=iec $saved) ($percent%)${NC}"
    fi
}

# 生成响应式尺寸
generate_responsive() {
    local file=$1
    local filename=$(basename "$file")
    local basename="${filename%.*}"
    local ext="${filename##*.}"
    local dir=$(dirname "$file")
    
    # 获取原始尺寸
    local width=$(identify -format "%w" "$file")
    
    # 只为大于 960px 的图片生成响应式版本
    if [ $width -lt 960 ]; then
        return
    fi
    
    echo "  生成响应式尺寸: $(basename "$file")"
    
    # 生成 640px, 960px, 1280px 版本
    for size in 640 960 1280; do
        if [ $width -gt $size ]; then
            local output="$dir/${basename}_${size}.$ext"
            if [ ! -f "$output" ] || [ "$file" -nt "$output" ]; then
                convert "$file" -resize ${size}x -quality $QUALITY "$output"
                echo "    生成 ${size}px 版本"
                
                # 同时生成 WebP
                local webp_output="$dir/${basename}_${size}.webp"
                cwebp -q $WEBP_QUALITY "$output" -o "$webp_output" -quiet
            fi
        fi
    done
}

# 处理单个目录
process_directory() {
    local dir=$1
    
    echo -e "\n${YELLOW}处理目录: $dir${NC}"
    
    local jpeg_count=0
    local png_count=0
    local webp_count=0
    
    # 处理 JPEG 文件
    while IFS= read -r -d '' file; do
        optimize_jpeg "$file"
        generate_webp "$file"
        generate_responsive "$file"
        ((jpeg_count++))
    done < <(find "$dir" -type f \( -iname "*.jpg" -o -iname "*.jpeg" \) -print0)
    
    # 处理 PNG 文件
    while IFS= read -r -d '' file; do
        optimize_png "$file"
        generate_webp "$file"
        generate_responsive "$file"
        ((png_count++))
    done < <(find "$dir" -type f -iname "*.png" -print0)
    
    echo -e "${GREEN}完成: 处理了 $jpeg_count 个 JPEG 和 $png_count 个 PNG 文件${NC}"
}

# 主函数
main() {
    echo "========================================="
    echo "      图片优化脚本"
    echo "========================================="
    
    check_dependencies
    
    if [ ! -d "$UPLOAD_DIR" ]; then
        echo -e "${RED}错误: 上传目录不存在: $UPLOAD_DIR${NC}"
        exit 1
    fi
    
    echo -e "\n${YELLOW}开始优化图片...${NC}"
    echo "上传目录: $UPLOAD_DIR"
    echo "JPEG 质量: $QUALITY"
    echo "WebP 质量: $WEBP_QUALITY"
    
    # 处理主要图片目录
    if [ -d "$UPLOAD_DIR/simpleimages" ]; then
        process_directory "$UPLOAD_DIR/simpleimages"
    fi
    
    # 处理其他子目录
    for subdir in "$UPLOAD_DIR"/*; do
        if [ -d "$subdir" ] && [ "$subdir" != "$UPLOAD_DIR/simpleimages" ]; then
            process_directory "$subdir"
        fi
    done
    
    echo ""
    echo -e "${GREEN}=========================================${NC}"
    echo -e "${GREEN}  图片优化完成！${NC}"
    echo -e "${GREEN}=========================================${NC}"
    echo ""
    echo "下一步："
    echo "1. 在模板中添加 WebP 支持（使用 <picture> 标签）"
    echo "2. 配置 Nginx 缓存（参考 nginx_cache_config.conf）"
    echo "3. 测试图片加载性能"
}

# 运行主函数
main
