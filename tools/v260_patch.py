from pathlib import Path
p=Path('xe-dap-ninh-binh/xe-dap-ninh-binh.php')
s=p.read_text(encoding='utf-8')
s=s.replace('Version: 2.5.3','Version: 2.6.0',1).replace("const VERSION = '2.5.3';","const VERSION = '2.6.0';",1).replace('>V2.5.3</span>','>V2.6.0</span>',1)
s=s.replace('V2.5.3: cập nhật từ nguồn, chống trùng và hiển thị rõ trạng thái nhập; hỗ trợ thuộc tính, variation, giá và ảnh.','V2.6.0: tự tạo thông số kỹ thuật, liên kết nội bộ SEO và giữ dữ liệu nguồn cho sản phẩm/biến thể.',1)
# options
s=s.replace("['api_key'=>'','model'=>'gpt-5-mini','default_category'=>0]", "['api_key'=>'','model'=>'gpt-5-mini','default_category'=>0,'internal_links'=>1,'specs_section'=>1,'max_internal_links'=>3]",1)
old="update_option(self::OPT_KEY,['api_key'=>trim(sanitize_text_field(wp_unslash($_POST['api_key']??''))),'model'=>sanitize_text_field(wp_unslash($_POST['model']??'gpt-5-mini')),'default_category'=>absint($_POST['default_category']??0)]);"
new="update_option(self::OPT_KEY,['api_key'=>trim(sanitize_text_field(wp_unslash($_POST['api_key']??''))),'model'=>sanitize_text_field(wp_unslash($_POST['model']??'gpt-5-mini')),'default_category'=>absint($_POST['default_category']??0),'internal_links'=>!empty($_POST['internal_links'])?1:0,'specs_section'=>!empty($_POST['specs_section'])?1:0,'max_internal_links'=>max(0,min(6,absint($_POST['max_internal_links']??3)))]);"
s=s.replace(old,new,1)
# settings fields
needle="</select></td></tr></table><button class=\"button button-primary\">Lưu cấu hình</button>"
repl="</select></td></tr><tr><th>SEO nội bộ</th><td><label><input type=\"checkbox\" name=\"internal_links\" value=\"1\" <?php checked($o['internal_links'],1);?>> Tự thêm liên kết nội bộ liên quan</label><br><label><input type=\"checkbox\" name=\"specs_section\" value=\"1\" <?php checked($o['specs_section'],1);?>> Tự thêm phần Thông số kỹ thuật</label><br><label>Số liên kết nội bộ tối đa: <input type=\"number\" min=\"0\" max=\"6\" name=\"max_internal_links\" value=\"<?php echo esc_attr($o['max_internal_links']);?>\" style=\"width:70px\"></label></td></tr></table><button class=\"button button-primary\">Lưu cấu hình</button>"
if needle not in s: raise SystemExit('settings marker missing')
s=s.replace(needle,repl,1)
# Add methods before ajax_update_source
marker='    public function ajax_update_source(){'
methods=r'''    private function xdn_technical_specs_html($data){
        $rows=[];$seen=[];$add=function($label,$value)use(&$rows,&$seen){$label=trim(wp_strip_all_tags((string)$label));$value=trim(wp_strip_all_tags((string)$value));if($label&&$value&&empty($seen[mb_strtolower($label)])){$seen[mb_strtolower($label)]=1;$rows[]='<tr><th scope="row">'.esc_html($label).'</th><td>'.esc_html($value).'</td></tr>';}};
        $add('SKU',$data['sku']??'');
        foreach((array)($data['attributes']??[]) as $a){$name=$a['name']??'';$opts=$a['options']??[];if(is_array($opts))$opts=implode(', ',$opts);$add($name,$opts);}
        if(!empty($data['frame']))$add('Khung xe',$data['frame']); if(!empty($data['fork']))$add('Phuộc',$data['fork']); if(!empty($data['wheel_size']))$add('Kích thước bánh',$data['wheel_size']); if(!empty($data['tire']))$add('Lốp',$data['tire']); if(!empty($data['height']))$add('Chiều cao phù hợp',$data['height']); if(!empty($data['weight']))$add('Trọng lượng',$data['weight']); if(!empty($data['brand']))$add('Thương hiệu',$data['brand']);
        if(empty($rows))return '';
        return '<section class="xdn-technical-specs"><h2>Thông số kỹ thuật</h2><table class="shop_attributes"><tbody>'.implode('',$rows).'</tbody></table></section>';
    }
    private function xdn_internal_links_html($post_id,$cat=0){
        $o=$this->opts();if(empty($o['internal_links'])||empty($o['max_internal_links']))return '';$links=[];$home=home_url('/');$links[]=['Xe Đạp Ninh Bình',$home];if(!$cat){$terms=wp_get_post_terms($post_id,'product_cat',['fields'=>'ids']);if(!is_wp_error($terms)&&!empty($terms))$cat=(int)$terms[0];}if($cat){$term=get_term($cat,'product_cat');if($term&&!is_wp_error($term))$links[]=['Xe đạp '. $term->name,get_term_link($term)];$q=new WP_Query(['post_type'=>'product','post_status'=>'publish','posts_per_page'=>max(1,$o['max_internal_links']-count($links)+1),'post__not_in'=>[$post_id],'tax_query'=>[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$cat]],'orderby'=>'rand','no_found_rows'=>true,'fields'=>'ids']);foreach($q->posts as $id){$links[]=[get_the_title($id),get_permalink($id)];if(count($links)>$o['max_internal_links'])break;}}
        $links=array_slice($links,0,(int)$o['max_internal_links']);if(!$links)return '';return '<section class="xdn-related-links"><h2>Sản phẩm liên quan</h2><ul>'.implode('',array_map(function($x){return '<li><a href="'.esc_url($x[1]).'">'.esc_html($x[0]).'</a></li>';},$links)).'</ul></section>';
    }
    private function xdn_prepare_content($post_id,$desc,$data,$cat=0){$o=$this->opts();$desc=trim((string)$desc);if(!empty($o['specs_section'])&&!preg_match('/Thông số kỹ thuật/i',$desc))$desc.="\n\n".$this->xdn_technical_specs_html($data);if(!empty($o['internal_links'])&&!preg_match('/xdn-related-links/i',$desc))$desc.="\n\n".$this->xdn_internal_links_html($post_id,$cat);return $desc;}

'''
s=s.replace(marker,methods+marker,1)
# Prepare descriptions in import/update paths
s=s.replace("$desc=wp_kses_post($ai['description']??$data['content']??'');$short=wp_kses_post", "$desc=wp_kses_post($ai['description']??$data['content']??'');$desc=$this->xdn_prepare_content(0,$desc,$data,$cat);$short=wp_kses_post",1)
# The update path has $post_id and cat already, replace second occurrence if still exists
s=s.replace("$desc=wp_kses_post($ai['description']??$data['content']??'');$short=wp_kses_post($ai['short_description']??$data['short_description']??'');$cat=0;", "$desc=wp_kses_post($ai['description']??$data['content']??'');$short=wp_kses_post($ai['short_description']??$data['short_description']??'');$cat=0;",1)
# after cat determined in update, prepare description
s=s.replace("if(!is_wp_error($terms)&&!empty($terms))$cat=(int)$terms[0];$product=wc_get_product($post_id);", "if(!is_wp_error($terms)&&!empty($terms))$cat=(int)$terms[0];$desc=$this->xdn_prepare_content($post_id,$desc,$data,$cat);$product=wc_get_product($post_id);",1)
# fix import: first replacement used $cat before declared; replace that bad expression with simple content, then prepare after AI
s=s.replace("$desc=wp_kses_post($ai['description']??$data['content']??'');$desc=$this->xdn_prepare_content(0,$desc,$data,$cat);$short=wp_kses_post($ai['short_description']??$data['short_description']??'');", "$desc=wp_kses_post($ai['description']??$data['content']??'');$short=wp_kses_post($ai['short_description']??$data['short_description']??'');")
s=s.replace("$short=wp_kses_post($ai['short_description']??$data['short_description']??'');try{", "$short=wp_kses_post($ai['short_description']??$data['short_description']??'');$desc=$this->xdn_prepare_content(0,$desc,$data,$cat);try{",1)
# Version text in page title fallback
s=s.replace('V2.5.3</span>','V2.6.0</span>')
p.write_text(s,encoding='utf-8')
print('V2.6 patch ready')
