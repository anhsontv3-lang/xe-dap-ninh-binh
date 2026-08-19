from pathlib import Path

p = Path('xe-dap-ninh-binh/xe-dap-ninh-binh.php')
s = p.read_text(encoding='utf-8')

# Bump version for this focused feature release.
s = s.replace('Version: 2.6.0', 'Version: 2.6.1', 1)
s = s.replace("const VERSION = '2.6.0';", "const VERSION = '2.6.1';", 1)
s = s.replace('>V2.6.0</span>', '>V2.6.1</span>', 1)
s = s.replace('V2.6.0: tự tạo thông số kỹ thuật, liên kết nội bộ SEO và giữ dữ liệu nguồn cho sản phẩm/biến thể.', 'V2.6.1: cho phép chọn nhiều danh mục WooCommerce khi nhập sản phẩm; giữ nguyên dữ liệu nguồn, SEO và biến thể.', 1)

# Update category selector to native multi-select.
old = '''<select id="cat"><option value="0">Danh mục WooCommerce...</option><?php foreach($cats as $cat)echo '<option value="'.esc_attr($cat->term_id).'">'.esc_html($cat->name).'</option>';?></select> <label><input id="ai" type="checkbox" checked> GPT viết lại SEO</label>'''
new = '''<select id="cat" class="xdn-cat-select" multiple size="4" title="Giữ Ctrl để chọn nhiều danh mục"><option value="0">-- Không chọn danh mục --</option><?php foreach($cats as $cat)echo '<option value="'.esc_attr($cat->term_id).'">'.esc_html($cat->name).'</option>';?></select> <span style="font-size:12px;color:#666">Giữ Ctrl để chọn nhiều danh mục</span> <label><input id="ai" type="checkbox" checked> GPT viết lại SEO</label>'''
if old not in s:
    raise SystemExit('category selector marker not found')
s = s.replace(old, new, 1)

# Add a reusable CSS rule for the multi-select.
s = s.replace('.xdn-status-note{display:block;margin-top:4px;color:#666;font-size:12px}', '.xdn-status-note{display:block;margin-top:4px;color:#666;font-size:12px}.xdn-cat-select{min-width:260px;min-height:96px;vertical-align:middle}', 1)

# JS: send a JSON array called categories instead of one scalar category.
old_js = "const fcat=document.getElementById('cat').value||<?php echo (int)$o['default_category'];?>,useAI=document.getElementById('ai').checked?'1':'0',log=document.getElementById('log'),btn=document.getElementById('imp'),counter=document.getElementById('xdn_import_counter');"
new_js = "const selectedCats=[...document.getElementById('cat').selectedOptions].map(o=>parseInt(o.value||'0',10)).filter(v=>v>0);if(!selectedCats.length&&<?php echo (int)$o['default_category'];?>)selectedCats.push(<?php echo (int)$o['default_category'];?>);const useAI=document.getElementById('ai').checked?'1':'0',log=document.getElementById('log'),btn=document.getElementById('imp'),counter=document.getElementById('xdn_import_counter');"
if old_js not in s:
    raise SystemExit('import JS marker not found')
s = s.replace(old_js, new_js, 1)
s = s.replace("fd.append('category',fcat);fd.append('use_ai',useAI);", "fd.append('categories',JSON.stringify(selectedCats));fd.append('use_ai',useAI);", 1)

# PHP: read category array in ajax importer and use it for assignment and content context.
old_php = "$products=json_decode(wp_unslash($_POST['products']??'[]'),true);$cat=absint($_POST['category']??0);$use_ai=!empty($_POST['use_ai']);"
new_php = "$products=json_decode(wp_unslash($_POST['products']??'[]'),true);$categories=json_decode(wp_unslash($_POST['categories']??'[]'),true);if(!is_array($categories))$categories=[];$categories=array_values(array_unique(array_filter(array_map('absint',$categories))));if(empty($categories)&&!empty($_POST['category']))$categories=[absint($_POST['category'])];$primary_cat=(int)($categories[0]??0);$use_ai=!empty($_POST['use_ai']);"
if old_php not in s:
    raise SystemExit('ajax import category marker not found')
s = s.replace(old_php, new_php, 1)

s = s.replace("$desc=$this->xdn_prepare_content(0,$desc,$data,$cat);", "$desc=$this->xdn_prepare_content(0,$desc,$data,$primary_cat);", 1)
s = s.replace("$r=$this->create_variable_product($name,$desc,$short,$data,$cat,$ai);", "$r=$this->create_variable_product($name,$desc,$short,$data,$primary_cat,$ai);", 1)

# Ensure variable products also receive every selected category.
old_var = "$r=$this->create_variable_product($name,$desc,$short,$data,$primary_cat,$ai);if(is_wp_error($r))throw new Exception($r->get_error_message());$results[]=['status'=>'created'"
new_var = "$r=$this->create_variable_product($name,$desc,$short,$data,$primary_cat,$ai);if(is_wp_error($r))throw new Exception($r->get_error_message());if(!empty($categories))wp_set_object_terms($r['id'],$categories,'product_cat');$results[]=['status'=>'created'"
if old_var not in s:
    raise SystemExit('variable assignment marker not found')
s = s.replace(old_var, new_var, 1)

# Simple products receive every selected category.
s = s.replace("if($cat)wp_set_object_terms($post_id,[$cat],'product_cat');if(!empty($ai['tags'])", "if(!empty($categories))wp_set_object_terms($post_id,$categories,'product_cat');if(!empty($ai['tags'])", 1)

# Return assigned categories in result for visible logging.
s = s.replace("'status'=>'created','id'=>$post_id,'message'=>'Đã tạo sản phẩm nháp #'.$post_id,'name'=>$name,'regular_price'=>$regular,'sale_price'=>$sale,'images'=>count($gallery)", "'status'=>'created','id'=>$post_id,'message'=>'Đã tạo sản phẩm nháp #'.$post_id,'name'=>$name,'regular_price'=>$regular,'sale_price'=>$sale,'images'=>count($gallery),'categories'=>$categories", 1)

p.write_text(s, encoding='utf-8')
print('V2.6.1 multi-category patch applied')
