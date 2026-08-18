from pathlib import Path
p=Path('xe-dap-ninh-binh/xe-dap-ninh-binh.php')
s=p.read_text(encoding='utf-8')
s=s.replace("$msg='Đã cập nhật nguồn: '.$gallery_count=count($gallery).' ảnh.';", "$msg='Đã cập nhật nguồn: '.count($gallery).' ảnh.';")
s=s.replace("if(log){const l=document.getElementById('log');if(l)l.innerHTML+='Cập nhật #'+esc(id)+' → '+esc(j.data?.message||'Đã cập nhật')+'<br>';}", "const l=document.getElementById('log');if(l)l.innerHTML+='Cập nhật #'+esc(id)+' → '+esc(j.data?.message||'Đã cập nhật')+'<br>';")
p.write_text(s,encoding='utf-8')
print('fixed')
