from pathlib import Path

p = Path('xe-dap-ninh-binh/xe-dap-ninh-binh.php')
s = p.read_text(encoding='utf-8')

# Version labels
s = s.replace('Version: 2.2.1', 'Version: 2.5.0', 1)
s = s.replace("const VERSION = '2.2.1';", "const VERSION = '2.5.0';", 1)
s = s.replace('>V2.2.1</span>', '>V2.5.0</span>', 1)
s = s.replace('V2.2.1: tách tên, giá, ảnh và tự nhận diện sản phẩm biến thể; hỗ trợ thuộc tính, variation và nhiều kiểu phân trang; tối đa 300 sản phẩm mỗi lượt.', 'V2.5.0: tự động thêm Thông số kỹ thuật và liên kết nội bộ SEO; dữ liệu kỹ thuật chỉ lấy từ nguồn.', 1)

# Settings
s = s.replace("['api_key'=>'','model'=>'gpt-5-mini','default_category'=>0]", "['api_key'=>'','model'=>'gpt-5-mini','default_category'=>0,'internal_links'=>1,'specs_section'=>1,'max_internal_links'=>3]", 1)
old_save = "update_option(self::OPT_KEY,['api_key'=>trim(sanitize_text_field(wp_unslash($_POST['api_key']??''))),'model'=>sanitize_text_field(wp_unslash($_POST['model']??'gpt-5-mini')),'default_category'=>absint($_POST['default_category']??0)]);"
new_save = "update_option(self::OPT_KEY,['api_key'=>trim(sanitize_text_field(wp_unslash($_POST['api_key']??''))),'model'=>sanitize_text_field(wp_unslash($_POST['model']??'gpt-5-mini')),'default_category'=>absint($_POST['default_category']??0),'internal_links'=>!empty($_POST['internal_links'])?1:0,'specs_section'=>!empty($_POST['specs_section'])?1:0,'max_internal_links'=>max(0,min(6,absint($_POST['max_internal_links']??3)))]);"
s = s.replace(old_save, new_save, 1)

needle = '</select></td></tr></table><button class="button button-primary">Lưu cấu hình</button>'
repl = '''</select></td></tr><tr><th>SEO nội bộ</th><td>
<label><input type="checkbox" name="internal_links" value="1" <?php checked($o['internal_links'],1);?>> Tự thêm liên kết nội bộ sản phẩm liên quan</label><br>
<label><input type="checkbox" name="specs_section" value="1" <?php checked($o['specs_section'],1);?>> Tự thêm phần Thông số kỹ thuật</label><br>
<label>Số liên kết nội bộ tối đa: <input type="number" min="0" max="6" name="max_internal_links" value="<?php echo esc_attr($o['max_internal_links']);?>" style="width:70px"></label>
</td></tr></table><button class="button button-primary">Lưu cấu hình</button>'''
if needle not in s:
    raise SystemExit('settings marker not found')
s = s.replace(needle, repl, 1)

