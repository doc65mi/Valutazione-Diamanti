"""Create a complete deployable application archive, excluding local runtime data."""
from pathlib import Path
import re, zipfile
root=Path(__file__).resolve().parent.parent
version=re.search(r"APP_VERSION = '([^']+)'",(root/'PHP di sistema/core.php').read_text(encoding='utf-8')).group(1)
output=root/'tools/distributions'/f'comprodiamanti-{version}-completo.zip'
output.parent.mkdir(parents=True,exist_ok=True)
folders=['blog','admin','api','assets','content','it','en','partials','pages-src','PHP di sistema','files MD']
with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as archive:
    for folder in folders:
        for file in sorted((root/folder).rglob('*')):
            if file.is_file(): archive.write(file,file.relative_to(root).as_posix())
    for name in ['.htaccess','index.php','index.html','robots.txt','sitemap.xml','llms.txt']:
        archive.write(root/name,name)
    for folder in ['data','uploads']:
        for file in sorted((root/folder).rglob('.htaccess')):
            archive.write(file,file.relative_to(root).as_posix())
    for folder in ['data/sessions/','uploads/clienti/']:
        archive.writestr(folder,'')
    archive.write(Path(__file__),'tools/package_full.py')
with zipfile.ZipFile(output) as archive:
    assert archive.testzip() is None
    names=set(archive.namelist())
    for name in ['admin/index.php','api/index.php','PHP di sistema/install.php','content/home.it.json','it/index.html','data/.htaccess','uploads/clienti/.htaccess']:
        assert name in names,name
    assert not any(name.endswith(('.sqlite','.sqlite-wal','.sqlite-shm')) or 'first-login' in name or name.startswith('old-page/') for name in names)
print(f'{output}\n{len(names)} voci; {output.stat().st_size} byte; integrità ZIP verificata.')