# Insert helpers before AJAX importer.
marker = '    public function ajax_import_products(){'
helpers = r'''    private function xdn_specs_html($data){
        $rows=[];$seen=[];
        $add=function($label,$value)use(&$rows,&$seen){$label=trim(wp_strip_all_tags((string)$label));$value=trim(wp_strip_all_tags((string)$value));$key=mb_strtolower($label);if($label&&$value&&!isset($seen[$key])){$seen[$key]=1;$rows[]='<tr><th>'.esc_html($label).'</th><td>'.esc_html($value).'</td></tr>';}};
        if(!empty($data['sku']))$add('SKU',$data['sku']);
        foreach((array)($data['attributes']??[]) as $a){$opts=$a['options']??[];if(is_array($opts))$opts=implode(', ',$opts);$add($a['name']??'',$opts);}
        if(!empty($data['specs'])){
            $parts=preg_split('/\\r?\\n|\\s*[|;]\\s*/u',(string)$data['specs']);
            foreach($parts as $part){$part=trim($part);if(!$part)continue;if(preg_match('/^([^:：]{2,50})[:：]\\s*(.+)$/u',$part,$m))$add($m[1],$m[2]);else $add('Thông tin kỹ thuật',$part);}
        }
        if(empty($rows))return '';
        return '<section class="xdn-technical-specs"><h2>Thông số kỹ thuật</h2><table class="shop_attributes"><tbody>'.implode('',$rows).'</tbody></table></section>';
    }
    private function xdn_internal_links_html($post_id,$cat){
        $o=$this->opts();$max=max(0,(int)($o['max_internal_links']??3));if(empty($o['internal_links'])||$max<1)return '';
        $ids=[];
        if($cat){$q=new WP_Query(['post_type'=>'product','post_status'=>'publish','posts_per_page'=>$max,'post__not_in'=>[$post_id],'tax_query'=>[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$cat]],'orderby'=>'date','order'=>'DESC','no_found_rows'=>true,'fields'=>'ids']);$ids=$q->posts;}
        if(!$ids){$q=new WP_Query(['post_type'=>'product','post_status'=>'publish','posts_per_page'=>$max,'post__not_in'=>[$post_id],'orderby'=>'date','order'=>'DESC','no_found_rows'=>true,'fields'=>'ids']);$ids=$q->posts;}
        $items=[];foreach($ids as $id){$url=get_permalink($id);$title=get_the_title($id);if($url&&$title)$items[]='<li><a href="'.esc_url($url).'">'.esc_html($title).'</a></li>';if(count($items)>=$max)break;}
        if(!$items)return '';
        return '<section class="xdn-related-links"><h2>Sản phẩm liên quan</h2><ul>'.implode('',$items).'</ul></section>';
    }
    private function xdn_prepare_description($post_id,$desc,$data,$cat){
        $o=$this->opts();$desc=trim((string)$desc);
        if(!empty($o['specs_section'])&&!preg_match('/xdn-technical-specs|Thông số kỹ thuật/i',$desc)){$spec=$this->xdn_specs_html($data);if($spec)$desc.="\n\n".$spec;}
        if(!empty($o['internal_links'])&&!preg_match('/xdn-related-links/i',$desc)){$links=$this->xdn_internal_links_html($post_id,$cat);if($links)$desc.="\n\n".$links;}
        return $desc;
    }

'''
if 'private function xdn_prepare_description' not in s:
    s = s.replace(marker, helpers + marker, 1)

# Variable product: finalize description after parent ID exists.
old_var = "$post_id=$product->save();if($cat)wp_set_object_terms($post_id,[$cat],'product_cat');"
new_var = "$post_id=$product->save();if($cat)wp_set_object_terms($post_id,[$cat],'product_cat');$product->set_description($this->xdn_prepare_description($post_id,$desc,$data,$cat));$product->save();"
if old_var in s:
    s = s.replace(old_var,new_var,1)

# Simple product: finalize after post ID/category exists.
old_simple = "if($cat)wp_set_object_terms($post_id,[$cat],'product_cat');if(!empty($ai['tags'])&&is_array($ai['tags']))"
new_simple = "$final_desc=$this->xdn_prepare_description($post_id,$desc,$data,$cat);wp_update_post(['ID'=>$post_id,'post_content'=>$final_desc]);if($cat)wp_set_object_terms($post_id,[$cat],'product_cat');if(!empty($ai['tags'])&&is_array($ai['tags']))"
if old_simple in s:
    s = s.replace(old_simple,new_simple,1)

# Make update handler/version cache safe for this test branch by clearing release cache after plugin upgrade.
s = s.replace("'requires_php'=>'7.4'];", "'requires_php'=>'7.4','autoupdate'=>true];", 1)

p.write_text(s,encoding='utf-8')
print('V2.5.0 patch applied')
